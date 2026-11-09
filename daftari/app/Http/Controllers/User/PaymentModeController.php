<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\PaymentMode;
use App\Models\PosPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * "add more... Payment Mode as GL-mapped dimension" — lets a company
 * repoint any tender type's GL postings at a different account (e.g. a
 * second card terminal settling to its own bank account) and add brand
 * new named tender types beyond the original cash/card/other/loyalty
 * card set, each with its own account. See PaymentMode::seedDefaults()
 * and LedgerPostingService::postPosSale() for how this flows through to
 * actual GL entries.
 */
class PaymentModeController extends Controller
{
    public function index()
    {
        $company = Auth::user()->company;
        PaymentMode::seedDefaults($company->id);

        return view('user.payment-modes.index', [
            'paymentModes' => PaymentMode::where('company_id', $company->id)->orderBy('sort_order')->orderBy('name')->get(),
            'accountOptions' => $this->accountOptions(),
            'categories' => $this->categoryLabels(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        PaymentMode::create($data);

        return back()->with('status', __('Payment mode added.'));
    }

    public function update(Request $request, PaymentMode $paymentMode)
    {
        $data = $this->validated($request, $paymentMode);

        $paymentMode->update($data);

        return back()->with('status', __('Payment mode updated.'));
    }

    public function destroy(PaymentMode $paymentMode)
    {
        if (PosPayment::where('company_id', $paymentMode->company_id)->where('method', $paymentMode->code)->exists()) {
            return back()->withErrors(['payment_mode' => __('This payment mode has sales recorded against it and cannot be deleted. Deactivate it instead.')]);
        }

        $paymentMode->delete();

        return back()->with('status', __('Payment mode deleted.'));
    }

    /**
     * A payment mode's code is the exact string a checkout form submits
     * as payments.*.method (see PosController/RestaurantOrderController/
     * RepairJobController) — the four defaults match the hardcoded
     * values the POS terminal's Cash/Card buttons already send, so
     * changing an existing mode's code would silently stop matching
     * them. It's therefore only settable when creating a brand new
     * mode, never when editing one — the edit form has no code field
     * at all, so it's simply absent from the request and left alone.
     */
    private function validated(Request $request, ?PaymentMode $paymentMode = null): array
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'code' => [
                $paymentMode ? 'sometimes' : 'required', 'string', 'max:40', 'alpha_dash',
                Rule::unique('payment_modes', 'code')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($paymentMode?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys($this->categoryLabels()))],
            'linked_account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->where('type', 'asset')),
            ],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function accountOptions()
    {
        return Account::where('company_id', Auth::user()->company_id)
            ->where('type', 'asset')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    private function categoryLabels(): array
    {
        return [
            'cash' => __('Cash'),
            'card' => __('Card'),
            'bank_transfer' => __('Bank transfer'),
            'cheque' => __('Cheque'),
            'other' => __('Other'),
            'loyalty_card' => __('Loyalty card'),
        ];
    }
}
