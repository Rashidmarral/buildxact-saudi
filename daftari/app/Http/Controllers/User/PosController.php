<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\Item;
use App\Models\PosRegister;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Services\Accounting\LedgerPostingService;
use App\Services\MpdfRenderer;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\PosShiftService;
use App\Services\ZatcaQrGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use RuntimeException;

class PosController extends Controller
{
    public function terminal(Request $request)
    {
        $registers = PosRegister::where('is_active', true)->orderBy('name')->get();
        $registerId = $request->query('register_id', $registers->first()?->id);
        $register = $registers->firstWhere('id', (int) $registerId);

        if (! $register) {
            return view('user.pos.no-register');
        }

        $shift = $register->openShift();

        if (! $shift) {
            return view('user.pos.open-shift', compact('register', 'registers'));
        }

        return view('user.pos.terminal', [
            'register' => $register,
            'registers' => $registers,
            'shift' => $shift,
        ]);
    }

    public function lookupItem(Request $request)
    {
        $query = trim((string) $request->query('q'));

        $items = Item::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('barcode', $query)
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%");
            })
            ->take(15)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'barcode' => $item->barcode,
                'unit_price' => (float) $item->unit_price,
                'vat_rate' => (float) $item->vat_rate,
            ])
            ->values();

        return response()->json($items);
    }

    public function openShift(Request $request, PosShiftService $shiftService)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'register_id' => ['required', Rule::exists('pos_registers', 'id')->where('company_id', $companyId)],
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ]);

        $register = PosRegister::findOrFail($data['register_id']);

        try {
            $shiftService->open($register, (float) $data['opening_cash']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['register' => $e->getMessage()]);
        }

        AuditLog::record('pos_shift.open', $register, __('Opened POS shift on :register', ['register' => $register->name]));

        return redirect()->route('app.pos.terminal', ['register_id' => $register->id]);
    }

    public function closeShift(Request $request, PosShift $shift, PosShiftService $shiftService)
    {
        $data = $request->validate(['counted_cash' => ['required', 'numeric', 'min:0']]);

        try {
            $shiftService->close($shift, (float) $data['counted_cash']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['shift' => $e->getMessage()]);
        }

        AuditLog::record('pos_shift.close', $shift, __('Closed POS shift on :register', ['register' => $shift->register->name]));

        return redirect()->route('app.pos.shifts.show', $shift)->with('status', __('Shift closed.'));
    }

    public function showShift(PosShift $shift)
    {
        $shift->load('register', 'opener', 'closer', 'sales.payments');

        return view('user.pos.shift-report', compact('shift'));
    }

    public function checkout(Request $request, PosSaleService $saleService, LedgerPostingService $ledger)
    {
        // Security audit finding D-8: these four all used a bare,
        // unscoped 'exists:table,id'. register_id/item_id happen to be
        // safe today because they're re-fetched through their scoped
        // Eloquent models afterward (PosRegister::findOrFail() here,
        // Item::findOrFail() in PosSaleService::checkout()) — but
        // client_id was NOT re-fetched: it was written straight onto
        // the new PosSale row, so a Company A cashier submitting a
        // Company B client ID would have linked the sale to another
        // tenant's client record. Scoping all four here closes that and
        // matches the pattern used everywhere else in the codebase.
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'register_id' => ['required', Rule::exists('pos_registers', 'id')->where('company_id', $companyId)],
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')->where('company_id', $companyId)],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:cash,card,other'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
        ]);

        $register = PosRegister::findOrFail($data['register_id']);
        $shift = $register->openShift();

        if (! $shift) {
            return back()->withErrors(['register' => __('Open a shift before ringing up a sale.')]);
        }

        try {
            $sale = $saleService->checkout($shift, $data['lines'], $data['payments'], $data['client_id'] ?? null, $ledger);
        } catch (RuntimeException $e) {
            return back()->withErrors(['cart' => $e->getMessage()])->withInput();
        }

        AuditLog::record('pos_sale.create', $sale, __('POS sale :number', ['number' => $sale->sale_number]));

        return redirect()->route('app.pos.sales.show', $sale)->with('status', __('Sale completed.'));
    }

    public function index()
    {
        $sales = PosSale::with('register', 'creator')->orderByDesc('created_at')->paginate(25);

        return view('user.pos.sales-index', compact('sales'));
    }

    public function showSale(PosSale $sale)
    {
        $sale->loadMissing('items.item', 'payments', 'register', 'client', 'company');

        $qr = ZatcaQrGenerator::generate(
            $sale->company->name,
            (string) ($sale->company->vat_number ?? ''),
            $sale->created_at,
            (float) $sale->total,
            (float) $sale->vat_total
        );

        $template = $this->resolveReceiptTemplate($sale->company);

        return view('user.pos.receipt', [
            'sale' => $sale, 'qr' => $qr,
            'layout' => $template->layout, 'languageMode' => $template->language_mode ?? 'bilingual',
        ]);
    }

    public function downloadReceiptPdf(PosSale $sale, MpdfRenderer $renderer)
    {
        $sale->loadMissing('items.item', 'payments', 'register', 'client', 'company');

        $qr = ZatcaQrGenerator::generate(
            $sale->company->name,
            (string) ($sale->company->vat_number ?? ''),
            $sale->created_at,
            (float) $sale->total,
            (float) $sale->vat_total
        );

        $template = $this->resolveReceiptTemplate($sale->company);

        $pdf = $renderer->render('documents.print.pos-receipt-pdf', [
            'sale' => $sale, 'qr' => $qr,
            'layout' => $template->layout, 'languageMode' => $template->language_mode ?? 'bilingual',
            'template' => $template,
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$sale->sale_number.'.pdf"',
        ]);
    }

    /**
     * A real, saved template with a valid receipt layout if the company
     * has picked one (see ReceiptTemplateController); otherwise a
     * synthetic default — never falls back to a document_type='all'
     * template the way invoices/quotations do, since that default is
     * almost certainly one of the A4 layouts (bilingual_classic, ...),
     * which this receipt system doesn't know how to render at all.
     */
    private function resolveReceiptTemplate(Company $company): InvoiceTemplate
    {
        $template = $company->defaultTemplateFor('pos_receipt');

        if ($template && in_array($template->layout, ['receipt_compact', 'receipt_detailed'], true)) {
            return $template;
        }

        return new InvoiceTemplate(['layout' => 'receipt_compact', 'language_mode' => 'bilingual']);
    }

    public function voidSale(Request $request, PosSale $sale, PosSaleService $saleService, LedgerPostingService $ledger)
    {
        $data = $request->validate(['void_reason' => ['required', 'string', 'max:255']]);

        try {
            $saleService->void($sale, $data['void_reason'], $ledger);
        } catch (RuntimeException $e) {
            return back()->withErrors(['sale' => $e->getMessage()]);
        }

        AuditLog::record('pos_sale.void', $sale, __('Voided POS sale :number', ['number' => $sale->sale_number]));

        return back()->with('status', __('Sale voided.'));
    }
}
