<?php

namespace Tests\Feature;

use App\Support\ProductionSafetyGuard;
use RuntimeException;
use Tests\TestCase;

class ProductionSafetyGuardTest extends TestCase
{
    private string $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEnv = $this->app['env'];
    }

    protected function tearDown(): void
    {
        $this->app['env'] = $this->originalEnv;

        parent::tearDown();
    }

    public function test_non_production_environments_are_never_blocked(): void
    {
        $violations = ProductionSafetyGuard::violations([
            'production' => false,
            'debug' => true,
            'demo_mode' => true,
            'otp_demo_display' => true,
        ]);

        $this->assertSame([], $violations);
    }

    public function test_production_reports_every_unsafe_setting(): void
    {
        $violations = ProductionSafetyGuard::violations([
            'production' => true,
            'debug' => true,
            'demo_mode' => true,
            'otp_demo_display' => true,
        ]);

        $this->assertCount(3, $violations);
        $this->assertStringContainsString('APP_DEBUG', implode(' ', $violations));
        $this->assertStringContainsString('TRACELESS_DEMO_MODE', implode(' ', $violations));
        $this->assertStringContainsString('TRACELESS_OTP_DEMO_DISPLAY', implode(' ', $violations));
    }

    public function test_production_reports_only_the_offending_setting(): void
    {
        $violations = ProductionSafetyGuard::violations([
            'production' => true,
            'debug' => false,
            'demo_mode' => false,
            'otp_demo_display' => true,
        ]);

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('TRACELESS_OTP_DEMO_DISPLAY', $violations[0]);
    }

    public function test_string_false_is_not_treated_as_enabled(): void
    {
        $violations = ProductionSafetyGuard::violations([
            'production' => true,
            'debug' => 'false',
            'demo_mode' => '0',
            'otp_demo_display' => '',
        ]);

        $this->assertSame([], $violations);
    }

    public function test_strict_production_configuration_is_accepted(): void
    {
        $this->app['env'] = 'production';

        config([
            'app.debug' => false,
            'traceless.demo_mode' => false,
            'traceless.otp.demo_display' => false,
        ]);

        (new ProductionSafetyGuard)->enforce();

        $this->addToAssertionCount(1);
    }

    public function test_production_with_demo_mode_refuses_to_boot(): void
    {
        $this->app['env'] = 'production';

        config([
            'app.debug' => false,
            'traceless.demo_mode' => true,
            'traceless.otp.demo_display' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TRACELESS_DEMO_MODE');

        (new ProductionSafetyGuard)->enforce();
    }

    public function test_production_with_on_screen_codes_refuses_to_boot(): void
    {
        $this->app['env'] = 'production';

        config([
            'app.debug' => false,
            'traceless.demo_mode' => false,
            'traceless.otp.demo_display' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TRACELESS_OTP_DEMO_DISPLAY');

        (new ProductionSafetyGuard)->enforce();
    }

    public function test_testing_and_local_environments_boot_normally(): void
    {
        config([
            'app.debug' => true,
            'traceless.demo_mode' => true,
            'traceless.otp.demo_display' => true,
        ]);

        (new ProductionSafetyGuard)->enforce();

        $this->addToAssertionCount(1);
    }

    public function test_the_design_preview_route_is_gone(): void
    {
        $this->assertFalse(
            collect(app('router')->getRoutes())->contains(
                fn ($route) => $route->uri() === '__preview/dashboard'
            ),
            'The /__preview/dashboard auto-login route must not be registered.'
        );
    }
}
