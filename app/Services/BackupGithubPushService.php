<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * نسخ ملف النسخة الاحتياطية إلى مستودع GitHub خاص عبر git push (يتطلب PAT وصلاحية المحتوى).
 */
final class BackupGithubPushService
{
    /**
     * @return bool true إذا اكتمل الدفع إلى GitHub بنجاح
     */
    public function pushBackupFile(string $absolutePath): bool
    {
        if (! config('database_backup.github_push_enabled')) {
            return false;
        }

        $token = trim((string) config('database_backup.github_token'));
        $owner = trim((string) config('database_backup.github_owner'));
        $repo = trim((string) config('database_backup.github_repo'));

        if ($token === '' || $owner === '' || $repo === '') {
            Log::warning('GitHub backup push skipped: set BACKUP_GITHUB_TOKEN, BACKUP_GITHUB_OWNER, BACKUP_GITHUB_REPO.');

            return false;
        }

        if (! is_file($absolutePath)) {
            return false;
        }

        $branch = (string) config('database_backup.github_branch', 'main');
        $subpath = trim((string) config('database_backup.github_subpath', 'backups'), '/');

        $configuredWd = config('database_backup.github_workdir');
        $workDir = is_string($configuredWd) && trim($configuredWd) !== ''
            ? rtrim($configuredWd, '/')
            : storage_path('app/.backup-github-remote');

        $filename = basename($absolutePath);

        $authenticatedUrl = sprintf(
            'https://x-access-token:%s@github.com/%s/%s.git',
            rawurlencode($token),
            rawurlencode($owner),
            rawurlencode($repo)
        );

        File::makeDirectory($workDir, 0750, true);

        $this->ensureRepositoryReady($workDir, $authenticatedUrl, $branch);

        $relativeFile = ($subpath !== '' ? $subpath.'/' : '').$filename;
        $destDir = $subpath !== '' ? $workDir.DIRECTORY_SEPARATOR.$subpath : $workDir;
        File::makeDirectory($destDir, 0750, true);

        File::copy($absolutePath, $destDir.DIRECTORY_SEPARATOR.$filename);

        $gitConfig = function (array $args): Process {
            return (new Process(array_merge(['git'], $args)))->setTimeout(120);
        };

        $gitConfig(['-C', $workDir, 'config', 'user.email', 'backup@noreply.local'])->mustRun();
        $gitConfig(['-C', $workDir, 'config', 'user.name', 'Khtwatos System Backup'])->mustRun();

        $gitConfig(['-C', $workDir, 'add', '--', $relativeFile])->mustRun();

        $status = new Process(['git', '-C', $workDir, 'status', '--porcelain']);
        $status->run();

        if (trim($status->getOutput()) === '') {
            return true;
        }

        $commit = new Process(['git', '-C', $workDir, 'commit', '-m', 'backup: '.$filename]);
        $commit->setTimeout(120);
        $commit->run();

        if (! $commit->isSuccessful()) {
            $combined = $commit->getErrorOutput().$commit->getOutput();
            if (str_contains(strtolower($combined), 'nothing to commit')) {
                return true;
            }

            throw new ProcessFailedException($commit);
        }

        (new Process(['git', '-C', $workDir, 'push', '-u', 'origin', $branch]))->setTimeout(600)->mustRun();

        return true;
    }

    /**
     * @throws RuntimeException
     */
    private function ensureRepositoryReady(string $workDir, string $authenticatedUrl, string $branch): void
    {
        if (File::exists($workDir.'/.git')) {
            (new Process(['git', '-C', $workDir, 'reset', '--hard']))->setTimeout(120)->run();

            $fetch = new Process(['git', '-C', $workDir, 'fetch', 'origin']);
            $fetch->setTimeout(300);
            $fetch->mustRun();

            $checkout = new Process(['git', '-C', $workDir, 'checkout', $branch]);
            $checkout->setTimeout(60);
            $checkout->run();

            if (! $checkout->isSuccessful()) {
                (new Process(['git', '-C', $workDir, 'checkout', '-b', $branch]))->setTimeout(60)->mustRun();
            }

            $pull = new Process(['git', '-C', $workDir, 'pull', '--rebase', 'origin', $branch]);
            $pull->setTimeout(300);
            $pull->run();

            return;
        }

        File::deleteDirectory($workDir);
        File::makeDirectory($workDir, 0750, true);

        $clone = new Process(
            ['git', 'clone', '--depth', '80', '--branch', $branch, $authenticatedUrl, '.'],
            $workDir
        );
        $clone->setTimeout(600);
        $clone->run();

        if ($clone->isSuccessful()) {
            return;
        }

        File::deleteDirectory($workDir);
        File::makeDirectory($workDir, 0750, true);

        try {
            (new Process(['git', 'init'], $workDir))->setTimeout(60)->mustRun();
            (new Process(['git', 'remote', 'add', 'origin', $authenticatedUrl], $workDir))->setTimeout(60)->mustRun();
            (new Process(['git', 'checkout', '-b', $branch], $workDir))->setTimeout(60)->mustRun();

            File::put(
                $workDir.DIRECTORY_SEPARATOR.'README.md',
                "# Automated backups\n\nيُدار هذا المستودع آلياً من خادم التطبيق. لا تُفعله عاماً.\n"
            );

            (new Process(['git', 'add', 'README.md'], $workDir))->setTimeout(60)->mustRun();
            (new Process(['git', 'commit', '-m', 'chore: init backup repository'], $workDir))->setTimeout(120)->mustRun();
            (new Process(['git', 'push', '-u', 'origin', $branch], $workDir))->setTimeout(600)->mustRun();
        } catch (ProcessFailedException $e) {
            throw new RuntimeException(
                'تعذّر تهيئة أو استنساخ مستودع النسخ على GitHub. أنشئ مستودعاً خاصاً (يفضّل مع README أول commit) وتحقق من الصلاحيات والفرع.',
                0,
                $e
            );
        }
    }
}
