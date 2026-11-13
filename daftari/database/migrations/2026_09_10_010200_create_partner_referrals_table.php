<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            // The Lead this referral produced (see Lead::SOURCES = 'referral'
            // and Site\LeadController::store reading ?ref=), so a referral's
            // whole CRM history — notes, stage, demo date — lives on the
            // existing Lead rather than being duplicated here.
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            // Set once the referral becomes a paying tenant, so commission
            // can be tied to a real subscription rather than guessed at.
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending'); // pending, qualified, converted, rejected
            // Never auto-computed and paid silently — an admin enters/
            // confirms this amount when approving the commission (see
            // Admin\PartnerController::updateReferral()), even though the
            // partner's effective rate is shown as a non-binding suggestion.
            $table->decimal('commission_amount', 10, 2)->nullable();
            $table->string('commission_status', 20)->default('unearned'); // unearned, approved, paid
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_referrals');
    }
};
