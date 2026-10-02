<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One tier of a company's configurable multi-step approval chain for a
 * document type (e.g. "purchase orders over 5,000 SAR need the Manager
 * role"). A document picks up every step whose min_amount it reaches, in
 * step_number order — see ApprovalChainService::applicableSteps(). A
 * document type with no steps configured at all falls back entirely to
 * the older flat company.*_approval_threshold gate (Company::poRequiresApproval()
 * etc.) rather than being affected by this table in any way.
 */
class ApprovalChainStep extends Model
{
    use BelongsToCompany;

    public const DOCUMENT_TYPES = ['purchase_order', 'expense'];

    protected $fillable = ['company_id', 'document_type', 'step_number', 'role_id', 'min_amount'];

    protected function casts(): array
    {
        return ['min_amount' => 'decimal:2'];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public static function forDocumentType(string $documentType): Collection
    {
        return static::where('document_type', $documentType)->with('role')->orderBy('step_number')->get();
    }
}
