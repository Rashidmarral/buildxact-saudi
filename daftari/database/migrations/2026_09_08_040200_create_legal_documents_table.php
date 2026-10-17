<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-editable legal documents (Terms, Privacy, Refund Policy, ...),
 * rendered publicly at /legal/{slug}. Distinct from the CmsSection
 * marketing-content system: these carry a review/publish lifecycle
 * (requires_legal_review, status) that marketing copy doesn't need, so
 * the public page can show an honest "pending legal review" banner
 * instead of silently presenting drafted text as a finished policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('title_en');
            $table->string('title_ar')->nullable();
            $table->longText('body_en')->nullable();
            $table->longText('body_ar')->nullable();
            $table->string('status', 20)->default('draft'); // draft, published
            $table->boolean('requires_legal_review')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
