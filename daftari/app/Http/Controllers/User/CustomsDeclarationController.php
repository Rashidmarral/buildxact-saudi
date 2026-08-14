<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CustomsDeclaration;
use App\Models\Supplier;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CustomsDeclarationController extends Controller
{
    public function index()
    {
        $declarations = CustomsDeclaration::with('supplier', 'bills')
            ->orderByDesc('declaration_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('user.customs-declarations.index', [
            'declarations' => $declarations,
            'suppliers' => Supplier::orderBy('name')->get(),
            'totalCount' => CustomsDeclaration::count(),
            'totalVat' => CustomsDeclaration::sum('vat_amount'),
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        $data = $this->validated($request);
        $billIds = $data['bill_ids'] ?? [];
        unset($data['bill_ids']);

        $rate = (float) $data['vat_rate'];
        $base = (float) $data['customs_value'] + (float) $data['customs_duty'];
        $data['vat_amount'] = round($base * $rate / 100, 2);
        $data['created_by'] = Auth::id();

        $declaration = CustomsDeclaration::create($data);

        if (! empty($billIds)) {
            $declaration->bills()->sync($billIds);
        }

        $ledger->postCustomsDeclaration($declaration);

        return redirect()->route('app.customs-declarations.index')->with('status', __('Customs declaration recorded.'));
    }

    public function destroy(CustomsDeclaration $customsDeclaration, LedgerPostingService $ledger)
    {
        $ledger->reverse($customsDeclaration->company, 'customs_declaration', $customsDeclaration->id, __('Customs declaration deleted'));
        $customsDeclaration->delete();

        return redirect()->route('app.customs-declarations.index')->with('status', __('Customs declaration deleted.'));
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'declaration_number' => ['nullable', 'string', 'max:255'],
            'declaration_date' => ['required', 'date'],
            'port_of_entry' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'customs_value' => ['required', 'numeric', 'min:0'],
            'customs_duty' => ['nullable', 'numeric', 'min:0'],
            'vat_rate' => ['required', 'numeric', 'in:15,0'],
            'sadad_reference' => ['nullable', 'string', 'max:255'],
            'bill_ids' => ['nullable', 'array'],
            'bill_ids.*' => [Rule::exists('bills', 'id')->where('company_id', $companyId)],
        ]);
    }
}
