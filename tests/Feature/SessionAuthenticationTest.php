<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SessionAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_credentials_start_a_session_and_redirect_to_dashboard(): void
    {
        User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('SecurePassword123!'),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'admin@example.test',
            'password' => 'SecurePassword123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_invalid_credentials_do_not_authenticate(): void
    {
        User::factory()->create(['email' => 'admin@example.test']);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'admin@example.test',
            'password' => 'incorrect',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => 'admin@example.test',
                'password' => 'incorrect',
            ])->assertRedirect();
        }

        $this->post(route('login.store'), [
            'email' => 'admin@example.test',
            'password' => 'incorrect',
        ])->assertTooManyRequests();
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_public_signup_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_cli_account_command_provisions_an_account_without_exposing_a_password_argument(): void
    {
        $this->artisan('printgate:user')
            ->expectsQuestion('Name', 'Local Admin')
            ->expectsQuestion('Email', 'admin@example.test')
            ->expectsQuestion('Password (at least 12 characters, mixed case, a number, and a symbol)', 'SecurePassword123!')
            ->expectsQuestion('Confirm password', 'SecurePassword123!')
            ->expectsOutput('Printgate account created for admin@example.test.')
            ->assertSuccessful();

        $user = User::query()->sole();
        $this->assertSame('Local Admin', $user->name);
        $this->assertTrue(Hash::check('SecurePassword123!', $user->password));
    }
}
