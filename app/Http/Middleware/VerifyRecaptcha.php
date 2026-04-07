<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

final class VerifyRecaptcha
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
    private const MIN_SCORE = 0.5;

    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.recaptcha.secret');

        if (! is_string($secret) || $secret === '') {
            return $next($request);
        }

        $token = $request->input('recaptcha_token') ?? $request->header('X-Recaptcha-Token');

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'reCAPTCHA token required.'], 422);
        }

        $response = Http::asForm()->post(self::VERIFY_URL, [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);

        $body = $response->json();

        if (! ($body['success'] ?? false)) {
            return response()->json(['message' => 'reCAPTCHA verification failed.'], 422);
        }

        $score = (float) ($body['score'] ?? 0);

        if ($score < self::MIN_SCORE) {
            return response()->json(['message' => 'reCAPTCHA score too low.'], 422);
        }

        return $next($request);
    }
}
