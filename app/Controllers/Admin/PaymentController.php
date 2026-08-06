<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Payment;

class PaymentController extends Controller
{
    public function index(): void
    {
        $payments = Payment::query(
            'SELECT pay.*, c.name AS company_name FROM payments pay JOIN companies c ON c.id = pay.company_id ORDER BY pay.created_at DESC'
        )->fetchAll();
        $total = array_sum(array_map(fn($p) => (float) $p['amount'], array_filter($payments, fn($p) => $p['status'] === 'paid')));

        $this->view('admin/payments/index', ['pageTitle' => 'Payments', 'payments' => $payments, 'total' => $total], 'layouts/admin');
    }
}
