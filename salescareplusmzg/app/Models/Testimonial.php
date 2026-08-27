<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'role',
        'organization',
        'quote',
        'rating',
        'sort_order',
    ];
}
