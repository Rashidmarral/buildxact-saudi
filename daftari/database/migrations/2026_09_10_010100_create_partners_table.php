<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            // Null until approved — a login is only ever created once an
            // admin has reviewed and accepted the application (see
            // Admin\PartnerController::approve()).
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('partner_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->string('company_name')->nullable();
            $table->text('message')->nullable();
            $table->string('referral_code', 20)->nullable()->unique();
            $table->string('status', 20)->default('pending'); // pending, invited, active, rejected, suspended
            // An admin may override the partner type's default commission
            // rule for one specific partner (e.g. a negotiated rate) —
            // never a hard-coded fallback, only ever set explicitly here.
            $table->string('commission_type_override', 10)->nullable();
            $table->decimal('commission_value_override', 10, 2)->nullable();
            $table->string('payout_method', 30)->nullable();
            $table->string('bank_iban', 40)->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('rejected_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
