<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "app have an ability to close records of supplier and client on
 * monthly/quarterly basis" (task #158) — a per-party mirror of
 * Company::accounting_lock_date: transactions dated on or before this
 * date cannot be posted/reversed against that client/supplier. Separate
 * column per party (not a shared polymorphic table) since both models
 * already carry their own simple date columns this way
 * (next_follow_up_date, last_contacted_at) and a lock check needs to be
 * a single indexed column read, not a join, on every posting call.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->date('lock_date')->nullable()->after('credit_hold_reason');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->date('lock_date')->nullable()->after('last_contacted_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('lock_date');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('lock_date');
        });
    }
};
