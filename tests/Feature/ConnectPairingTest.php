<?php

namespace Tests\Feature;

use App\Models\ConnectSite;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConnectPairingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['printgate.role' => 'hub']);
    }

    public function test_local_role_hides_connect_ui_and_api(): void
    {
        config(['printgate.role' => 'local']);

        $this->actingAs(User::factory()->create())->get('/connect')->assertNotFound();
        $this->postJson('https://localhost/api/connect/pairings', [])->assertNotFound();
    }

    public function test_pairing_requires_explicit_owner_confirmation_and_delivers_credential_once(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $this->from('/connect')->post('https://localhost/connect/invitations')->assertRedirect('/connect')->assertSessionHas('connect_invitation_token');
        $token = session('connect_invitation_token');
        $this->assertSame(64, strlen($token));

        $claimResponse = $this->postJson('https://localhost/api/connect/pairings', [
            'invitation' => $token,
            'site_name' => 'Office Printer',
            'comparison_code' => '482901',
        ])->assertCreated();
        $claimId = $claimResponse->json('claim_id');
        $claimToken = $claimResponse->json('claim_token');
        $this->assertSame('482901', $claimResponse->json('comparison_code'));

        $this->postJson("https://localhost/api/connect/pairings/{$claimId}/credential", ['claim_token' => $claimToken])
            ->assertOk()->assertJson(['status' => 'pending']);

        $this->post("https://localhost/connect/claims/{$claimId}/confirm", ['comparison_code' => '111111'])
            ->assertSessionHasErrors('comparison_code');
        $this->assertDatabaseCount('connect_sites', 0);

        $this->post("https://localhost/connect/claims/{$claimId}/confirm", ['comparison_code' => '482901'])
            ->assertRedirect('/connect');
        $site = ConnectSite::query()->sole();
        $this->assertSame($owner->id, $site->owner_id);
        $this->assertSame(64, strlen($site->credential_digest));

        $delivery = $this->postJson("https://localhost/api/connect/pairings/{$claimId}/credential", ['claim_token' => $claimToken])
            ->assertOk()->assertJson(['status' => 'paired']);
        $credential = $delivery->json('credential');
        $this->assertSame(hash('sha256', $credential), $site->credential_digest);
        $this->assertDatabaseHas('connect_pairing_claims', ['id' => $claimId, 'credential' => null]);
        $this->postJson("https://localhost/api/connect/pairings/{$claimId}/credential", ['claim_token' => $claimToken])->assertGone();
    }

    public function test_site_authentication_resolves_only_active_credentials_and_revoke_takes_effect_immediately(): void
    {
        $owner = User::factory()->create();
        $credential = str_repeat('c', 64);
        $site = $owner->connectSites()->create([
            'name' => 'Desk',
            'credential_digest' => hash('sha256', $credential),
            'credential_issued_at' => now(),
        ]);

        $this->withHeader('Authorization', "Bearer {$credential}")->getJson('https://localhost/api/connect/site')
            ->assertOk()->assertJson(['site_id' => $site->id, 'owner_id' => $owner->id, 'user_id' => $owner->id]);

        $this->actingAs($owner)->delete('https://localhost/connect/sites/'.$site->id)->assertRedirect('/connect');
        $this->withHeader('Authorization', "Bearer {$credential}")->getJson('https://localhost/api/connect/site')->assertUnauthorized();
        $this->assertNotNull($site->fresh()->revoked_at);
    }
}
