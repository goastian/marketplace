<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'data'])]
class UserPreference extends Model
{
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
