<?php

namespace Tests\Unit;

use App\Support\WebRtcSdp;
use PHPUnit\Framework\TestCase;

class WebRtcSdpTest extends TestCase
{
    public function test_normalizes_nested_session_description(): void
    {
        $inner = [
            'type' => 'offer',
            'sdp' => "v=0\r\no=- 0 0 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\nm=audio 9 UDP/TLS/RTP/SAVPF 111\r\n",
        ];

        $result = WebRtcSdp::normalize(['sdp' => $inner]);

        $this->assertNotNull($result);
        $this->assertSame('offer', $result['type']);
        $this->assertTrue(WebRtcSdp::isValid($result['sdp']));
    }

    public function test_repairs_literal_newline_escapes(): void
    {
        $escaped = 'v=0\\no=- 0 0 IN IP4 127.0.0.1\\ns=-\\nt=0 0\\nm=audio 9 UDP/TLS/RTP/SAVPF 111\\n';
        $result = WebRtcSdp::normalize(['type' => 'offer', 'sdp' => $escaped]);

        $this->assertNotNull($result);
        $this->assertStringContainsString("\nm=audio", $result['sdp']);
    }
}
