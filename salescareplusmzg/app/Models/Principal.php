<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Principal extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'initials',
        'tagline',
        'description',
        'logo_path',
        'years_partnership',
        'products_count',
        'sort_order',
    ];
}
