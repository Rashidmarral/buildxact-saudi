<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Print templates used to read $line->item?->name_ar / ->description live
 * off the current Item record, so editing a product's Arabic name or
 * description after an invoice/quotation/bill/PO/credit-note/debit-note was
 * issued silently rewrote how that historical document displayed. These two
 * columns freeze both values at line-creation time, the same way
 * `description` already freezes the English name/line text.
 */
return new class extends Migration
{
    private array $directItemTables = ['invoice_items', 'quotation_items', 'bill_items', 'purchase_order_items'];

    public function up(): void
    {
        foreach (array_merge($this->directItemTables, ['credit_note_items', 'purchase_return_items']) as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('name_ar')->nullable()->after('description');
                $table->text('item_description')->nullable()->after('name_ar');
            });
        }

        // Best-effort backfill for existing rows, which predate these
        // columns — there is no historical snapshot to recover, so this
        // copies whatever the linked Item currently holds (matching what
        // those documents already displayed up to this point). Done as
        // per-row updates rather than a join-update, since SQLite (used in
        // tests) can't pull SET values from a joined table.
        foreach ($this->directItemTables as $table) {
            DB::table('items')->select('id', 'name_ar', 'description')->orderBy('id')
                ->chunk(500, function ($items) use ($table) {
                    foreach ($items as $item) {
                        DB::table($table)->where('item_id', $item->id)->update([
                            'name_ar' => $item->name_ar,
                            'item_description' => $item->description,
                        ]);
                    }
                });
        }

        DB::table('invoice_items')->select('id', 'name_ar', 'item_description')->orderBy('id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('credit_note_items')->where('invoice_item_id', $row->id)->update([
                        'name_ar' => $row->name_ar,
                        'item_description' => $row->item_description,
                    ]);
                }
            });

        DB::table('bill_items')->select('id', 'name_ar', 'item_description')->orderBy('id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('purchase_return_items')->where('bill_item_id', $row->id)->update([
                        'name_ar' => $row->name_ar,
                        'item_description' => $row->item_description,
                    ]);
                }
            });
    }

    public function down(): void
    {
        foreach (array_merge($this->directItemTables, ['credit_note_items', 'purchase_return_items']) as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['name_ar', 'item_description']);
            });
        }
    }
};
