@extends('layouts.site')

@section('title', __('Glossary') . ' · Daftari')

@section('content')
<section class="mx-auto max-w-4xl px-6 pt-16 pb-8">
    <h1 class="text-4xl font-extrabold text-slate-900">{{ __('Accounting & e-invoicing glossary') }}</h1>
    <p class="mt-4 text-lg text-slate-600">{{ __('Plain-language definitions for the accounting and ZATCA e-invoicing terms you\'ll come across running a business in Saudi Arabia.') }}</p>
</section>

<section class="mx-auto max-w-4xl px-6 pb-20">
    @foreach ([
        __('Accounting basics') => [
            [__('Chart of accounts'), __('The full list of categories (assets, liabilities, income, expenses) a business records its transactions under.')],
            [__('General ledger'), __('The complete record of every financial transaction a business has made, organized by account.')],
            [__('Journal entry'), __('A single recorded transaction, with at least one debit and one matching credit.')],
            [__('Debit / credit'), __('The two sides of every accounting entry — debits and credits always balance to zero for a transaction.')],
            [__('Trial balance'), __('A summary listing every account balance, used to check that total debits equal total credits.')],
            [__('Accrual accounting'), __('Recording income and expenses when they are earned or incurred, not when cash actually changes hands.')],
            [__('Cash accounting'), __('Recording income and expenses only when cash is actually received or paid.')],
            [__('Fiscal year'), __('The 12-month period a business uses for its financial reporting and tax filing.')],
            [__('Accounts receivable'), __('Money owed to a business by its customers for goods or services already delivered.')],
            [__('Accounts payable'), __('Money a business owes to its suppliers for goods or services already received.')],
            [__('Balance sheet'), __("A snapshot of what a business owns, owes, and is worth at a specific point in time.")],
            [__('Income statement'), __('A summary of revenue, costs, and profit or loss over a period of time.')],
        ],
        __('E-invoicing (ZATCA)') => [
            [__('E-invoice'), __('An invoice generated, stored, and (from Phase 2) exchanged in a structured electronic format, as required by ZATCA.')],
            [__('Simplified tax invoice'), __('An e-invoice typically issued for B2C transactions, requiring a QR code but not always real-time clearance.')],
            [__('Standard tax invoice'), __('An e-invoice typically issued for B2B transactions, cleared with ZATCA in real time under Phase 2.')],
            [__('QR code'), __('A scannable code on a tax invoice that encodes key details like seller name, VAT number, and totals for quick verification.')],
            [__('Cryptographic stamp'), __('A digital seal applied to a Phase-2 e-invoice that proves it hasn\'t been altered after issuance.')],
            [__('Clearance'), __('The Phase-2 process of submitting a standard invoice to ZATCA for approval before it is sent to the buyer.')],
            [__('Reporting'), __('The Phase-2 process of submitting a simplified invoice to ZATCA shortly after it has already been issued to the buyer.')],
            [__('UBL / XML format'), __('The structured data format ZATCA requires e-invoices to be generated and exchanged in.')],
            [__('ZATCA'), __('The Zakat, Tax and Customs Authority — the Saudi government body responsible for VAT, Zakat, customs, and e-invoicing regulation.')],
            [__('Fatoora'), __("ZATCA's e-invoicing program name, covering both Phase 1 (generation) and Phase 2 (integration).")],
        ],
        __('VAT & taxes') => [
            [__('VAT'), __('Value Added Tax — a consumption tax added to most goods and services in Saudi Arabia, currently at a standard rate of 15%.')],
            [__('Output VAT'), __('The VAT a business collects from its customers on sales.')],
            [__('Input VAT'), __('The VAT a business pays on its own purchases, which can usually be reclaimed against output VAT.')],
            [__('Zero-rated supply'), __('A sale that is taxable but charged at 0% VAT, such as qualifying exports.')],
            [__('Exempt supply'), __('A sale that falls outside the VAT system entirely — no VAT is charged and no input VAT can be reclaimed against it.')],
            [__('Reverse charge'), __('A mechanism where the buyer, not the seller, accounts for VAT on certain imported services.')],
            [__('Withholding tax'), __('Tax withheld at source on certain payments to non-residents and remitted directly to the tax authority.')],
            [__('Zakat'), __('An Islamic wealth levy, typically 2.5% of zakatable assets annually, administered by ZATCA for Saudi and GCC-owned businesses.')],
            [__('Tax period'), __('The regular interval (usually monthly or quarterly) a business must file its VAT return for.')],
        ],
        __('Banking & reconciliation') => [
            [__('Bank reconciliation'), __('Matching your accounting records against your bank statement to confirm they agree.')],
            [__('Bank statement'), __('An official record from your bank listing every transaction on an account over a period.')],
            [__('Outstanding cheque'), __('A cheque that has been issued and recorded but hasn\'t yet cleared the bank.')],
            [__('Petty cash'), __('A small amount of physical cash kept on hand for minor, everyday business expenses.')],
        ],
        __('Purchasing & inventory') => [
            [__('Purchase order'), __('A formal request to a supplier confirming what you intend to buy, at what price, before you\'re invoiced.')],
            [__('Supplier bill'), __("An invoice a supplier sends you for goods or services they've provided.")],
            [__('Debit note'), __('A document recording an increase in what you owe a supplier — often for a price or quantity correction.')],
            [__('Credit note'), __('A document recording a reduction in what a customer owes you, usually issued against a returned or corrected invoice.')],
            [__('Inventory'), __('The stock of goods a business holds for sale or use in producing what it sells.')],
            [__('Reorder point'), __('The stock level at which a business should place a new purchase order to avoid running out.')],
        ],
        __('Reports & analysis') => [
            [__('Cash flow statement'), __('A report showing how cash moved in and out of a business over a period.')],
            [__('Profit margin'), __('The percentage of revenue that remains as profit after costs are deducted.')],
            [__('Cost center'), __('A department, branch, or activity that costs are tracked against, without necessarily generating revenue directly.')],
            [__('Aging report'), __('A breakdown of unpaid invoices or bills by how long they\'ve been outstanding.')],
            [__('Break-even point'), __('The sales level at which total revenue exactly covers total costs — beyond it, a business turns a profit.')],
        ],
    ] as $category => $terms)
        <div class="mb-12">
            <h2 class="inline-block rounded-lg bg-slate-900 text-white text-sm font-semibold px-4 py-2 mb-4">{{ $category }}</h2>
            <dl class="divide-y divide-slate-100 border-t border-slate-100">
                @foreach ($terms as [$term, $definition])
                    <div class="py-4">
                        <dt class="font-semibold text-slate-900">{{ $term }}</dt>
                        <dd class="mt-1 text-sm text-slate-600">{{ $definition }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endforeach
</section>

@include('site.tools.partials.cta')
@endsection
