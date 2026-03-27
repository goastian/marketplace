<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asset_id',
    'version',
    'status',
    'min_app_version',
    'max_app_version',
    'browsers',
    'manifest',
    'file_disk',
    'file_path',
    'checksum',
    'size_bytes',
    'published_at',
])]
class AssetVersion extends Model
{
    protected function casts(): array
    {
        return [
            'browsers' => 'array',
            'manifest' => 'array',
            'size_bytes' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
