<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Support\ReceiptTemplatePresets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * A small, dedicated picker for the POS/Restaurant sale receipt's PDF —
 * separate from the invoice/quotation Advanced Customization editor
 * (that form's letterhead/watermark/density/table-direction fields don't
 * apply to a narrow receipt slip at all) but built the same way: each
 * card is an InvoiceTemplate row with document_type='pos_receipt',
 * scoped so activating one only ever clears is_default on the company's
 * other pos_receipt rows — never touching invoices, quotations, or any
 * other document type's default (see InvoiceTemplateController's own
 * activatePreset() for the same 'all'-scoped pattern this mirrors).
 */
class ReceiptTemplateController extends Controller
{
    public function index()
    {
        $company = Auth::user()->company;

        $activeKey = $company->invoiceTemplates()
            ->where('document_type', 'pos_receipt')
            ->where('is_default', true)
            ->value('preset_key');

        return view('user.receipt-templates.index', [
            'presets' => ReceiptTemplatePresets::all(),
            'activeKey' => $activeKey,
        ]);
    }

    public function activate(string $key): RedirectResponse
    {
        $preset = ReceiptTemplatePresets::find($key);
        abort_unless($preset, 404);

        $company = Auth::user()->company;
        $existing = $company->invoiceTemplates()->where('document_type', 'pos_receipt')->where('preset_key', $key)->first();

        if (! $existing && $company->hasReachedPlanLimit('invoice_templates')) {
            return redirect()->route('app.receipt-templates.index')
                ->withErrors(['plan_limit' => __('You have reached your plan\'s invoice template limit. Upgrade your plan to add more templates.')]);
        }

        $company->invoiceTemplates()->where('document_type', 'pos_receipt')->where('id', '!=', $existing?->id)->update(['is_default' => false]);

        $company->invoiceTemplates()->updateOrCreate(
            ['document_type' => 'pos_receipt', 'preset_key' => $key],
            [
                'name' => $preset['name'],
                'name_ar' => $preset['name_ar'],
                'layout' => $preset['layout'],
                'language_mode' => $preset['language_mode'],
                'is_default' => true,
            ]
        );

        return redirect()->route('app.receipt-templates.index')
            ->with('status', __(':name is now used on your sale receipts.', ['name' => $preset['name']]));
    }
}
