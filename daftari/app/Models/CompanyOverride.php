<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Super Admin's manual override of one feature or limit for one
 * company — takes precedence over whatever the company's plan says. See
 * FeatureAccessService / UsageLimitService, which always check here first.
 */
class CompanyOverride extends Model
{
    protected $fillable = [
        'company_id', 'type', 'key', 'value', 'is_unlimited', 'reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_unlimited' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
