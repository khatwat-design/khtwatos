<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SystemEventNotification extends Notification
{
    use Queueable;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(private readonly array $payload)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'system_event',
            'title' => (string) ($this->payload['title'] ?? 'إشعار جديد'),
            'body' => (string) ($this->payload['body'] ?? ''),
            'severity' => (string) ($this->payload['severity'] ?? 'info'),
            'category' => (string) ($this->payload['category'] ?? 'general'),
            'link' => $this->payload['link'] ?? null,
            'meta' => is_array($this->payload['meta'] ?? null) ? $this->payload['meta'] : [],
        ];
    }
}

