<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class ProcessCalendlyWebhook
{
    use Dispatchable, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        $event = Arr::get($this->payload, 'event');

        $scheduled = Arr::get($this->payload, 'payload.scheduled_event')
            ?? Arr::get($this->payload, 'payload.event');

        if (! is_array($scheduled)) {
            return;
        }

        $invitee = Arr::get($this->payload, 'payload.invitee');
        if (! is_array($invitee)) {
            $invitee = [];
        }

        $externalId = (string) (Arr::get($scheduled, 'uri') ?? Arr::get($scheduled, 'uuid') ?? '');

        if ($externalId === '') {
            return;
        }

        if ($event === 'invitee.canceled') {
            Meeting::query()->where('external_id', $externalId)->update(['status' => 'canceled']);

            return;
        }

        if ($event !== 'invitee.created') {
            return;
        }

        $start = Arr::get($scheduled, 'start_time');
        $end = Arr::get($scheduled, 'end_time');

        $hostEmail = Arr::get($scheduled, 'event_memberships.0.user_email')
            ?? Arr::get($this->payload, 'payload.event_type.owner');

        $host = null;
        if (is_string($hostEmail) && $hostEmail !== '') {
            $host = User::query()->where('email', $hostEmail)->first();
        }

        $inviteeEmail = Arr::get($invitee, 'email');
        $inviteeName = Arr::get($invitee, 'name');
        $reason = $this->extractReason($this->payload);

        $client = null;
        if (is_string($inviteeEmail) && $inviteeEmail !== '') {
            $client = Client::query()->where('email', $inviteeEmail)->first();
        }

        Meeting::query()->updateOrCreate(
            ['external_id' => $externalId],
            [
                'source' => 'calendly',
                'title' => Arr::get($scheduled, 'name'),
                'start_at' => $start,
                'end_at' => $end,
                'invitee_name' => is_string($inviteeName) ? $inviteeName : null,
                'invitee_email' => is_string($inviteeEmail) ? $inviteeEmail : null,
                'reason' => $reason,
                'status' => 'scheduled',
                'user_id' => $host?->id,
                'client_id' => $client?->id,
                'raw_payload' => $this->payload,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractReason(array $payload): ?string
    {
        $answers = Arr::get($payload, 'payload.questions_and_answers');
        if (! is_array($answers)) {
            return null;
        }

        foreach ($answers as $row) {
            if (! is_array($row)) {
                continue;
            }
            $question = Arr::get($row, 'question');
            $answer = Arr::get($row, 'answer');
            if (is_string($question) && str_contains(strtolower($question), 'reason') && is_string($answer)) {
                return $answer;
            }
        }

        $first = $answers[0] ?? null;
        if (is_array($first)) {
            $answer = Arr::get($first, 'answer');

            return is_string($answer) ? $answer : null;
        }

        return null;
    }
}
