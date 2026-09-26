<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use Illuminate\Http\Request;

class JournalEntryApiController extends Controller
{
    public function index(Request $request)
    {
        $entries = JournalEntry::with('lines')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json($entries->through(fn (JournalEntry $entry) => $this->transform($entry)));
    }

    public function show(JournalEntry $journalEntry)
    {
        $journalEntry->load('lines.account');

        return response()->json($this->transform($journalEntry, withLines: true));
    }

    private function transform(JournalEntry $entry, bool $withLines = false): array
    {
        $data = [
            'id' => $entry->id,
            'entry_number' => $entry->entry_number,
            'entry_date' => $entry->entry_date,
            'source_type' => $entry->source_type,
            'source_id' => $entry->source_id,
            'description' => $entry->description,
        ];

        if ($withLines) {
            $data['lines'] = $entry->lines->map(fn ($line) => [
                'account_code' => $line->account?->code,
                'account_name' => $line->account?->name,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'memo' => $line->memo,
            ]);
        } else {
            $data['total_debit'] = $entry->totalDebit();
            $data['total_credit'] = $entry->totalCredit();
        }

        return $data;
    }
}
