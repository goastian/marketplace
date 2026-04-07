<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ExtensionController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Sitemap (no locale prefix).
Route::get('/sitemap.xml', [SitemapController::class, 'index']);

// Auth routes (no locale prefix — redirects to Authentik).
Route::get('/auth/login', [AuthController::class, 'login'])->name('login');
Route::get('/auth/callback', [AuthController::class, 'callback']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// SPA surfaces (legacy).
Route::get('/', function () {
    return view('marketplace', ['surface' => 'storefront']);
});

// Session-based user info for SPA.
Route::get('/auth/user', function (\Illuminate\Http\Request $request) {
    if ($request->user()) {
        return response()->json([
            'data' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'role' => $request->user()->role,
            ],
        ]);
    }
    return response()->json(['data' => null]);
})->middleware('web');

Route::get('/admin', function () {
    return view('marketplace', ['surface' => 'admin']);
})->middleware(['auth', 'role:admin']);

// Localized routes for SEO.
Route::prefix('{locale?}')
    ->where(['locale' => 'en|es'])
    ->middleware(\App\Http\Middleware\DetectLocale::class)
    ->group(function () {
        Route::get('/extensions', [ExtensionController::class, 'index'])->name('extensions.index');
        Route::get('/extensions/{slug}', [ExtensionController::class, 'show'])->name('extensions.show');

        Route::get('/contact', function () {
            return view('contact');
        })->name('contact.page');

        Route::post('/contact', [ContactController::class, 'store'])
            ->middleware(['throttle:contact', 'recaptcha'])
            ->name('contact.store');

        Route::get('/docs', function () {
            return view('docs.index');
        })->name('docs.page');
    });
