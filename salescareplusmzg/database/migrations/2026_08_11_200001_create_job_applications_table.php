<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_title');
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('cover_message')->nullable();
            $table->string('resume_path')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
