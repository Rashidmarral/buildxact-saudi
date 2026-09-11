<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('approval_rejection_reason');
            $table->string('accepted_by_name')->nullable()->after('accepted_at');
            $table->longText('accepted_signature')->nullable()->after('accepted_by_name');
            $table->string('accepted_ip', 45)->nullable()->after('accepted_signature');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'accepted_by_name', 'accepted_signature', 'accepted_ip']);
        });
    }
};
