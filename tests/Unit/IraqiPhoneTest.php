<?php

namespace Tests\Unit;

use App\Support\IraqiPhone;
use PHPUnit\Framework\TestCase;

class IraqiPhoneTest extends TestCase
{
    public function test_detects_iraqi_mobile(): void
    {
        $this->assertTrue(IraqiPhone::isLikelyMobile('p:+9647824462427'));
        $this->assertTrue(IraqiPhone::isLikelyMobile('07824462427'));
        $this->assertFalse(IraqiPhone::isLikelyMobile(''));
        $this->assertFalse(IraqiPhone::isLikelyMobile('123'));
    }

    public function test_whatsapp_url_includes_message(): void
    {
        $url = IraqiPhone::whatsAppUrl('07824462427', 'مرحبا');
        $this->assertStringStartsWith('https://wa.me/9647824462427', $url);
        $this->assertStringContainsString('text=', $url);
        $this->assertStringContainsString(rawurlencode('مرحبا'), $url);
    }

    public function test_whatsapp_business_url_uses_scheme(): void
    {
        $url = IraqiPhone::whatsAppBusinessUrl('07824462427', 'مرحبا');
        $this->assertStringStartsWith('whatsapp://send?phone=9647824462427', $url);
        $this->assertStringContainsString(rawurlencode('مرحبا'), $url);
    }
}
