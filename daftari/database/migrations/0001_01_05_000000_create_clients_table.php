<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('client_code', 20)->nullable();
            $table->string('type', 20)->default('company'); // individual, company
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('contact_name')->nullable();
            $table->boolean('is_vat_registered')->default(true);
            $table->string('vat_number', 32)->nullable();
            $table->string('cr_number', 32)->nullable();
            $table->string('additional_id_type', 30)->nullable(); // national_id, iqama, passport, gcc_id
            $table->string('additional_id_number', 50)->nullable();
            $table->decimal('initial_balance', 12, 2)->default(0);
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('street_name')->nullable();
            $table->string('building_number', 20)->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'client_code']);
            $table->index(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
