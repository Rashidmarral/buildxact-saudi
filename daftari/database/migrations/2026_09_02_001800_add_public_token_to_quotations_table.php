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
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('public_token', 40)->nullable()->unique()->after('id');
        });

        // Backfill existing quotations so every one of them — not just new
        // ones going forward — gets a shareable client-portal link.
        DB::table('quotations')->whereNull('public_token')->orderBy('id')->chunkById(200, function ($quotations) {
            foreach ($quotations as $quotation) {
                DB::table('quotations')->where('id', $quotation->id)->update(['public_token' => Str::random(40)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });
    }
};
