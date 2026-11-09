<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMode extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'code', 'name', 'name_ar', 'category', 'linked_account_id', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function linkedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'linked_account_id');
    }

    public function label(): string
    {
        return $this->name;
    }

    /**
     * The four tender types every POS/restaurant/repair-shop checkout
     * form already validates against (PosController, RestaurantOrder-
     * Controller, RepairJobController), each pointed at the same
     * account LedgerPostingService::postPosSale() hardcoded before this
     * table existed — so a company that never visits Settings sees
     * exactly the same GL postings as before. Idempotent: safe to call
     * on every checkout for companies that predate this feature.
     */
    public static function seedDefaults(int $companyId): void
    {
        $cash = AccountMapping::resolve($companyId, 'DEFAULT_CASH');
        $card = AccountMapping::resolve($companyId, 'POS_CARD_CLEARING');
        $bank = AccountMapping::resolve($companyId, 'DEFAULT_BANK');

        $defaults = [
            ['code' => 'cash', 'name' => __('Cash'), 'category' => 'cash', 'linked_account_id' => $cash?->id, 'sort_order' => 1],
            ['code' => 'card', 'name' => __('Card'), 'category' => 'card', 'linked_account_id' => $card?->id, 'sort_order' => 2],
            ['code' => 'other', 'name' => __('Other'), 'category' => 'other', 'linked_account_id' => $bank?->id, 'sort_order' => 3],
            ['code' => 'loyalty_card', 'name' => __('Loyalty card'), 'category' => 'loyalty_card', 'linked_account_id' => $bank?->id, 'sort_order' => 4],
        ];

        foreach ($defaults as $default) {
            self::withoutGlobalScope('company')->firstOrCreate(
                ['company_id' => $companyId, 'code' => $default['code']],
                $default + ['company_id' => $companyId]
            );
        }
    }
}
