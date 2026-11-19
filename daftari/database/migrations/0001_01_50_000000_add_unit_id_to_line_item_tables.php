<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every user-facing line-item table gets a nullable unit_id (the unit
     * that line was actually sold/purchased in — may differ from the
     * item's base unit, e.g. selling by the Bag when stock is tracked by
     * the Ton). Credit note and purchase return lines get the column too
     * so it can be copied from the source line without a separate picker.
     */
    private array $tables = [
        'invoice_items' => 'item_id',
        'quotation_items' => 'item_id',
        'bill_items' => 'item_id',
        'purchase_order_items' => 'item_id',
        'credit_note_items' => 'invoice_item_id',
        'purchase_return_items' => 'bill_item_id',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName => $afterColumn) {
            Schema::table($tableName, function (Blueprint $table) use ($afterColumn) {
                $table->foreignId('unit_id')->nullable()->after($afterColumn)->constrained('units')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName => $afterColumn) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('unit_id');
            });
        }
    }
};
