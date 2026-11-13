<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "add more... Payment Mode as GL-mapped dimension" — today a POS/
 * restaurant/repair-shop sale's tender (cash/card/other/loyalty_card)
 * posts to one of three accounts hardcoded in LedgerPostingService.
 * A PaymentMode row makes that mapping company-configurable (e.g. a
 * second card account for a different processor) and lets a company
 * add brand new named tender types beyond the original three, each
 * with its own GL account — the missing piece behind both a Payment
 * Mode Statement/Balances report and Card/POS Settlement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->enum('category', ['cash', 'card', 'bank_transfer', 'cheque', 'other', 'loyalty_card'])->default('other');
            $table->foreignId('linked_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_modes');
    }
};
