<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->string('company_name')->nullable();
            $table->string('industry', 40)->nullable();
            $table->string('source', 40)->default('other');
            $table->text('message')->nullable();
            $table->string('status', 20)->default('lead');
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->timestamp('demo_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('source');
            $table->index('industry');
            $table->index('assigned_admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
