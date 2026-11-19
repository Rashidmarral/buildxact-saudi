<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "if some companies order receive spare parts order and they have
 * deliver it to client and companies can issue delivery note for the
 * customers as well" — proof-of-delivery for goods shipped to a client,
 * either standalone (pick a client, add lines by hand) or generated from
 * an existing invoice (invoice_id set, each line's invoice_item_id ties
 * back to the exact invoice line it's fulfilling — see
 * DeliveryNoteService::remainingQuantityFor(), which sums already-issued,
 * non-cancelled delivery note quantities per invoice line so a company
 * can ship an order across more than one partial delivery without ever
 * shipping more than was actually invoiced).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('delivery_note_number');
            $table->string('status', 20)->default('issued'); // issued, cancelled
            $table->date('issue_date');
            $table->string('driver_name')->nullable();
            $table->string('vehicle_number')->nullable();
            $table->string('received_by_name')->nullable();
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'delivery_note_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('delivery_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_items');
        Schema::dropIfExists('delivery_notes');
    }
};
