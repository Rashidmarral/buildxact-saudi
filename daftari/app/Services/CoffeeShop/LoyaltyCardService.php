<?php

namespace App\Services\CoffeeShop;

use App\Models\Company;
use App\Models\LoyaltyCard;
use App\Models\LoyaltyCardTransaction;
use App\Models\PosSale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The Coffee Shop module's differentiator over plain Restaurant: a
 * reloadable prepaid balance a walk-in or regular customer can top up and
 * spend at checkout. Redeeming is called from
 * RestaurantOrderService::checkout() inside its own DB transaction, so a
 * failed checkout rolls the redemption back with it — a card is never
 * debited for a sale that didn't actually go through.
 */
class LoyaltyCardService
{
    public function createCard(Company $company, ?int $clientId, float $initialTopUp = 0): LoyaltyCard
    {
        $card = LoyaltyCard::create([
            'company_id' => $company->id,
            'client_id' => $clientId,
            'card_number' => $this->generateCardNumber($company),
            'balance' => 0,
            'is_active' => true,
        ]);

        if ($initialTopUp > 0) {
            $this->topUp($card, $initialTopUp);
        }

        return $card->fresh();
    }

    public function topUp(LoyaltyCard $card, float $amount, ?string $notes = null): LoyaltyCardTransaction
    {
        if ($amount <= 0) {
            throw new RuntimeException(__('Top-up amount must be greater than zero.'));
        }

        if (! $card->is_active) {
            throw new RuntimeException(__('This card is deactivated.'));
        }

        return DB::transaction(function () use ($card, $amount, $notes) {
            $card = LoyaltyCard::whereKey($card->id)->lockForUpdate()->firstOrFail();
            $card->increment('balance', $amount);

            return LoyaltyCardTransaction::create([
                'company_id' => $card->company_id,
                'loyalty_card_id' => $card->id,
                'type' => 'top_up',
                'amount' => $amount,
                'created_by' => Auth::id(),
                'notes' => $notes,
            ]);
        });
    }

    /**
     * @param  PosSale|null  $sale  Attached to the transaction once the
     *                              checkout it paid for actually exists —
     *                              null while redeeming happens before the
     *                              sale row is created (see
     *                              RestaurantOrderService::checkout()).
     */
    public function redeem(LoyaltyCard $card, float $amount, ?PosSale $sale = null): LoyaltyCardTransaction
    {
        if ($amount <= 0) {
            throw new RuntimeException(__('Redeem amount must be greater than zero.'));
        }

        return DB::transaction(function () use ($card, $amount, $sale) {
            $card = LoyaltyCard::whereKey($card->id)->lockForUpdate()->firstOrFail();

            if (! $card->is_active) {
                throw new RuntimeException(__('This card is deactivated.'));
            }

            if ((float) $card->balance < $amount) {
                throw new RuntimeException(__('Card :number has an insufficient balance (:balance) for this amount.', [
                    'number' => $card->card_number,
                    'balance' => number_format((float) $card->balance, 2),
                ]));
            }

            $card->decrement('balance', $amount);

            return LoyaltyCardTransaction::create([
                'company_id' => $card->company_id,
                'loyalty_card_id' => $card->id,
                'type' => 'redeem',
                'amount' => $amount,
                'pos_sale_id' => $sale?->id,
                'created_by' => Auth::id(),
            ]);
        });
    }

    private function generateCardNumber(Company $company): string
    {
        do {
            $number = 'LC-'.strtoupper(Str::random(8));
        } while (LoyaltyCard::where('company_id', $company->id)->where('card_number', $number)->exists());

        return $number;
    }
}
