<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = $request->header('X-Webhook-Secret');
        $expectedSecret = config('services.webhook_secret');

        if (! $secret || ! $expectedSecret || ! hash_equals((string) $expectedSecret, (string) $secret)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
