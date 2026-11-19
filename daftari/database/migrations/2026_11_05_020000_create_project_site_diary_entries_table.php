<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_site_diary_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('weather', 100)->nullable();
            $table->unsignedInteger('manpower_count')->nullable();
            $table->text('work_done')->nullable();
            $table->text('materials_received')->nullable();
            $table->text('issues_delays')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_site_diary_entries');
    }
};
