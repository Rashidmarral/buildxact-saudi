<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_account_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // What this custom type actually behaves like internally — the
            // GL code range (BankAccount::glAccount()) and the shared
            // Default Cash/Default Bank mapping lookup
            // (BankAccountController::transferGlOpeningBalance()) both still
            // branch on exactly these two values. A custom type is purely a
            // user-facing label layered on top of one of them, never a
            // third accounting treatment.
            $table->string('nature', 10); // cash, bank
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_account_types');
    }
};
