<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One existing document (a Quotation, Purchase Order, or Invoice, so
 * far — $linkableTypes below is the whole whitelist) referenced by a
 * CompanyLetter, in the order it should appear when CompanyLetterController::pdf()
 * merges each one's own rendered PDF behind the letter itself. See the
 * company_letter_links migration for the full reasoning. Note
 * PurchaseOrder here is always this company's own issued order to one
 * of ITS suppliers — a client's own PO (an external document this
 * company never generated) isn't a linkable system record at all; that
 * goes through CompanyLetter::attachments() as an uploaded file
 * instead, which CompanyLetterController::pdf() also merges in.
 */
class CompanyLetterLink extends Model
{
    protected $fillable = ['company_letter_id', 'linkable_type', 'linkable_id', 'sort_order'];

    /**
     * The morph alias => model map this relation accepts, also used to
     * validate incoming request input before anything is ever written —
     * never trust a raw class-name string from the client as a morph
     * type. Keep in sync with CompanyLetterController's picker options.
     */
    public const LINKABLE_TYPES = [
        'quotation' => Quotation::class,
        'purchase_order' => PurchaseOrder::class,
        'invoice' => Invoice::class,
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(CompanyLetter::class, 'company_letter_id');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
