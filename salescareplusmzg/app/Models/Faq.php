<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use LogsActivity;

    protected $fillable = [
        'question',
        'answer',
        'category',
        'sort_order',
    ];
}
