<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the existing polymorphic Attachment model double as a company
 * compliance-document store (CR, VAT certificate, National Address
 * certificate, etc.) without a separate table — document_type/expiry_date
 * stay null for the invoice/bill attachments that already use this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('document_type', 50)->nullable()->after('attachable_id');
            $table->date('expiry_date')->nullable()->after('mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'expiry_date']);
        });
    }
};
