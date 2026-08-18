<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\ArabCountryDialCodes;
use App\Support\EmployeeOutsideContactSync;
use Illuminate\Console\Command;

class AssignEmployeePhonesFromCsvCommand extends Command
{
    protected $signature = 'employees:assign-phones
                            {file : مسار ملف CSV على الخادم}
                            {--dry-run : عرض النتائج دون حفظ في قاعدة البيانات}
                            {--force : تحديث الأرقام حتى للموظفين الذين لديهم رقمًا بالفعل}';

    protected $description = 'تحديث أرقام واتساب للموظفين من CSV لمن لا يملكون حقل phone (أو للجميع مع --force)';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        if (! is_readable($path)) {
            $this->error('الملف غير موجود أو غير قابل للقراءة: '.$path);

            return self::FAILURE;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->error('تعذّر فتح الملف.');

            return self::FAILURE;
        }

        $allowedCc = ArabCountryDialCodes::options();
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $lineNumber = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            $row = array_map(static fn ($cell): string => trim((string) $cell), $row);
            while (count($row) > 0 && end($row) === '') {
                array_pop($row);
            }
            $row = array_values($row);
            if ($row === [] || ! array_filter($row, static fn (string $c): bool => $c !== '')) {
                continue;
            }

            if ($lineNumber === 1 && $this->looksLikeHeaderRow($row)) {
                continue;
            }

            if (count($row) === 2) {
                [$username, $phoneCol] = $row;
                $digitsFull = ArabCountryDialCodes::normalizeDigits($phoneCol);
                $merged = $this->digitsMustMatchArabPrefix($digitsFull);
                if ($merged === null) {
                    $this->warn("[{$lineNumber}] صيغة رقم غير صالحة أو لا يبدأ بمفتاح عربي معروف: {$username}");
                    $errors++;

                    continue;
                }
            } elseif (count($row) === 3) {
                $username = $row[0];
                $cc = trim($row[1]);
                $localRaw = $row[2];
                if (! isset($allowedCc[$cc])) {
                    $this->warn("[{$lineNumber}] مفتاح دولة غير مسموح: {$cc} ({$username})");
                    $errors++;

                    continue;
                }
                $localDigits = ltrim(ArabCountryDialCodes::normalizeDigits($localRaw), '0');
                if (strlen($localDigits) < 6 || strlen($localDigits) > 14) {
                    $this->warn("[{$lineNumber}] الرقم المحلي غير صالح: {$username}");
                    $errors++;

                    continue;
                }
                $merged = $cc.$localDigits;
                if (strlen($merged) > 15) {
                    $this->warn("[{$lineNumber}] الرقم الكامل طويل جدًا: {$username}");
                    $errors++;

                    continue;
                }
            } else {
                $this->warn("[{$lineNumber}] توقع عمودين (اسم_المستخدم،رقم_كامل) أو ثلاثة (اسم_المستخدم،مفتاح_الدولة،محلي). تُستبدل الفاصلة في CSV بالشرطة أو احصر الحقول بعلامات اقتباس.");
                $errors++;

                continue;
            }

            $username = strtolower(trim((string) $username));
            if ($username === '') {
                $errors++;

                continue;
            }

            $user = User::query()->where('username', $username)->first();
            if (! $user) {
                $this->warn("[{$lineNumber}] لا مستخدم بهذا الاسم: {$username}");
                $errors++;

                continue;
            }

            if ($user->phone !== null && $user->phone !== '' && ! $force) {
                $this->line("[{$lineNumber}] تخطي — {$username} لديه رقم مسبقًا (استخدم --force للاستبدال).");
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->info("[{$lineNumber}] (dry-run) {$username} ← {$merged}");
                $updated++;

                continue;
            }

            $user->forceFill(['phone' => $merged])->save();
            EmployeeOutsideContactSync::sync((int) $user->id, $merged, (string) $user->name);
            $this->info("[{$lineNumber}] تم تحديث {$username} ← {$merged}");
            $updated++;
        }

        fclose($handle);

        $this->newLine();
        $this->line("صفوف محدثة/معاينة: {$updated} — متروكة (كان هناك رقم أصلًا): {$skipped} — أخطاء: {$errors}");

        if ($dryRun) {
            $this->comment('وضع dry-run: لم يُحفظ شيء. أزل الخيار لتطبيق التغييرات.');
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<string>  $row
     */
    private function looksLikeHeaderRow(array $row): bool
    {
        $first = strtolower(trim((string) ($row[0] ?? '')));

        return in_array($first, ['username', 'اسم_المستخدم', 'user', 'المستخدم'], true);
    }

    private function digitsMustMatchArabPrefix(string $digits): ?string
    {
        if ($digits === '') {
            return null;
        }

        foreach (ArabCountryDialCodes::codesLongestFirst() as $code) {
            if (str_starts_with($digits, $code)) {
                $rest = substr($digits, strlen($code));
                $rest = ltrim($rest, '0');
                $full = $code.$rest;
                if (strlen($full) < 8 || strlen($full) > 15) {
                    return null;
                }

                return $full;
            }
        }

        return null;
    }
}
