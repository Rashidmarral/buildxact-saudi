<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Salesperson extends Model
{
    use BelongsToCompany;

    protected $table = 'salespersons';

    protected $fillable = ['company_id', 'name', 'email', 'phone', 'commission_rate', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }
}
