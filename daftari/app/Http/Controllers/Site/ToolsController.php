<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;

class ToolsController extends Controller
{
    public function index()
    {
        return view('site.tools.index');
    }

    public function percentageCalculator()
    {
        return view('site.tools.percentage-calculator');
    }

    public function discountCalculator()
    {
        return view('site.tools.discount-calculator');
    }

    public function vatCalculator()
    {
        return view('site.tools.vat-calculator');
    }

    public function zakatCalculator()
    {
        return view('site.tools.zakat-calculator');
    }

    public function gosiCalculator()
    {
        return view('site.tools.gosi-calculator');
    }

    public function endOfServiceCalculator()
    {
        return view('site.tools.end-of-service-calculator');
    }

    public function zatcaPenaltyCalculator()
    {
        return view('site.tools.zatca-penalty-calculator');
    }

    public function invoiceGenerator()
    {
        return view('site.tools.invoice-generator');
    }

    public function quotationGenerator()
    {
        return view('site.tools.quotation-generator');
    }

    public function receiptVoucher()
    {
        return view('site.tools.receipt-voucher');
    }

    public function paymentVoucher()
    {
        return view('site.tools.payment-voucher');
    }

    public function glossary()
    {
        return view('site.glossary');
    }
}
