<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certification extends Model
{
    protected $fillable = [
        'title',
        'issuing_body',
        'description',
        'icon',
        'sort_order',
    ];
}
