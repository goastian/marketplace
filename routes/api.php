<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public catalog routes.
    Route::middleware('throttle:api-public')->group(function () {
        Route::get('/catalog', [\App\Http\Controllers\Api\V1\CatalogController::class, 'index']);
        Route::get('/assets/{asset:slug}', [\App\Http\Controllers\Api\V1\CatalogController::class, 'show']);
        Route::get('/assets/{asset:slug}/download', [\App\Http\Controllers\Api\V1\CatalogController::class, 'download']);
        Route::get('/assets/{asset}/versions/{assetVersion}/download/signed', [\App\Http\Controllers\Api\V1\CatalogController::class, 'downloadSigned'])
            ->middleware('signed')
            ->name('api.v1.assets.download.signed');

        // Public reviews.
        Route::get('/assets/{asset:slug}/reviews', [\App\Http\Controllers\Api\V1\ReviewController::class, 'index']);
    });

    // Authenticated user routes.
    Route::middleware(['authentik.jwt', 'throttle:api-authenticated'])->group(function () {
        Route::get('/me', [\App\Http\Controllers\Api\V1\MeController::class, 'show']);
        Route::get('/me/preferences', [\App\Http\Controllers\Api\V1\PreferencesController::class, 'show']);
        Route::put('/me/preferences', [\App\Http\Controllers\Api\V1\PreferencesController::class, 'upsert']);

        // Developer asset management.
        Route::get('/me/assets', [\App\Http\Controllers\Api\V1\ManagedAssetController::class, 'index']);
        Route::post('/me/assets', [\App\Http\Controllers\Api\V1\ManagedAssetController::class, 'store']);
        Route::get('/me/assets/{asset}', [\App\Http\Controllers\Api\V1\ManagedAssetController::class, 'show']);
        Route::put('/me/assets/{asset}', [\App\Http\Controllers\Api\V1\ManagedAssetController::class, 'update']);
        Route::post('/me/assets/{asset}/versions', [\App\Http\Controllers\Api\V1\ManagedAssetController::class, 'storeVersion'])
            ->middleware('throttle:uploads');

        // User reviews (create, edit own, delete own).
        Route::post('/assets/{asset:slug}/reviews', [\App\Http\Controllers\Api\V1\ReviewController::class, 'store'])
            ->middleware('throttle:reviews');
        Route::put('/me/reviews/{review}', [\App\Http\Controllers\Api\V1\ReviewController::class, 'update']);
        Route::delete('/me/reviews/{review}', [\App\Http\Controllers\Api\V1\ReviewController::class, 'destroy']);
    });

    // Admin routes.
    Route::middleware(['authentik.jwt', 'role:admin', 'throttle:api-authenticated'])->prefix('admin')->group(function () {
        Route::get('/stats', [\App\Http\Controllers\Api\V1\AdminController::class, 'stats']);
        Route::get('/assets', [\App\Http\Controllers\Api\V1\AdminController::class, 'index']);
        Route::get('/assets/{asset}', [\App\Http\Controllers\Api\V1\AdminController::class, 'show']);
        Route::post('/assets', [\App\Http\Controllers\Api\V1\AdminController::class, 'store']);
        Route::put('/assets/{asset}/approve', [\App\Http\Controllers\Api\V1\AdminController::class, 'approve']);
        Route::put('/assets/{asset}/reject', [\App\Http\Controllers\Api\V1\AdminController::class, 'reject']);
        Route::put('/assets/{asset}/request-revision', [\App\Http\Controllers\Api\V1\AdminController::class, 'requestRevision']);

        Route::get('/reviews', [\App\Http\Controllers\Api\V1\AdminReviewController::class, 'index']);
        Route::put('/reviews/{review}/approve', [\App\Http\Controllers\Api\V1\AdminReviewController::class, 'approve']);
        Route::delete('/reviews/{review}', [\App\Http\Controllers\Api\V1\AdminReviewController::class, 'destroy']);
    });

    Route::get('/health', fn (Request $request) => ['ok' => true]);
});

