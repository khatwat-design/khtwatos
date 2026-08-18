<?php

namespace App\Http\Controllers;

use App\Services\ProductTourService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductTourController extends Controller
{
    public function __construct(
        private readonly ProductTourService $productTours,
    ) {}

    public function complete(Request $request, string $tour): JsonResponse
    {
        abort_unless($this->productTours->isEnabled(), 404);

        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($this->productTours->userCanAccessTour($user, $tour, $request), 404);

        $this->productTours->markFinished($user, $tour, 'completed');

        return response()->json([
            'product_tours' => $this->productTours->payloadForUser($user, $request),
        ]);
    }

    public function skip(Request $request, string $tour): JsonResponse
    {
        abort_unless($this->productTours->isEnabled(), 404);

        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($this->productTours->userCanAccessTour($user, $tour, $request), 404);

        $this->productTours->markFinished($user, $tour, 'skipped');

        return response()->json([
            'product_tours' => $this->productTours->payloadForUser($user, $request),
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        abort_unless($this->productTours->isEnabled(), 404);

        $user = $request->user();
        abort_unless($user, 403);

        $this->productTours->resetAllForUser($user);

        return response()->json([
            'product_tours' => $this->productTours->payloadForUser($user, $request),
        ]);
    }

    public function restart(Request $request, string $tour): JsonResponse
    {
        abort_unless($this->productTours->isEnabled(), 404);

        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($this->productTours->userCanAccessTour($user, $tour, $request), 404);

        $this->productTours->restartTour($user, $tour);

        return response()->json([
            'product_tours' => $this->productTours->payloadForUser($user, $request),
        ]);
    }
}
