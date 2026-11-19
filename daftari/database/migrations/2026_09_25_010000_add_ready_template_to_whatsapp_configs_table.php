<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_configs', function (Blueprint $table) {
            // A "your order/job is ready for pickup" message has a
            // different body shape (customer name, item/job description,
            // number — no amount, no pay link) than the invoice_notification
            // template above, so it needs its own Meta-approved template
            // rather than reusing template_name. Nullable: a company using
            // WhatsApp for invoices only doesn't have to set this up.
            $table->string('ready_template_name')->nullable()->after('template_language');
            $table->string('ready_template_language', 10)->default('en_US')->after('ready_template_name');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_configs', function (Blueprint $table) {
            $table->dropColumn(['ready_template_name', 'ready_template_language']);
        });
    }
};
