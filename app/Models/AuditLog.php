<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'action',
    'target_type',
    'target_id',
    'meta',
    'ip_address',
])]
class AuditLog extends Model
{
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an audit entry.
     */
    public static function record(
        string $action,
        ?int $userId = null,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $meta = null,
        ?string $ipAddress = null,
    ): static {
        return static::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'meta' => $meta,
            'ip_address' => $ipAddress,
        ]);
    }
}
