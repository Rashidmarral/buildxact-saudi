<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_payment_terms_days')->nullable()->after('invoice_approval_threshold');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_terms_days')->nullable()->after('initial_balance');
        });

        Schema::create('invoice_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description')->nullable();
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_installments');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('payment_terms_days');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('default_payment_terms_days');
        });
    }
};
