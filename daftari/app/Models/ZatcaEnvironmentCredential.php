<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A save slot for one company's onboarding progress in one ZATCA
 * environment (developer/simulation/production) — see the migration
 * that creates this table and Company::switchZatcaEnvironment() for why
 * this exists separately from the company's own zatca_* columns.
 */
class ZatcaEnvironmentCredential extends Model
{
    protected $fillable = [
        'company_id', 'environment', 'onboarding_status', 'egs_serial', 'common_name',
        'organization_unit_name', 'business_category', 'csr', 'private_key',
        'compliance_request_id', 'compliance_csid', 'compliance_secret',
        'production_request_id', 'production_csid', 'production_secret',
        'last_invoice_hash', 'linked_at', 'last_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'private_key' => 'encrypted',
            'compliance_csid' => 'encrypted',
            'compliance_secret' => 'encrypted',
            'production_csid' => 'encrypted',
            'production_secret' => 'encrypted',
            'linked_at' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
