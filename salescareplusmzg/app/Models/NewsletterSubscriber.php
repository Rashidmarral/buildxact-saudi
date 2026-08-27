<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    use LogsActivity;

    protected $fillable = [
        'email',
    ];
}
