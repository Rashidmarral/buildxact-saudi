<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->string('type', 20)->default('department')->after('name_ar'); // department, project, branch
            $table->foreignId('parent_id')->nullable()->after('type')->constrained('cost_centers')->nullOnDelete();
        });

        Schema::create('cost_center_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_center_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 30); // invoice (extensible to bill, expense, ...)
            $table->unsignedBigInteger('entity_id');
            $table->decimal('allocation_percent', 5, 2)->default(100);
            $table->timestamps();

            $table->index(['company_id', 'entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_center_links');
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('type');
        });
    }
};
