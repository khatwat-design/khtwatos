<?php

namespace Tests\Unit;

use App\Services\ChatMentionService;
use PHPUnit\Framework\TestCase;

class ChatMentionServiceTest extends TestCase
{
    public function test_extracts_usernames_from_body(): void
    {
        $service = new ChatMentionService(app(\App\Services\TeamChatMemberService::class));

        $usernames = $service->extractUsernames('مرحبا @ahmed.bashir و @hussein');

        $this->assertSame(['ahmed.bashir', 'hussein'], $usernames);
    }

    public function test_resolves_mentioned_user_ids_from_allowed_list(): void
    {
        $service = new ChatMentionService(app(\App\Services\TeamChatMemberService::class));

        $ids = $service->resolveMentionedUserIds('شكرا @ahmed', [
            ['id' => 5, 'username' => 'ahmed'],
            ['id' => 9, 'username' => 'noor'],
        ]);

        $this->assertSame([5], $ids);
    }
}
