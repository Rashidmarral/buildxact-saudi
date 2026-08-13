<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'name_ar', 'vat_number', 'cr_number',
        'email', 'phone', 'address', 'city',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
