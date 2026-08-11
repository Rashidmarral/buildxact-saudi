<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ClientLogo extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'logo_path',
        'website_url',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];
}
