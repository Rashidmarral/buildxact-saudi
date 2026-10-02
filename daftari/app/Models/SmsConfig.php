<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class SmsConfig extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'app_sid', 'sender_id', 'is_enabled',
    ];

    protected $hidden = ['app_sid'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'app_sid' => 'encrypted',
        ];
    }
}
