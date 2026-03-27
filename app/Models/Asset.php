<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'owner_user_id',
    'type',
    'slug',
    'name',
    'description',
    'author',
    'license',
    'tags',
    'status',
    'published_at',
])]
class Asset extends Model
{
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AssetVersion::class);
    }

    public function publishedVersions(): HasMany
    {
        return $this->hasMany(AssetVersion::class)
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }
}
