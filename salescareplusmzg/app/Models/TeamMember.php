<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'designation',
        'bio',
        'initials',
        'photo_path',
        'sort_order',
    ];
}
