<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // 'feature' | 'limit'
            $table->string('key', 60); // e.g. 'whatsapp' (feature) or 'users' (limit)
            // Limits: numeric string, or NULL with is_unlimited=true for
            // "no cap at all" (distinct from "no override row exists").
            // Features: '1' or '0'.
            $table->string('value', 30)->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'type', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_overrides');
    }
};
