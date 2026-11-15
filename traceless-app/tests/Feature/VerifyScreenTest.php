<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyScreenTest extends TestCase
{
    use RefreshDatabase;

    private function arriveAtVerifyScreen(string $email = 'person@example.test'): void
    {
        $this->post('/login', ['email' => $email])
            ->assertRedirect(route('auth.verify'));
    }

    public function test_the_code_is_never_rendered_on_screen(): void
    {
        $this->arriveAtVerifyScreen();

        $response = $this->get(route('auth.verify'));

        $response->assertOk();
        $response->assertDontSee('code shown locally');
        $response->assertDontSee('Demo mode');
    }

    public function test_the_plaintext_code_is_not_stored_in_the_session(): void
    {
        $this->arriveAtVerifyScreen();

        $this->get(route('auth.verify'))->assertOk();

        $this->assertNull(session('otp_demo_code'));
    }

    public function test_the_screen_auto_submits_once_six_digits_are_entered(): void
    {
        $this->arriveAtVerifyScreen();

        $this->get(route('auth.verify'))
            ->assertOk()
            ->assertSee('x-ref="verifyForm"', false)
            ->assertSee('requestSubmit()', false);
    }

    public function test_the_resend_button_runs_a_client_side_countdown(): void
    {
        $this->arriveAtVerifyScreen();

        $this->get(route('auth.verify'))
            ->assertOk()
            ->assertSee('setInterval', false)
            ->assertSee('x-text="left"', false);
    }

    public function test_the_verify_form_is_still_usable_without_javascript(): void
    {
        $this->arriveAtVerifyScreen();

        $this->get(route('auth.verify'))
            ->assertOk()
            ->assertSee('action="'.route('auth.verify.submit').'"', false)
            ->assertSee('name="code"', false)
            ->assertSee('type="submit"', false);
    }
}
