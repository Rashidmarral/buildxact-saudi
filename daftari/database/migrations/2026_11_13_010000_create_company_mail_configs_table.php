<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "please login and verify each and everything" (Hamly ERP) — Hamly lets
 * each business configure its own outgoing mail server, so an invoice
 * email lands in a client's inbox from the company's own address/domain
 * instead of a shared platform one. Until now every outbound email in
 * this app (invoices, quotations, statements, reminders) went out
 * through the one platform-wide SMTP config (Admin > Platform Settings >
 * Email) regardless of which company triggered it — see
 * CompanyMailService for where this now applies, scoped to exactly the
 * "company talking to its own customer" emails, not platform-side ones
 * like team invites or Daftari's own subscription billing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_mail_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('smtp_host')->nullable();
            $table->unsignedInteger('smtp_port')->default(587);
            $table->string('smtp_encryption', 10)->default('tls'); // tls, ssl, none
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_mail_configs');
    }
};
