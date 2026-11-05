<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kafeel_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('payment_date');
            // Who the money actually went to — a person (e.g. "Abdul
            // Majeed"), a service provider ("STC"), or the sponsor
            // directly ("Kafeel Account"). Free text, not a FK: these
            // recipients aren't company clients/suppliers/employees, just
            // names in a personal payment log.
            $table->string('paid_to', 150)->nullable();
            $table->string('category', 30)->default('other');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kafeel_payments');
    }
};
