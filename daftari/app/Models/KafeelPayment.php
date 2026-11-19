<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A private, per-company payment log — money paid to or on behalf of a
 * company's Kafeel (sponsor), a Saudi sponsorship-system relationship
 * (license/Iqama fees, insurance, fines, cash handed to the sponsor's
 * own staff, subscriptions, ...). Deliberately NOT posted to the GL —
 * this is a personal tracking sheet a company owner keeps, not a
 * company expense, so LedgerPostingService is never involved. The
 * whole module is hidden by default (FeatureRegistry 'kafeel_payments'
 * is 'planned' type — no plan grants it, no self-service request flow
 * exists) and only becomes visible for a specific company once a
 * Super Admin sets a CompanyOverride, per that company owner's own
 * request to keep this private.
 */
class KafeelPayment extends Model
{
    use BelongsToCompany, SoftDeletes;

    public const CATEGORIES = [
        'license_fee' => 'License / Iqama fee',
        'insurance' => 'Health insurance',
        'maintenance' => 'Vehicle / equipment maintenance',
        'fine' => 'Fine',
        'cash_transfer' => 'Cash transfer',
        'fuel' => 'Fuel',
        'subscription' => 'Subscription',
        'recharge' => 'Mobile / data recharge',
        'grocery' => 'Grocery',
        'utility' => 'Utility',
        'other' => 'Other',
    ];

    protected $fillable = [
        'company_id', 'payment_date', 'paid_to', 'category', 'description', 'amount', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function categoryLabel(): string
    {
        return __(self::CATEGORIES[$this->category] ?? self::CATEGORIES['other']);
    }
}
