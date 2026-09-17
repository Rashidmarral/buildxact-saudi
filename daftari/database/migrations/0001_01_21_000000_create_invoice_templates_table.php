<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            // all, invoice, quotation, proforma, receipt_voucher, payment_voucher
            $table->string('document_type', 20)->default('all');
            $table->string('accent_color', 7)->default('#0f766e');
            $table->string('layout', 20)->default('minimal'); // boxed, bordered, minimal
            $table->boolean('show_logo')->default(true);
            $table->text('notes_en')->nullable();
            $table->text('notes_ar')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_templates');
    }
};
