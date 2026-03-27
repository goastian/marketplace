<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'rev', 'scope', 'payload'])]
class SyncRevision extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
