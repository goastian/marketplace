<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('authentik.jwt')->group(function () {
        Route::get('/me', [\App\Http\Controllers\Api\V1\MeController::class, 'show']);
        Route::get('/me/preferences', [\App\Http\Controllers\Api\V1\PreferencesController::class, 'show']);
        Route::put('/me/preferences', [\App\Http\Controllers\Api\V1\PreferencesController::class, 'upsert']);
    });

    Route::get('/health', fn (Request $request) => ['ok' => true]);
});

