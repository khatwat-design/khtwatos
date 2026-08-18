<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCalendlyWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CalendlyWebhookController extends Controller
{
    public function store(Request $request): SymfonyResponse
    {
        $rawBody = $request->getContent();
        $signingKey = config('services.calendly.webhook_signing_key');

        if (is_string($signingKey) && $signingKey !== '') {
            $header = $request->header('Calendly-Webhook-Signature');
            if (! $this->signatureValid($header, $rawBody, $signingKey)) {
                return response('Invalid signature', Response::HTTP_UNAUTHORIZED);
            }
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response('Invalid JSON', Response::HTTP_BAD_REQUEST);
        }

        ProcessCalendlyWebhook::dispatchSync($payload);

        return response()->noContent();
    }

    private function signatureValid(?string $signatureHeader, string $body, string $secret): bool
    {
        if (! $signatureHeader) {
            return false;
        }

        $timestamp = null;
        $signature = null;
        foreach (explode(',', $signatureHeader) as $part) {
            $part = trim($part);
            if (! str_contains($part, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $part, 2);
            if ($key === 't') {
                $timestamp = $value;
            }
            if ($key === 'v1') {
                $signature = $value;
            }
        }

        if ($timestamp === null || $signature === null) {
            return false;
        }

        $computed = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        return hash_equals($computed, $signature);
    }
}
