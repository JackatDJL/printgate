<?php

namespace Tests\Feature;

use App\Connect\SiteAgent\CredentialStore;
use App\Connect\SiteAgent\SitePairer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConnectSitePairerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pairs_after_hub_owner_confirms_and_saves_credential_privately(): void
    {
        $this->app->useStoragePath(sys_get_temp_dir().'/printgate-test-'.bin2hex(random_bytes(8)));
        Http::preventStrayRequests();
        Http::fake([
            'https://hub.test/api/connect/pairings' => Http::response([
                'claim_id' => 'claim-one', 'claim_token' => str_repeat('a', 64), 'expires_at' => now()->addMinute()->toIso8601String(),
            ], 201),
            'https://hub.test/api/connect/pairings/claim-one/credential' => Http::response([
                'status' => 'paired', 'credential' => str_repeat('b', 64), 'site_id' => 'site-one',
            ]),
        ]);

        $siteId = $this->app->make(SitePairer::class)->pair('https://hub.test', 'Office', str_repeat('c', 64), '123456');
        $configuration = $this->app->make(CredentialStore::class)->read();

        $this->assertSame('site-one', $siteId);
        $this->assertSame(str_repeat('b', 64), $configuration['credential']);
        $this->assertSame('https://hub.test', $configuration['base_url']);
        $this->assertSame(0600, fileperms($this->app->make(CredentialStore::class)->path()) & 0777);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://hub.test/api/connect/pairings'
            && $request['comparison_code'] === '123456');

        @unlink($this->app->make(CredentialStore::class)->path());
    }
}
