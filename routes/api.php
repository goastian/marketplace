<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/catalog', [\App\Http\Controllers\Api\V1\CatalogController::class, 'index']);
    Route::get('/assets/{asset:slug}', [\App\Http\Controllers\Api\V1\CatalogController::class, 'show']);
    Route::get('/assets/{asset:slug}/download', [\App\Http\Controllers\Api\V1\CatalogController::class, 'download']);
    Route::get('/assets/{asset}/versions/{assetVersion}/download/signed', [\App\Http\Controllers\Api\V1\CatalogController::class, 'downloadSigned'])
        ->middleware('signed')
        ->name('api.v1.assets.download.signed');

    Route::middleware('authentik.jwt')->group(function () {
        Route::get('/me', [\App\Http\Controllers\Api\V1\MeController::class, 'show']);
        Route::get('/me/preferences', [\App\Http\Controllers\Api\V1\PreferencesController::class, 'show']);
        Route::put('/me/preferences', [\App\Http\Controllers\Api\V1\PreferencesController::class, 'upsert']);

        Route::get('/me/assets', [\App\Http\Controllers\Api\V1\ManagedAssetController::class, 'index']);
        Route::post('/me/assets', [\App\Http\Controllers\Api\V1\ManagedAssetController::class, 'store']);
        Route::get('/me/assets/{asset}', [\App\Http\Controllers\Api\V1\ManagedAssetController::class, 'show']);
        Route::put('/me/assets/{asset}', [\App\Http\Controllers\Api\V1\ManagedAssetController::class, 'update']);
        Route::post('/me/assets/{asset}/versions', [\App\Http\Controllers\Api\V1\ManagedAssetController::class, 'storeVersion']);
    });

    Route::get('/health', fn (Request $request) => ['ok' => true]);
});

