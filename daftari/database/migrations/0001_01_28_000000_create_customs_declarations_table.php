<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customs_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('declaration_number')->nullable();
            $table->date('declaration_date');
            $table->string('port_of_entry')->nullable();
            $table->decimal('customs_value', 14, 2)->default(0);
            $table->decimal('customs_duty', 14, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(15.00);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->string('sadad_reference')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'declaration_date']);
        });

        Schema::create('customs_declaration_bill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customs_declaration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['customs_declaration_id', 'bill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customs_declaration_bill');
        Schema::dropIfExists('customs_declarations');
    }
};
