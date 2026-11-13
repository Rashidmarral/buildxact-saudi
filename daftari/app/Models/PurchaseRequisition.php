<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The step before a Purchase Order: a material/equipment need flagged
 * by whoever's on site, with no supplier or price chosen yet. Once
 * approved it can be turned into a priced PurchaseOrder — see
 * PurchaseRequisitionController::createPurchaseOrder() and
 * PurchaseOrderController::create()'s `from_requisition` prefill.
 */
class PurchaseRequisition extends Model
{
    use BelongsToCompany;

    public const STATUSES = ['requested', 'approved', 'rejected', 'converted'];

    protected $fillable = [
        'company_id', 'project_id', 'requested_by', 'requisition_number', 'needed_by_date',
        'status', 'notes', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'purchase_order_id',
    ];

    protected function casts(): array
    {
        return [
            'needed_by_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionItem::class);
    }
}
