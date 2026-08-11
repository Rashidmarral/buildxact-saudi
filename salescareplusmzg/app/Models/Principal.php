<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Principal extends Model
{
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
