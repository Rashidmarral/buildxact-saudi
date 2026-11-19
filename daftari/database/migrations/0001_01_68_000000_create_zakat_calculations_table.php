<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zakat_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_end_date');
            // Hijri years run ~354 days, so GAZT's own guidance uses a
            // slightly higher rate (2.5775%) for a company that Zakats on
            // the Gregorian calendar instead, to keep the effective annual
            // burden equivalent — same distinction the existing marketing
            // Zakat calculator already offers.
            $table->string('rate_type', 10)->default('hijri');
            // Total equity as of period_end_date, pulled from the real
            // chart of accounts at the moment this calculation was
            // created — stored as a snapshot so a later transaction
            // doesn't retroactively change a saved calculation's numbers.
            $table->decimal('equity_amount', 14, 2)->default(0);
            // The next three are manual inputs — this app has no fixed-
            // asset register or current/non-current liability
            // classification yet (a company's own chart of accounts can't
            // reliably answer "is this a long-term loan or a fixed
            // asset" on its own), so the company enters them directly.
            $table->decimal('long_term_liabilities', 14, 2)->default(0);
            $table->decimal('net_fixed_assets', 14, 2)->default(0);
            $table->decimal('other_deductions', 14, 2)->default(0);
            $table->decimal('zakat_base', 14, 2);
            $table->decimal('zakat_due', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zakat_calculations');
    }
};
