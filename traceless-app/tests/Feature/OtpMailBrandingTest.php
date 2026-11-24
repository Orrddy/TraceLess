<?php

namespace Tests\Feature;

use App\Mail\OtpCodeMail;
use App\Models\EmailOtp;
use Tests\TestCase;

class OtpMailBrandingTest extends TestCase
{
    public function test_the_sign_in_email_includes_the_brand_image(): void
    {
        $html = (new OtpCodeMail('123456', EmailOtp::PURPOSE_LOGIN, 10))->render();

        $this->assertStringContainsString('apple-touch-icon.png', $html);
        $this->assertStringContainsString('alt="TraceLess"', $html);
        $this->assertStringContainsString('123456', $html);
    }
}
