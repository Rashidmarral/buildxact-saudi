<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Quotation;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;

/**
 * Audit finding MEDIUM-14: the client portal only ever handled invoices —
 * a quotation could only reach a client as an emailed PDF, with no way to
 * view it online or accept/reject it without calling or emailing back.
 * Mirrors PublicInvoiceController's no-login, id+public_token pattern,
 * with accept/reject standing in for "pay online".
 */
class PublicQuotationController extends Controller
{
    public function show(int $id, string $token)
    {
        $quotation = Quotation::withoutGlobalScopes()->with('items', 'client', 'bankAccount', 'company')->findOrFail($id);

        abort_unless(hash_equals($quotation->public_token, $token), 404);
        abort_unless($quotation->isPubliclyViewable(), 404);

        return view('public.quotation-show', [
            'quotation' => $quotation,
            'company' => $quotation->company,
            'doc' => $this->buildDoc($quotation),
            'template' => $quotation->company->defaultTemplateFor($quotation->type),
        ]);
    }

    public function downloadPdf(int $id, string $token, MpdfRenderer $renderer)
    {
        $quotation = Quotation::withoutGlobalScopes()->with('items', 'client', 'bankAccount', 'company')->findOrFail($id);

        abort_unless(hash_equals($quotation->public_token, $token), 404);
        abort_unless($quotation->isPubliclyViewable(), 404);

        $pdf = $renderer->render('documents.print.pdf', [
            'doc' => $this->buildDoc($quotation),
            'company' => $quotation->company,
            'template' => $quotation->company->defaultTemplateFor($quotation->type),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$quotation->quotation_number.'.pdf"',
        ]);
    }

    public function accept(Request $request, int $id, string $token)
    {
        $quotation = Quotation::withoutGlobalScopes()->findOrFail($id);

        abort_unless(hash_equals($quotation->public_token, $token), 404);

        if (! $quotation->isActionable()) {
            return back()->with('error', __('This quotation can no longer be accepted online.'));
        }

        // The e-signature is a lightweight acceptance record, not a legal
        // digital-signature scheme: a typed name plus a hand-drawn stroke
        // captured from the public accept form, alongside the IP and
        // timestamp, so a later dispute has something more than "the
        // status changed to accepted" to point to.
        $data = $request->validate([
            'accepted_by_name' => ['required', 'string', 'max:255'],
            'accepted_signature' => ['required', 'string', 'starts_with:data:image/png;base64,'],
        ]);

        $quotation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'accepted_by_name' => $data['accepted_by_name'],
            'accepted_signature' => $data['accepted_signature'],
            'accepted_ip' => $request->ip(),
        ]);

        AuditLog::record('quotation.client_accept', $quotation, __('Client :name accepted quotation :number', ['name' => $data['accepted_by_name'], 'number' => $quotation->quotation_number]));

        if ($quotation->created_by) {
            User::find($quotation->created_by)?->notify(new GenericNotification(
                title: __('Quotation accepted'),
                body: __(':number was accepted by the client.', ['number' => $quotation->quotation_number]),
                url: route('app.quotations.show', $quotation),
                icon: 'quotations',
            ));
        }

        return back()->with('status', __('Thank you — this quotation has been accepted.'));
    }

    public function reject(int $id, string $token)
    {
        $quotation = Quotation::withoutGlobalScopes()->findOrFail($id);

        abort_unless(hash_equals($quotation->public_token, $token), 404);

        if (! $quotation->isActionable()) {
            return back()->with('error', __('This quotation can no longer be declined online.'));
        }

        $quotation->update(['status' => 'rejected']);

        AuditLog::record('quotation.client_reject', $quotation, __('Client declined quotation :number', ['number' => $quotation->quotation_number]));

        if ($quotation->created_by) {
            User::find($quotation->created_by)?->notify(new GenericNotification(
                title: __('Quotation declined'),
                body: __(':number was declined by the client.', ['number' => $quotation->quotation_number]),
                url: route('app.quotations.show', $quotation),
                icon: 'quotations',
            ));
        }

        return back()->with('status', __('This quotation has been declined.'));
    }

    private function buildDoc(Quotation $quotation): array
    {
        $bankAccount = $quotation->bankAccount ?? $quotation->company->defaultBankAccount();

        return [
            'type_label' => $quotation->type === 'proforma' ? __('Proforma Invoice') : __('Quotation'),
            'type_label_ar' => $quotation->type === 'proforma' ? 'فاتورة أولية' : 'عرض سعر',
            'number' => $quotation->quotation_number,
            'date_label' => __('Issued'),
            'date' => $quotation->issue_date,
            'date2_label' => __('Valid until'),
            'date2_label_ar' => 'صالح حتى',
            'date2' => $quotation->expiry_date,
            'party_label' => __('To'),
            'party_label_ar' => 'العميل',
            'party' => $quotation->client,
            'lines' => $quotation->items,
            'subtotal' => $quotation->subtotal,
            'discount_total' => $quotation->discount_total,
            'vat_total' => $quotation->vat_total,
            'total' => $quotation->total,
            'bank_account' => $bankAccount,
            'salesperson' => $quotation->salesperson,
            'notes' => $quotation->notes,
        ];
    }
}
