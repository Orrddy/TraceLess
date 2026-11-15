<?php

namespace Tests\Feature;

use App\Mail\OtpCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function otpCodeFor(string $email): string
    {
        $code = null;

        Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail) use (&$code, $email) {
            if (! $mail->hasTo($email)) {
                return false;
            }

            $code = $mail->code;

            return true;
        });

        $this->assertNotNull($code, "No OTP email was sent to {$email}.");

        return $code;
    }

    public function test_public_pages_render(): void
    {
        foreach (['/', '/login', '/register', '/privacy', '/terms', '/security'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_registration_requires_an_email_code_before_creating_the_account(): void
    {
        Mail::fake();

        $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'Jane@Example.com',
            'terms' => '1',
        ])->assertRedirect(route('auth.verify'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);

        $this->post('/auth/verify', [
            'code' => $this->otpCodeFor('jane@example.com'),
        ])->assertRedirect(route('identity.create'));

        $this->assertAuthenticated();
        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNull($user->password);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account_created']);
    }

    public function test_registration_requires_terms_and_a_unique_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ])->assertSessionHasErrors('terms');

        $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'existing@example.com',
            'terms' => '1',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_flow_uses_an_email_code_and_logout_clears_the_session(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->post('/login', ['email' => 'jane@example.com'])
            ->assertRedirect(route('auth.verify'));

        $this->assertGuest();

        $this->post('/auth/verify', [
            'code' => $this->otpCodeFor('jane@example.com'),
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_login_does_not_reveal_whether_an_account_exists(): void
    {
        Mail::fake();

        $this->post('/login', ['email' => 'nobody@example.com'])
            ->assertRedirect(route('auth.verify'))
            ->assertSessionHas('status');

        Mail::assertNothingSent();

        $this->post('/auth/verify', ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_wrong_codes_are_rejected_and_lock_the_challenge(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'jane@example.com']);

        $this->post('/login', ['email' => 'jane@example.com']);
        $code = $this->otpCodeFor('jane@example.com');
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->post('/auth/verify', ['code' => $wrong])
                ->assertSessionHasErrors('code');
        }

        $this->post('/auth/verify', ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertDatabaseHas('email_otps', ['email' => 'jane@example.com', 'attempts' => 5]);
    }

    public function test_expired_codes_are_rejected(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'jane@example.com']);

        $this->post('/login', ['email' => 'jane@example.com']);
        $code = $this->otpCodeFor('jane@example.com');

        $this->travel(11)->minutes();

        $this->post('/auth/verify', ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_guests_are_redirected_from_private_pages(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/findings')->assertRedirect('/login');
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_authenticated_application_pages_render(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (['/dashboard', '/identity', '/identity/create', '/scans', '/scans/create', '/findings', '/credentials', '/removals', '/monitoring', '/reports', '/settings'] as $path) {
            $this->get($path)->assertOk();
        }
    }
}
