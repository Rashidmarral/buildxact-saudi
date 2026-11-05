<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "can we do this in our accounting system" — a client's project manager
 * asked for a completed-works invoice that credits part of an earlier
 * advance (already fully paid, referenced by its own invoice number)
 * against the new invoice, split by line item, while keeping the rest of
 * that same advance in reserve for a later invoice. The existing
 * PrepaymentInvoice feature can't do this: it's a single non-itemized
 * amount that can only be applied once, in full, to one invoice, and
 * only if the advance itself was created through that dedicated feature
 * — not an option here, since the advance in question (the operator's
 * own words: "i have issued invoice as regular invoice not prepayment
 * invoice") was issued as an ordinary Invoice.
 *
 * An AdvanceCredit is a company-scoped wrapper around "some amount of
 * money was received in advance" — optionally pointing at the invoice
 * that was actually issued for it (source_invoice_id; nullable, since
 * the source document might predate this feature or not be in the
 * system at all), scoped to a client (and optionally a project) so the
 * invoice-side "apply advance credit" picker only ever offers credits
 * that actually belong to the invoice's own client.
 *
 * Deliberately separate from PrepaymentInvoice/ZatcaXmlGenerator's
 * invoice-type-code-386 mechanism — that remains the correct path for a
 * genuine ZATCA Phase 2 advance-payment invoice created from the start
 * through the dedicated feature. This is a bookkeeping/presentation
 * layer for exactly the situation described above: an advance that
 * wasn't issued that way, reconciled after the fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_credits');
    }
};
