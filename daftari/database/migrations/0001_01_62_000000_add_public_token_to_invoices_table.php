<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('public_token', 40)->nullable()->unique()->after('id');
        });

        // Backfill existing invoices so every one of them — not just new
        // ones going forward — gets a shareable payable link.
        DB::table('invoices')->whereNull('public_token')->orderBy('id')->chunkById(200, function ($invoices) {
            foreach ($invoices as $invoice) {
                DB::table('invoices')->where('id', $invoice->id)->update(['public_token' => Str::random(40)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });
    }
};
