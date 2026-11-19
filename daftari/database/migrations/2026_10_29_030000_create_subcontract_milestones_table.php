<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The milestone-wise payment schedule — "20% advance, 30%, 30%, 15% and
 * 5% after handover and approval of the project" is just the default
 * seed; percentages are editable per subcontract since not every trade
 * uses the same schedule. requires_handover_approval marks the final
 * release milestone (the 5%) so the module can withhold it until
 * approved_by/approved_at are set, instead of letting it look payable
 * the moment every other milestone is funded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcontract_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subcontract_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->string('name');
            $table->decimal('percentage', 5, 2);
            $table->decimal('amount', 14, 2)->default(0);
            $table->boolean('requires_handover_approval')->default(false);
            $table->string('status', 20)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcontract_milestones');
    }
};
