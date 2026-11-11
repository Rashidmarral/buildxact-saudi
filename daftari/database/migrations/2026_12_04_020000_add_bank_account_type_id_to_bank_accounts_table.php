<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            // Nullable: a plain built-in "Bank"/"Cash" account (the only two
            // that existed before this column) leaves this null and keeps
            // using its own `type` column directly. Only set when the
            // account was assigned one of the company's custom types —
            // `type` itself still always holds the resolved 'bank'/'cash'
            // nature (see BankAccountController::validated()), so every
            // existing nature-based check elsewhere in the app keeps
            // working unchanged.
            $table->foreignId('bank_account_type_id')->nullable()->after('type')->constrained('bank_account_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_type_id');
        });
    }
};
