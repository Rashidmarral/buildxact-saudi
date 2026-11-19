<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subcontract_payments', function (Blueprint $table) {
            $table->foreignId('work_id')->nullable()->after('milestone_id')->constrained('subcontract_works')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subcontract_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_id');
        });
    }
};
