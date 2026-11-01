<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\Attachment;
use App\Models\Bill;
use App\Models\BankTransfer;
use App\Models\Company;
use App\Models\CompanyLetter;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\KafeelPayment;
use App\Models\MachineryAsset;
use App\Models\MachineryHireInContract;
use App\Models\MachineryRentalContract;
use App\Models\PaymentVoucher;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\ReceiptVoucher;
use Illuminate\Http\Request;

/**
 * "make a proper document saving module like, client po, suplier bill,
 * company invoices, expenses bill etc that are required for an audit
 * at the end of year" — every real transaction type already stores its
 * own attachments against the same polymorphic Attachment table (see
 * PurchaseOrder/Bill/Invoice/Quotation/ReceiptVoucher/PaymentVoucher/
 * BankTransfer/MachineryAsset/.../Expense, the last one new — see
 * ExpenseController::storeAttachment()). What was missing wasn't
 * storage, it was a single place to actually FIND everything at
 * year-end: this reads across every one of those types at once,
 * filterable by document kind and date, exportable, so nothing has to
 * be hunted down screen by screen when an auditor asks for it.
 */
class DocumentArchiveController extends Controller
{
    use ExportsCsv, ResolvesPerPage;

    /** @var array<string, class-string> */
    private const TYPES = [
        'bill' => Bill::class,
        'invoice' => Invoice::class,
        'quotation' => Quotation::class,
        'purchase_order' => PurchaseOrder::class,
        'expense' => Expense::class,
        'receipt_voucher' => ReceiptVoucher::class,
        'payment_voucher' => PaymentVoucher::class,
        'bank_transfer' => BankTransfer::class,
        'machinery_asset' => MachineryAsset::class,
        'machinery_rental_contract' => MachineryRentalContract::class,
        'machinery_hire_in_contract' => MachineryHireInContract::class,
        'company_letter' => CompanyLetter::class,
        'kafeel_payment' => KafeelPayment::class,
        'company' => Company::class,
    ];

    private const TYPE_LABELS = [
        'bill' => 'Supplier Bill',
        'invoice' => 'Company Invoice',
        'quotation' => 'Quotation',
        'purchase_order' => 'Client / Purchase Order',
        'expense' => 'Expense',
        'receipt_voucher' => 'Receipt Voucher',
        'payment_voucher' => 'Payment Voucher',
        'bank_transfer' => 'Bank Transfer',
        'machinery_asset' => 'Machinery Asset',
        'machinery_rental_contract' => 'Rental Contract',
        'machinery_hire_in_contract' => 'Hired-in Equipment',
        'company_letter' => 'Letter / Agreement',
        'kafeel_payment' => 'Kafeel Payment',
        'company' => 'Company Compliance Document',
    ];

    public function index(Request $request)
    {
        $query = Attachment::with(['attachable', 'uploader'])->orderByDesc('created_at');

        $type = $request->query('type');
        if ($type && isset(self::TYPES[$type])) {
            $query->where('attachable_type', self::TYPES[$type]);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }
        if ($search = $request->query('search')) {
            $query->where('original_name', 'like', "%{$search}%");
        }

        if ($request->query('export') === 'csv') {
            return $this->csvResponse(
                'document-archive.csv',
                [__('Date'), __('Document type'), __('Reference'), __('File name'), __('Uploaded by'), __('Size')],
                $query->get()->map(fn (Attachment $attachment) => [
                    $attachment->created_at->format('Y-m-d'),
                    __(self::TYPE_LABELS[$this->typeKey($attachment)] ?? $this->typeKey($attachment)),
                    $this->referenceLabel($attachment),
                    $attachment->original_name,
                    $attachment->uploader?->name,
                    $attachment->humanSize(),
                ])
            );
        }

        $attachments = $query->paginate($this->resolvePerPage($request))->withQueryString();

        foreach ($attachments as $attachment) {
            $attachment->reference_label = $this->referenceLabel($attachment);
            $attachment->reference_url = $this->referenceUrl($attachment);
        }

        return view('user.documents.index', [
            'attachments' => $attachments,
            'types' => self::TYPES,
            'typeLabels' => self::TYPE_LABELS,
        ]);
    }

    private function typeKey(Attachment $attachment): ?string
    {
        return array_search($attachment->attachable_type, self::TYPES, true) ?: null;
    }

    public function referenceLabel(Attachment $attachment): string
    {
        $model = $attachment->attachable;

        if (! $model) {
            return __('(source deleted)');
        }

        return match ($attachment->attachable_type) {
            Bill::class => $model->bill_number,
            Invoice::class => $model->invoice_number,
            Quotation::class => $model->quotation_number,
            PurchaseOrder::class => $model->po_number,
            Expense::class => $model->description,
            ReceiptVoucher::class, PaymentVoucher::class => $model->voucher_number,
            BankTransfer::class => __('Transfer #:id', ['id' => $model->id]),
            MachineryAsset::class => $model->asset_code,
            MachineryRentalContract::class, MachineryHireInContract::class => $model->contract_number,
            CompanyLetter::class => $model->reference_number ?: $model->title,
            KafeelPayment::class => $model->description ?: $model->paid_to,
            Company::class => __(\App\Http\Controllers\User\SettingsController::DOCUMENT_TYPES[$attachment->document_type] ?? $attachment->document_type ?? __('Company document')),
            default => (string) $model->id,
        };
    }

    public function referenceUrl(Attachment $attachment): ?string
    {
        $model = $attachment->attachable;

        if (! $model) {
            return null;
        }

        return match ($attachment->attachable_type) {
            Bill::class => route('app.bills.show', $model),
            Invoice::class => route('app.invoices.show', $model),
            Quotation::class => route('app.quotations.show', $model),
            PurchaseOrder::class => route('app.purchase-orders.show', $model),
            Expense::class => route('app.expenses.edit', $model),
            ReceiptVoucher::class => route('app.receipt-vouchers.show', $model),
            PaymentVoucher::class => route('app.payment-vouchers.show', $model),
            BankTransfer::class => route('app.bank-transfers.show', $model),
            MachineryAsset::class => route('app.machinery.assets.show', $model),
            MachineryRentalContract::class => route('app.machinery.rental-contracts.show', $model),
            MachineryHireInContract::class => route('app.machinery.hire-in-contracts.show', $model),
            CompanyLetter::class => route('app.machinery.letters.edit', $model),
            KafeelPayment::class => route('app.kafeel-payments.edit', $model),
            Company::class => route('app.settings.index'),
            default => null,
        };
    }
}
