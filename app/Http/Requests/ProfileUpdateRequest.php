<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $user = $this->user();
        if (!$user) {
            return;
        }

        $defaultStart = $user->availability_start_time ? substr($user->availability_start_time, 0, 5) : '09:00';
        $defaultEnd = $user->availability_end_time ? substr($user->availability_end_time, 0, 5) : '17:00';
        $days = $user->availability_days ?: [0, 1, 2, 3, 4];
        $existingSchedule = is_array($user->availability_schedule) ? $user->availability_schedule : [];
        $incoming = $this->input('availability_schedule');
        $incomingSchedule = is_array($incoming) ? $incoming : [];
        $schedule = [];

        for ($day = 0; $day <= 6; $day++) {
            $current = Arr::get($existingSchedule, (string) $day, []);
            $posted = Arr::get($incomingSchedule, $day, []);
            $enabled = array_key_exists('enabled', $posted)
                ? filter_var($posted['enabled'], FILTER_VALIDATE_BOOLEAN)
                : (bool) ($current['enabled'] ?? in_array($day, $days, true));
            $start = $posted['start'] ?? $current['start'] ?? ($enabled ? $defaultStart : null);
            $end = $posted['end'] ?? $current['end'] ?? ($enabled ? $defaultEnd : null);

            $schedule[] = [
                'day' => $day,
                'enabled' => $enabled,
                'start' => $enabled ? (is_string($start) ? substr($start, 0, 5) : null) : null,
                'end' => $enabled ? (is_string($end) ? substr($end, 0, 5) : null) : null,
            ];
        }

        $this->merge([
            'availability_schedule' => $schedule,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(User::class, 'name')->ignore($this->user()->id),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'is_bookable' => ['sometimes', 'boolean'],
            'availability_schedule' => ['required', 'array', 'size:7'],
            'availability_schedule.*.day' => ['required', 'integer', 'between:0,6'],
            'availability_schedule.*.enabled' => ['required', 'boolean'],
            'availability_schedule.*.start' => ['nullable', 'date_format:H:i'],
            'availability_schedule.*.end' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        /** @var array<string, mixed> $data */
        $data = parent::validated($key, $default);
        /** @var array<int, array<string, mixed>> $scheduleRows */
        $scheduleRows = $data['availability_schedule'] ?? [];

        $scheduleByDay = [];
        $enabledDays = [];
        $firstStart = null;
        $firstEnd = null;

        foreach ($scheduleRows as $row) {
            $day = (int) ($row['day'] ?? -1);
            if ($day < 0 || $day > 6) {
                continue;
            }

            $enabled = (bool) ($row['enabled'] ?? false);
            $start = $enabled ? ($row['start'] ?? null) : null;
            $end = $enabled ? ($row['end'] ?? null) : null;

            if ($enabled) {
                if (!$start || !$end) {
                    throw ValidationException::withMessages([
                        "availability_schedule.$day.start" => 'حدّد وقت البداية والنهاية لهذا اليوم.',
                    ]);
                }

                if ($start >= $end) {
                    throw ValidationException::withMessages([
                        "availability_schedule.$day.end" => 'وقت النهاية يجب أن يكون بعد وقت البداية.',
                    ]);
                }

                $enabledDays[] = $day;
                $firstStart ??= $start;
                $firstEnd ??= $end;
            }

            $scheduleByDay[(string) $day] = [
                'enabled' => $enabled,
                'start' => $start,
                'end' => $end,
            ];
        }

        if (!count($enabledDays)) {
            throw ValidationException::withMessages([
                'availability_schedule' => 'يجب اختيار يوم توفر واحد على الأقل.',
            ]);
        }

        $data['availability_schedule'] = $scheduleByDay;
        $data['availability_days'] = array_values(array_unique($enabledDays));
        $data['availability_start_time'] = $firstStart;
        $data['availability_end_time'] = $firstEnd;

        return $data;
    }
}
