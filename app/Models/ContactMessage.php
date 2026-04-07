<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'email',
    'subject',
    'message',
    'ip_address',
    'status',
])]
class ContactMessage extends Model
{
}
