<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasBilingualName;
use App\Models\Concerns\HasCustomFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use BelongsToCompany, HasBilingualName, HasCustomFields;

    public const CUSTOM_FIELD_ENTITY_TYPE = 'client';

    /**
     * A client record doubles as a sales pipeline card when a stage is
     * set — the same record a prospect gets invoiced on once they convert,
     * rather than a separate lead entity that would need its own
     * conversion flow. 'won' and 'lost' are terminal; everything else is
     * still an open deal (see isInOpenPipeline()).
     */
    public const PIPELINE_STAGES = ['prospect', 'contacted', 'qualified', 'negotiating', 'won', 'lost'];

    public const OPEN_PIPELINE_STAGES = ['prospect', 'contacted', 'qualified', 'negotiating'];

    protected $fillable = [
        'company_id', 'client_code', 'type', 'name', 'name_ar', 'contact_name',
        'is_vat_registered', 'vat_number', 'cr_number', 'additional_id_type',
        'additional_id_number', 'initial_balance', 'payment_terms_days', 'email', 'phone', 'mobile',
        'street_name', 'building_number', 'district', 'city', 'state', 'country',
        'postal_code', 'notes',
        'lead_source', 'next_follow_up_date', 'last_contacted_at', 'pipeline_stage', 'lost_reason',
        'platform_billed_company_id',
        'credit_hold', 'credit_hold_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_vat_registered' => 'boolean',
            'initial_balance' => 'decimal:2',
            'payment_terms_days' => 'integer',
            'next_follow_up_date' => 'date',
            'last_contacted_at' => 'datetime',
            'credit_hold' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            if (empty($client->client_code) && $client->company_id) {
                $client->client_code = static::generateClientCode($client->company_id);
            }
        });
    }

    public static function generateClientCode(int $companyId): string
    {
        do {
            $code = 'CLI-'.random_int(100000, 999999);
        } while (static::withoutGlobalScopes()->where('company_id', $companyId)->where('client_code', $code)->exists());

        return $code;
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function collectionNotes(): HasMany
    {
        return $this->hasMany(CollectionNote::class)->latest();
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function fullAddress(): string
    {
        return collect([
            $this->building_number,
            $this->street_name,
            $this->district,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ])->filter()->implode(', ');
    }

    public function isInOpenPipeline(): bool
    {
        return in_array($this->pipeline_stage, self::OPEN_PIPELINE_STAGES, true);
    }
}
