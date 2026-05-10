<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class DatabaseBackupService
{
    public function backupDirectory(): string
    {
        $relative = trim((string) config('database_backup.directory', 'backups'), '/');
        $path = storage_path('app/'.$relative);

        if (! is_dir($path)) {
            File::makeDirectory($path, 0750, true);
        }

        return $path;
    }

    /**
     * @return list<array{filename: string, size_bytes: int, created_at: string, kind: string, encrypted: bool}>
     */
    public function listMeta(): array
    {
        $items = [];

        if (! is_dir($this->backupDirectory())) {
            return [];
        }

        foreach (File::files($this->backupDirectory()) as $file) {
            $filename = $file->getFilename();
            if (! $this->isBackupFilename($filename)) {
                continue;
            }

            $path = $file->getPathname();
            $mtime = @filemtime($path) ?: time();

            $items[] = [
                'filename' => $filename,
                'size_bytes' => (int) (@filesize($path) ?: 0),
                'created_at' => date('Y-m-d H:i:s', $mtime),
                'kind' => str_contains($filename, '-full.') ? 'full' : 'database_only',
                'encrypted' => str_ends_with($filename, '.tar.gz.enc'),
            ];
        }

        usort($items, fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function inertiaPayload(): array
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $encrypt = (bool) config('database_backup.encrypt', false);
        $password = (string) (config('database_backup.encryption_password') ?? '');

        return [
            'files' => $this->listMeta(),
            'connection' => $connection,
            'driver' => $driver,
            'mode' => (string) config('database_backup.mode', 'full'),
            'keep_max_files' => (int) config('database_backup.keep_max_files', 30),
            'compress' => (bool) config('database_backup.compress', true),
            'encrypt' => $encrypt,
            'encryption_configured' => ! $encrypt || strlen($password) >= 16,
            'openssl_iterations' => (int) config('database_backup.openssl_pbkdf2_iterations', 600000),
            'include_public_storage' => (bool) config('database_backup.include_public_storage', true),
            'include_private_storage' => (bool) config('database_backup.include_private_storage', true),
            'schedule_enabled' => (bool) config('database_backup.schedule_enabled', false),
            'schedule_at' => (string) config('database_backup.schedule_at', '03:30'),
            'storage_hint' => 'storage/app/'.trim((string) config('database_backup.directory', 'backups'), '/'),
            'github_push_enabled' => (bool) config('database_backup.github_push_enabled', false),
            'github_configured' => $this->githubBackupConfigured(),
            'github_repo_label' => $this->githubRepoLabel(),
            'github_branch' => (string) config('database_backup.github_branch', 'main'),
            'github_subpath' => trim((string) config('database_backup.github_subpath', 'backups'), '/') ?: 'backups',
            'github_clone_hint' => $this->githubCloneRelativeHint(),
            'delete_local_after_github_push' => (bool) config('database_backup.delete_local_after_github_push', false),
        ];
    }

    public function assertSafeFilename(string $filename): void
    {
        if (! $this->isBackupFilename($filename)) {
            abort(404);
        }
    }

    public function absolutePathOr404(string $filename): string
    {
        $this->assertSafeFilename($filename);

        $dir = realpath($this->backupDirectory());
        if ($dir === false) {
            abort(404);
        }

        $full = realpath($dir.DIRECTORY_SEPARATOR.$filename);

        if ($full === false || ! str_starts_with($full, $dir.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        return $full;
    }

    public function deleteBackup(string $filename): bool
    {
        $path = $this->absolutePathOr404($filename);

        return @unlink($path);
    }

    /**
     * @throws RuntimeException
     */
    public function createBackup(): string
    {
        $mode = (string) config('database_backup.mode', 'full');

        $finalPath = $mode === 'database_only'
            ? $this->createDatabaseOnlyBackup()
            : $this->createFullBundleBackup();

        $this->applyRetention();

        $pushed = false;

        try {
            $pushed = app(BackupGithubPushService::class)->pushBackupFile($finalPath);
        } catch (\Throwable $e) {
            report($e);
            Log::error('GitHub backup push failed', ['exception' => $e]);
        }

        if ($pushed && config('database_backup.delete_local_after_github_push')) {
            @unlink($finalPath);
        }

        return basename($finalPath);
    }

    public function applyRetention(): void
    {
        $keep = max(1, (int) config('database_backup.keep_max_files', 30));
        $paths = $this->sortedBackupPaths();

        foreach (array_slice($paths, $keep) as $path) {
            @unlink($path);
        }
    }

    /**
     * @throws RuntimeException
     */
    private function createFullBundleBackup(): string
    {
        $encrypt = (bool) config('database_backup.encrypt', false);
        $password = (string) (config('database_backup.encryption_password') ?? '');

        if ($encrypt && strlen($password) < 16) {
            throw new RuntimeException(
                'التشفير مفعّل (BACKUP_ENCRYPT) لكن BACKUP_ENCRYPTION_PASSWORD غير مضبوط أو أقصر من 16 حرفاً.'
            );
        }

        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $basename = $this->makeBasename($connection).'-full';
        $backupRoot = $this->backupDirectory();

        $stagingBase = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-sys-backup-'.bin2hex(random_bytes(8));
        $staging = $stagingBase.DIRECTORY_SEPARATOR.'bundle';

        File::makeDirectory($staging.DIRECTORY_SEPARATOR.'01_database', 0750, true);

        try {
            $dbDumpPath = $staging.DIRECTORY_SEPARATOR.'01_database'.DIRECTORY_SEPARATOR.'database.sql';

            match ($driver) {
                'mysql', 'mariadb' => $this->dumpMysqlToPath($connection, $dbDumpPath),
                'sqlite' => $this->dumpSqliteToPath($connection, $dbDumpPath),
                default => throw new RuntimeException("نوع قاعدة البيانات غير مدعوم للنسخ الاحتياطي: {$driver}"),
            };

            if ((bool) config('database_backup.compress', true)) {
                $dbDumpPath = $this->compressToGzipAndRemoveSource($dbDumpPath);
            }

            if ((bool) config('database_backup.include_public_storage', true)) {
                $to = $staging.DIRECTORY_SEPARATOR.'02_storage_public';
                File::makeDirectory($to, 0750, true);
                $from = storage_path('app/public');
                if (is_dir($from)) {
                    File::copyDirectory($from, $to);
                }
            }

            if ((bool) config('database_backup.include_private_storage', true)) {
                $to = $staging.DIRECTORY_SEPARATOR.'03_storage_private';
                File::makeDirectory($to, 0750, true);
                $from = storage_path('app/private');
                if (is_dir($from)) {
                    File::copyDirectory($from, $to);
                }
            }

            $manifest = [
                'schema_version' => 1,
                'created_at' => now()->toIso8601String(),
                'app_name' => config('app.name'),
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
                'timezone' => config('app.timezone'),
                'backup_mode' => 'full',
                'database' => [
                    'connection' => $connection,
                    'driver' => $driver,
                    'dump_relative_path' => '01_database/'.basename($dbDumpPath),
                ],
                'storage_relative_paths' => array_values(array_filter([
                    is_dir($staging.DIRECTORY_SEPARATOR.'02_storage_public') ? '02_storage_public' : null,
                    is_dir($staging.DIRECTORY_SEPARATOR.'03_storage_private') ? '03_storage_private' : null,
                ])),
                'encrypted_outer' => $encrypt,
                'cipher' => 'aes-256-cbc',
                'kdf' => 'PBKDF2',
                'pbkdf2_iterations' => (int) config('database_backup.openssl_pbkdf2_iterations', 600000),
            ];

            File::put(
                $staging.DIRECTORY_SEPARATOR.'manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n"
            );

            $tarPath = $backupRoot.DIRECTORY_SEPARATOR.$basename.'.tar.gz';
            $tarBin = (string) config('database_backup.tar_path', 'tar');

            $tar = new Process([$tarBin, '-czf', $tarPath, '-C', $staging, '.']);
            $tar->setTimeout(7200);

            try {
                $tar->mustRun();
            } catch (ProcessFailedException $e) {
                throw new RuntimeException(
                    'فشل إنشاء الأرشيف (tar): '.trim($e->getProcess()->getErrorOutput()),
                    0,
                    $e
                );
            }

            if (! $encrypt) {
                return $tarPath;
            }

            $encPath = $tarPath.'.enc';

            try {
                $this->encryptTarGzWithOpenssl($tarPath, $encPath, $password);
            } catch (\Throwable $e) {
                @unlink($encPath);
                @unlink($tarPath);

                throw $e;
            }

            @unlink($tarPath);

            return $encPath;
        } finally {
            File::deleteDirectory($stagingBase);
        }
    }

    /**
     * @throws RuntimeException
     */
    private function encryptTarGzWithOpenssl(string $plainPath, string $cipherPath, string $password): void
    {
        $openssl = (string) config('database_backup.openssl_path', 'openssl');
        $iterations = (int) config('database_backup.openssl_pbkdf2_iterations', 600000);

        $process = new Process([
            $openssl, 'enc', '-aes-256-cbc', '-salt', '-pbkdf2',
            '-iter', (string) $iterations,
            '-pass', 'env:LARAVEL_BACKUP_ENC_PASS',
            '-in', $plainPath,
            '-out', $cipherPath,
        ]);
        $process->setTimeout(7200);
        $process->setEnv(['LARAVEL_BACKUP_ENC_PASS' => $password]);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new RuntimeException(
                'فشل تشفير النسخة (openssl): '.trim($e->getProcess()->getErrorOutput()),
                0,
                $e
            );
        }
    }

    /**
     * @throws RuntimeException
     */
    private function dumpMysqlToPath(string $connection, string $sqlPath): void
    {
        $cfg = config("database.connections.{$connection}");
        if (! is_array($cfg)) {
            throw new RuntimeException('إعدادات اتصال القاعدة غير صالحة.');
        }

        $database = (string) ($cfg['database'] ?? '');
        if ($database === '') {
            throw new RuntimeException('اسم قاعدة البيانات فارغ.');
        }

        $defaultsFile = tempnam(sys_get_temp_dir(), 'laravel-db-backup-');
        if ($defaultsFile === false) {
            throw new RuntimeException('تعذّر إنشاء ملف إعدادات مؤقت لـ mysqldump.');
        }

        try {
            $ini = "[client]\n";
            $ini .= 'host='.($cfg['host'] ?? '127.0.0.1')."\n";
            $ini .= 'port='.(string) ($cfg['port'] ?? '3306')."\n";
            $ini .= 'user='.($cfg['username'] ?? 'root')."\n";
            $ini .= 'password='.($cfg['password'] ?? '')."\n";

            $socket = $cfg['unix_socket'] ?? '';
            if (is_string($socket) && $socket !== '') {
                $ini .= 'socket='.$socket."\n";
            }

            File::put($defaultsFile, $ini);
            @chmod($defaultsFile, 0600);

            $mysqldump = (string) config('database_backup.mysqldump_path', 'mysqldump');

            $bashCmd = sprintf(
                '%s --defaults-extra-file=%s --single-transaction --quick --skip-lock-tables --no-tablespaces %s > %s',
                escapeshellcmd($mysqldump),
                escapeshellarg($defaultsFile),
                escapeshellarg($database),
                escapeshellarg($sqlPath)
            );

            $process = new Process(['bash', '-c', $bashCmd]);
            $process->setTimeout(7200);

            try {
                $process->mustRun();
            } catch (ProcessFailedException $e) {
                $err = trim($e->getProcess()->getErrorOutput()."\n".$e->getProcess()->getOutput());

                throw new RuntimeException(
                    $err !== '' ? $err : 'فشل mysqldump. تأكد أن الأداة متوفرة على الخادم.',
                    0,
                    $e
                );
            }
        } finally {
            @unlink($defaultsFile);
        }

        if (! is_file($sqlPath) || filesize($sqlPath) === 0) {
            throw new RuntimeException('مخرجات mysqldump فارغة أو ملف غير مُنشأ.');
        }
    }

    /**
     * @throws RuntimeException
     */
    private function dumpSqliteToPath(string $connection, string $sqlPath): void
    {
        $cfg = config("database.connections.{$connection}");
        if (! is_array($cfg)) {
            throw new RuntimeException('إعدادات اتصال القاعدة غير صالحة.');
        }

        $database = $cfg['database'] ?? '';
        if ($database === ':memory:') {
            throw new RuntimeException('لا يمكن أخذ نسخة احتياطية لقاعدة SQLite في الذاكرة.');
        }

        $path = is_string($database) && ! str_starts_with($database, DIRECTORY_SEPARATOR)
            ? database_path($database)
            : (string) $database;

        if (! is_file($path)) {
            throw new RuntimeException('ملف SQLite غير موجود: '.$path);
        }

        $sqlite3 = (string) config('database_backup.sqlite3_path', 'sqlite3');

        $dump = new Process([$sqlite3, $path, '.dump']);
        $dump->setTimeout(7200);

        try {
            $dump->mustRun();
            File::put($sqlPath, $dump->getOutput());
        } catch (ProcessFailedException) {
            File::copy($path, $sqlPath);
        }

        if (! is_file($sqlPath) || filesize($sqlPath) === 0) {
            throw new RuntimeException('فشل تصدير SQLite.');
        }
    }

    /**
     * @throws RuntimeException
     */
    private function createDatabaseOnlyBackup(): string
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        $basename = $this->makeBasename($connection);

        return match ($driver) {
            'mysql', 'mariadb' => $this->backupMysqlFamilyLegacy($connection, $basename),
            'sqlite' => $this->backupSqliteLegacy($connection, $basename),
            default => throw new RuntimeException("نوع قاعدة البيانات غير مدعوم للنسخ الاحتياطي: {$driver}"),
        };
    }

    /**
     * @throws RuntimeException
     */
    private function backupMysqlFamilyLegacy(string $connection, string $basename): string
    {
        $dir = $this->backupDirectory();
        $sqlPath = $dir.DIRECTORY_SEPARATOR.$basename.'.sql';

        $this->dumpMysqlToPath($connection, $sqlPath);

        if ((bool) config('database_backup.compress', true)) {
            return $this->compressToGzipAndRemoveSource($sqlPath);
        }

        return $sqlPath;
    }

    /**
     * @throws RuntimeException
     */
    private function backupSqliteLegacy(string $connection, string $basename): string
    {
        $cfg = config("database.connections.{$connection}");
        if (! is_array($cfg)) {
            throw new RuntimeException('إعدادات اتصال القاعدة غير صالحة.');
        }

        $database = $cfg['database'] ?? '';
        if ($database === ':memory:') {
            throw new RuntimeException('لا يمكن أخذ نسخة احتياطية لقاعدة SQLite في الذاكرة.');
        }

        $path = is_string($database) && ! str_starts_with($database, DIRECTORY_SEPARATOR)
            ? database_path($database)
            : (string) $database;

        if (! is_file($path)) {
            throw new RuntimeException('ملف SQLite غير موجود: '.$path);
        }

        $dir = $this->backupDirectory();
        $sqlPath = $dir.DIRECTORY_SEPARATOR.$basename.'.sql';

        $sqlite3 = (string) config('database_backup.sqlite3_path', 'sqlite3');

        $dump = new Process([$sqlite3, $path, '.dump']);
        $dump->setTimeout(7200);

        try {
            $dump->mustRun();
            $out = $dump->getOutput();
            if ($out === '') {
                throw new RuntimeException('SQLite dump empty.');
            }
            File::put($sqlPath, $out);

            if ((bool) config('database_backup.compress', true)) {
                return $this->compressToGzipAndRemoveSource($sqlPath);
            }

            return $sqlPath;
        } catch (ProcessFailedException|RuntimeException) {
            $binaryPath = $dir.DIRECTORY_SEPARATOR.$basename.'.sqlite';
            File::copy($path, $binaryPath);
            if ((bool) config('database_backup.compress', true)) {
                return $this->compressBinaryFileToGzip($binaryPath, $dir.DIRECTORY_SEPARATOR.$basename.'.sqlite.gz');
            }

            return $binaryPath;
        }
    }

    /**
     * @throws RuntimeException
     */
    private function compressToGzipAndRemoveSource(string $sqlPath): string
    {
        $gzPath = $sqlPath.'.gz';

        $in = fopen($sqlPath, 'rb');
        $gz = gzopen($gzPath, 'wb9');

        if ($in === false || $gz === false) {
            throw new RuntimeException('تعذّر ضغط ملف النسخة الاحتياطية.');
        }

        while (! feof($in)) {
            $chunk = fread($in, 1024 * 1024);
            if ($chunk === false) {
                break;
            }
            gzwrite($gz, $chunk);
        }

        fclose($in);
        gzclose($gz);
        unlink($sqlPath);

        return $gzPath;
    }

    /**
     * @throws RuntimeException
     */
    private function compressBinaryFileToGzip(string $sourcePath, string $targetGzPath): string
    {
        $in = fopen($sourcePath, 'rb');
        $gz = gzopen($targetGzPath, 'wb9');

        if ($in === false || $gz === false) {
            throw new RuntimeException('تعذّر ضغط نسخة SQLite.');
        }

        while (! feof($in)) {
            $chunk = fread($in, 1024 * 1024);
            if ($chunk === false) {
                break;
            }
            gzwrite($gz, $chunk);
        }

        fclose($in);
        gzclose($gz);
        unlink($sourcePath);

        return $targetGzPath;
    }

    private function makeBasename(string $connection): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $connection) ?: 'db';
        $slug = substr($slug, 0, 36);
        $entropy = bin2hex(random_bytes(3));

        return 'backup-'.date('YmdHis').'-'.$slug.'-'.$entropy;
    }

    private function isBackupFilename(string $filename): bool
    {
        return (bool) preg_match(
            '/^backup-\d{14}-[a-zA-Z0-9_-]+(?:-[a-f0-9]{6})?(?:-full\.tar\.gz(?:\.enc)?|\.sql\.gz|\.sql|\.sqlite\.gz|\.sqlite)$/',
            $filename
        );
    }

    /**
     * @return list<string>
     */
    private function sortedBackupPaths(): array
    {
        $paths = [];

        if (! is_dir($this->backupDirectory())) {
            return [];
        }

        foreach (File::files($this->backupDirectory()) as $file) {
            if ($this->isBackupFilename($file->getFilename())) {
                $paths[] = $file->getPathname();
            }
        }

        usort($paths, fn (string $a, string $b): int => (@filemtime($b) ?: 0) <=> (@filemtime($a) ?: 0));

        return $paths;
    }

    private function githubBackupConfigured(): bool
    {
        if (! config('database_backup.github_push_enabled')) {
            return false;
        }

        $token = trim((string) config('database_backup.github_token'));
        $owner = trim((string) config('database_backup.github_owner'));
        $repo = trim((string) config('database_backup.github_repo'));

        return $token !== '' && strlen($token) >= 8 && $owner !== '' && $repo !== '';
    }

    private function githubRepoLabel(): string
    {
        $owner = trim((string) config('database_backup.github_owner'));
        $repo = trim((string) config('database_backup.github_repo'));

        if ($owner === '' || $repo === '') {
            return '';
        }

        return $owner.'/'.$repo;
    }

    private function githubCloneRelativeHint(): string
    {
        $configuredWd = config('database_backup.github_workdir');

        if (is_string($configuredWd) && trim($configuredWd) !== '') {
            return trim(str_replace('\\', '/', $configuredWd));
        }

        return 'storage/app/.backup-github-remote';
    }
}
