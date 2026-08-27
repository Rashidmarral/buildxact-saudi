<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Certification extends Model
{
    use LogsActivity;

    protected $fillable = [
        'title',
        'issuing_body',
        'description',
        'icon',
        'sort_order',
    ];
}
