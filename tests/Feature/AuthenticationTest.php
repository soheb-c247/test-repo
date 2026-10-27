<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders_without_signup(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in')
            ->assertDontSee('Register')
            ->assertDontSee('Forgot your password?');
    }

    public function test_registration_and_password_reset_routes_do_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['email' => 'x@example.com', 'password' => 'Secret123'])->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
    }

    public function test_admin_can_sign_in_with_case_insensitive_email(): void
    {
        User::factory()->labAdmin()->create(['email' => 'soheb.m@chapter247.com', 'password' => 'Shoaib@123']);

        $this->post('/login', ['email' => 'Soheb.m@chapter247.com', 'password' => 'Shoaib@123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertNotNull(User::first()->last_login_at);
    }

    public function test_invalid_password_is_rejected(): void
    {
        User::factory()->labAdmin()->create(['email' => 'admin@example.com']);

        $this->from('/login')->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_non_administrator_accounts_cannot_sign_in(): void
    {
        User::factory()->create(['email' => 'legacy@example.com', 'password' => 'Secret123']);

        $this->post('/login', ['email' => 'legacy@example.com', 'password' => 'Secret123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_authenticated_non_admin_session_is_signed_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_private_routes_require_authentication(): void
    {
        foreach (['/dashboard', '/reports', '/reports/create', '/tests', '/tests/create', '/patients', '/profile'] as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
        }
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->labAdmin()->create(['email' => 'admin@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong']);
        }

        // Even the correct password is refused while locked out.
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_admin_can_sign_out(): void
    {
        $this->actingAsAdmin();

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_authenticated_admin_is_redirected_away_from_login(): void
    {
        $this->actingAsAdmin();

        $this->get('/login')->assertRedirect(route('dashboard'));
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_admin_seeder_provisions_hashed_password_and_single_admin(): void
    {
        $legacy = User::factory()->labAdmin()->create(['email' => 'old-admin@example.com']);
        config(['lab.admin.email' => 'Soheb.m@chapter247.com', 'lab.admin.password' => 'Shoaib@123', 'lab.admin.name' => 'Admin']);

        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'soheb.m@chapter247.com')->firstOrFail();
        $this->assertTrue($admin->is_lab_admin);
        $this->assertTrue(Hash::check('Shoaib@123', $admin->password));
        $this->assertNotSame('Shoaib@123', $admin->password);
        $this->assertFalse($legacy->fresh()->is_lab_admin);
    }

    public function test_responses_carry_security_headers_and_are_not_cached_when_signed_in(): void
    {
        $this->actingAsAdmin();

        $this->get('/dashboard')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_admin_can_change_password(): void
    {
        $admin = User::factory()->labAdmin()->create(['password' => 'OldPass123']);
        $this->actingAs($admin);

        $this->put('/profile/password', [
            'current_password' => 'OldPass123',
            'password' => 'NewPass456',
            'password_confirmation' => 'NewPass456',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewPass456', $admin->fresh()->password));
    }
}
