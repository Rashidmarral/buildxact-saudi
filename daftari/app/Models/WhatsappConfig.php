<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class WhatsappConfig extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'phone_number_id', 'access_token',
        'template_name', 'template_language', 'is_enabled',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'access_token' => 'encrypted',
        ];
    }
}
