<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('parent_item_id')->nullable()->after('is_kit')->constrained('items')->nullOnDelete();
            $table->string('variant_label', 100)->nullable()->after('parent_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_item_id');
            $table->dropColumn('variant_label');
        });
    }
};
