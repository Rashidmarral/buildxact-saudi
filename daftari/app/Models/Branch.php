<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'code', 'name', 'name_ar', 'cr_number', 'logo_path',
        'street_name', 'building_number', 'district', 'city', 'state',
        'country', 'postal_code', 'phone', 'email',
    ];

    protected static function booted(): void
    {
        static::creating(function (Branch $branch) {
            if (empty($branch->code) && $branch->company_id) {
                $branch->code = static::generateCode($branch->company_id);
            }
        });
    }

    public static function generateCode(int $companyId): string
    {
        do {
            $code = 'BR-'.random_int(100000, 999999);
        } while (static::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->exists());

        return $code;
    }

    public function fullAddress(): string
    {
        return collect([
            $this->building_number, $this->street_name, $this->district,
            $this->city, $this->state, $this->postal_code, $this->country,
        ])->filter()->implode(', ');
    }
}
