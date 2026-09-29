<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A generated bilingual letter/agreement — a sale/purchase/rental
 * agreement for a machine, a work handover letter, or any custom letter a
 * company drafts. document_type is a free string (see App\Support\
 * LetterPresets for the starter content shipped for each kind); content
 * is a freely editable ordered array of {text_en, text_ar, is_heading}
 * paragraph blocks the user can add/remove/edit — nothing about a
 * letter's body is fixed once generated from a preset.
 */
class CompanyLetter extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'machinery_asset_id', 'machinery_rental_contract_id', 'project_id',
        'document_type', 'reference_number', 'title', 'title_ar', 'letter_date',
        'party_a_role', 'party_a_name', 'party_b_role', 'party_b_name', 'party_b_details',
        'client_id', 'supplier_id', 'language_mode', 'content', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'letter_date' => 'date',
            'content' => 'array',
        ];
    }

    public function machinery(): BelongsTo
    {
        return $this->belongsTo(MachineryAsset::class, 'machinery_asset_id');
    }

    public function rentalContract(): BelongsTo
    {
        return $this->belongsTo(MachineryRentalContract::class, 'machinery_rental_contract_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function partyAName(): string
    {
        return $this->party_a_name ?: $this->company->name;
    }

    public function titleAr(): string
    {
        return $this->title_ar ?: $this->title;
    }
}
