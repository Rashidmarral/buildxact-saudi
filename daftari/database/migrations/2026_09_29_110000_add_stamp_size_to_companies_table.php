<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Width in px the stamp renders at on a downloaded/emailed PDF
            // (height always scales proportionally — see pdf.blade.php's
            // .stamp-img). Null keeps each layout's own sensible default.
            $table->unsignedSmallInteger('stamp_size')->nullable()->after('stamp_path');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('stamp_size');
        });
    }
};
