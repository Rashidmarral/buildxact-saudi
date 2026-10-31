<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('type', 20)->default('company')->after('supplier_code');
            $table->string('contact_name')->nullable()->after('name_ar');
            $table->decimal('initial_balance', 12, 2)->default(0)->after('cr_number');
            $table->string('mobile', 30)->nullable()->after('phone');
            $table->string('address_line_2')->nullable()->after('address');
            $table->string('district')->nullable()->after('address_line_2');
            $table->string('postal_code', 20)->nullable()->after('city');
            $table->string('state')->nullable()->after('postal_code');
            $table->string('country')->nullable()->after('state');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->renameColumn('address', 'address_line_1');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->renameColumn('address_line_1', 'address');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'type', 'contact_name', 'initial_balance', 'mobile',
                'address_line_2', 'district', 'postal_code', 'state', 'country',
            ]);
        });
    }
};
