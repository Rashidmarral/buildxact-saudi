<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->foreignId('from_warehouse_id')->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->constrained('warehouses');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->date('date');
            $table->string('status', 20)->default('recorded'); // recorded, reversed
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
