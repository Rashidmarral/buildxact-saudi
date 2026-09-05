<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Certificates/compliance documents (CR certificate, VAT registration,
     * ZATCA-related accreditation, etc.) a super admin uploads for the
     * platform itself — shown publicly on the marketing site's
     * Certificates page. Not tied to any company: this is Daftari's own
     * documentation, distinct from a company's own Attachment records.
     */
    public function up(): void
    {
        Schema::create('platform_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_documents');
    }
};
