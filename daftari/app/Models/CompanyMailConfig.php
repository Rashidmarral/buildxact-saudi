<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class CompanyMailConfig extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username',
        'smtp_password', 'from_address', 'from_name', 'is_enabled',
    ];

    protected $hidden = ['smtp_password'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'smtp_port' => 'integer',
            'smtp_password' => 'encrypted',
        ];
    }
}
