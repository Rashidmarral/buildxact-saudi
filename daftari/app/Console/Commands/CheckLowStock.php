<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\ItemStock;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Console\Command;

class CheckLowStock extends Command
{
    protected $signature = 'inventory:check-low-stock';

    protected $description = 'Notify users with inventory access when a tracked item drops to or below its reorder point, once per dip';

    public function handle(): int
    {
        $notified = 0;

        foreach (Company::withoutGlobalScopes()->get() as $company) {
            $notified += $this->checkCompany($company);
        }

        $this->info("Sent low-stock alerts for {$notified} item/warehouse pair(s).");

        return self::SUCCESS;
    }

    private function checkCompany(Company $company): int
    {
        $stocks = ItemStock::whereHas('item', fn ($q) => $q->where('company_id', $company->id)->where('track_inventory', true)->where('is_active', true)->whereNotNull('reorder_point'))
            ->with('item', 'warehouse')
            ->get();

        // A pair that's back above its reorder point is cleared so a
        // future dip alerts again — otherwise a single low-stock event
        // would silence this item forever.
        $replenished = $stocks->filter(fn (ItemStock $stock) => $stock->quantity > $stock->item->reorder_point && $stock->low_stock_notified_at !== null);
        foreach ($replenished as $stock) {
            $stock->update(['low_stock_notified_at' => null]);
        }

        $newlyLow = $stocks->filter(fn (ItemStock $stock) => $stock->quantity <= $stock->item->reorder_point && $stock->low_stock_notified_at === null);

        if ($newlyLow->isEmpty()) {
            return 0;
        }

        $this->notifyUsers($company, $newlyLow);

        foreach ($newlyLow as $stock) {
            $stock->update(['low_stock_notified_at' => now()]);
        }

        return $newlyLow->count();
    }

    private function notifyUsers(Company $company, \Illuminate\Support\Collection $lowStocks): void
    {
        $body = $lowStocks->count() === 1
            ? __(':item at :warehouse is at :qty (reorder point :point).', [
                'item' => $lowStocks->first()->item->name,
                'warehouse' => $lowStocks->first()->warehouse->name,
                'qty' => rtrim(rtrim(number_format($lowStocks->first()->quantity, 2), '0'), '.'),
                'point' => rtrim(rtrim(number_format($lowStocks->first()->item->reorder_point, 2), '0'), '.'),
            ])
            : __(':count items have dropped to or below their reorder point.', ['count' => $lowStocks->count()]);

        User::where('company_id', $company->id)
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('inventory'))
            ->each(fn (User $user) => $user->notify(new GenericNotification(
                title: __('Low stock alert'),
                body: $body,
                url: route('app.inventory.stock'),
                icon: 'items',
            )));
    }
}
