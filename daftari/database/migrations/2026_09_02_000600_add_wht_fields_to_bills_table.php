<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->foreignId('wht_rate_id')->nullable()->constrained('wht_rates')->nullOnDelete();
            $table->decimal('wht_amount', 12, 2)->default(0);
            $table->boolean('wht_withheld')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wht_rate_id');
            $table->dropColumn(['wht_amount', 'wht_withheld']);
        });
    }
};
