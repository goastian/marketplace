<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class DetectLocale
{
    private const SUPPORTED = ['en', 'es'];
    private const DEFAULT = 'en';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        if (is_string($locale) && in_array($locale, self::SUPPORTED, true)) {
            App::setLocale($locale);
        } else {
            $preferred = $request->getPreferredLanguage(self::SUPPORTED);
            App::setLocale($preferred ?? self::DEFAULT);
        }

        return $next($request);
    }
}
