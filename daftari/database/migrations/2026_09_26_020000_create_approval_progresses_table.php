<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_progresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->morphs('approvable');
            $table->unsignedTinyInteger('step_number');
            // Nullable + a denormalized name snapshot: if the role is
            // later renamed or deleted, this row still shows who was
            // asked to approve at the time the document was submitted.
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('role_name');
            $table->decimal('min_amount', 12, 2);
            $table->string('status', 20)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_progresses');
    }
};
