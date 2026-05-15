<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Support\OperationalAutomationRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hidden admin shell for future automation control center (Phase 5).
 * Not linked from public navigation.
 */
final class OperationalAutomationFoundationController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }

        return Inertia::render('Internal/OperationalAutomationFoundation', [
            'catalog' => OperationalAutomationRegistry::catalog(),
        ]);
    }
}
