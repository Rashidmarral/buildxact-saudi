<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::withoutGlobalScopes()
            ->with('plan')
            ->join('companies', 'companies.id', '=', 'payments.company_id')
            ->select('payments.*', 'companies.name as company_name')
            ->latest('payments.paid_at')
            ->latest('payments.id')
            ->paginate(25);

        return view('admin.payments.index', compact('payments'));
    }
}
