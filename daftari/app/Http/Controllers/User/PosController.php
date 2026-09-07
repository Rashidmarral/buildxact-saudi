<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\PosRegister;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\PosShiftService;
use App\Services\ZatcaQrGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $data = $request->validate([
            'register_id' => ['required', 'exists:pos_registers,id'],
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
        $data = $request->validate([
            'register_id' => ['required', 'exists:pos_registers,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'exists:items,id'],
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
        $sale->loadMissing('items', 'payments', 'register', 'client', 'company');

        $qr = ZatcaQrGenerator::generate(
            $sale->company->name,
            (string) ($sale->company->vat_number ?? ''),
            $sale->created_at,
            (float) $sale->total,
            (float) $sale->vat_total
        );

        return view('user.pos.receipt', compact('sale', 'qr'));
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
