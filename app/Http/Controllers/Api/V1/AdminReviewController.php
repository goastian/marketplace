<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AssetReview;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AssetReview::query()
            ->with(['user', 'asset'])
            ->orderByDesc('id');

        $status = $request->query('status');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $perPage = max(1, min((int) $request->integer('per_page', 20), 100));
        $reviews = $query->paginate($perPage);

        return response()->json([
            'data' => collect($reviews->items())->map(fn (AssetReview $r) => $this->transform($r))->values(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    public function approve(Request $request, AssetReview $review): JsonResponse
    {
        $review->update(['status' => 'approved']);

        AuditLog::record('admin.review.approve', $request->user()->id, 'AssetReview', $review->id, null, $request->ip());

        return response()->json(['data' => $this->transform($review->fresh())]);
    }

    public function destroy(Request $request, AssetReview $review): JsonResponse
    {
        AuditLog::record('admin.review.delete', $request->user()->id, 'AssetReview', $review->id, [
            'asset_id' => $review->asset_id,
            'user_id' => $review->user_id,
        ], $request->ip());

        $review->delete();

        return response()->json(null, 204);
    }

    private function transform(AssetReview $review): array
    {
        return [
            'id' => $review->id,
            'asset_id' => $review->asset_id,
            'asset' => $review->relationLoaded('asset') && $review->asset ? [
                'id' => $review->asset->id,
                'name' => $review->asset->name,
                'slug' => $review->asset->slug,
            ] : null,
            'user_id' => $review->user_id,
            'user' => $review->relationLoaded('user') && $review->user ? [
                'id' => $review->user->id,
                'name' => $review->user->name,
            ] : null,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'status' => $review->status,
            'created_at' => $review->created_at?->toISOString(),
        ];
    }
}
