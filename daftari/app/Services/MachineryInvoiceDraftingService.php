<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MachineryAsset;
use App\Models\TaxRate;
use Illuminate\Support\Facades\Auth;

/**
 * Shared by MachineryAssetController::sell() and
 * MachineryRentalContractController::generateInvoice() — a minimal draft
 * Invoice with one free-text line item (no Item record required, see
 * InvoiceItem.item_id's nullable precedent), tagged to a machine. Always
 * left as a draft: the ordinary Invoice show page's own send/approval/
 * ZATCA flow takes it from there, so this never duplicates that logic.
 */
class MachineryInvoiceDraftingService
{
    public function draftLine(MachineryAsset $machineryAsset, ?int $clientId, string $description, float $amount, string $date): Invoice
    {
        $company = $machineryAsset->company;

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $clientId ?: Client::where('company_id', $company->id)->value('id'),
            'machinery_asset_id' => $machineryAsset->id,
            'branch_id' => $company->default_branch_id,
            'created_by' => Auth::id(),
            'invoice_number' => $company->nextInvoiceNumber(),
            'type' => 'invoice',
            'status' => 'draft',
            'issue_date' => $date,
            'currency' => $company->currency,
            'exchange_rate' => 1,
        ]);

        $line = new InvoiceItem([
            'invoice_id' => $invoice->id,
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $amount,
            'vat_rate' => TaxRate::defaultRate($company->id),
        ]);
        $line->recalculate();
        $line->save();

        $invoice->recalculateTotals();

        return $invoice;
    }
}
