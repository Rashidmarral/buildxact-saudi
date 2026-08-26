<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_configs', function (Blueprint $table) {
            $table->id();
            // One row per company — each company sends from its own SMS
            // gateway account, same as WhatsApp/payment gateway credentials.
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('app_sid');
            $table->string('sender_id')->default('Daftari');
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_configs');
    }
};
