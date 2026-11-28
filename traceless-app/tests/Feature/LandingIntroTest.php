<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingIntroTest extends TestCase
{
    public function test_landing_page_renders_the_first_visit_intro_gate(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('data-intro-gate', false)
            ->assertSee('media/intro.mp4', false)
            ->assertSee('traceless:intro-complete:v1', false)
            ->assertSee('intro-gate-pending', false)
            ->assertSee('data-intro-sound', false);
    }

    public function test_auth_pages_do_not_render_the_landing_intro_gate(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('data-intro-gate', false);
    }
}
