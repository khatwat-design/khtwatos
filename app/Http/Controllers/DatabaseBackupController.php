<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DatabaseBackupController extends Controller
{
    public function store(Request $request, DatabaseBackupService $backups): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-system-settings'), 403);

        try {
            $filename = $backups->createBackup();
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('settings.index', ['tab' => 'backups'])
                ->with('error', 'فشل إنشاء النسخة: '.$e->getMessage());
        }

        return redirect()
            ->route('settings.index', ['tab' => 'backups'])
            ->with('success', 'تم إنشاء نسخة احتياطية: '.$filename);
    }

    public function destroy(Request $request, string $filename, DatabaseBackupService $backups): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-system-settings'), 403);

        $backups->deleteBackup($filename);

        return redirect()
            ->route('settings.index', ['tab' => 'backups'])
            ->with('success', 'تم حذف ملف النسخة.');
    }

    public function download(Request $request, string $filename, DatabaseBackupService $backups): BinaryFileResponse
    {
        abort_unless($request->user()?->can('manage-system-settings'), 403);

        $path = $backups->absolutePathOr404($filename);

        return response()->download($path, $filename);
    }
}
