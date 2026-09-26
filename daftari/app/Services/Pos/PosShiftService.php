<?php

namespace App\Services\Pos;

use App\Models\PosRegister;
use App\Models\PosShift;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Till-session lifecycle: open a shift with a starting cash float, close
 * it with a physical cash count reconciled against what the shift's cash
 * sales imply should be in the drawer (the Z-report's core number).
 */
class PosShiftService
{
    public function open(PosRegister $register, float $openingCash): PosShift
    {
        if ($register->openShift()) {
            throw new RuntimeException(__('This register already has an open shift.'));
        }

        return PosShift::create([
            'register_id' => $register->id,
            'opened_by' => Auth::id(),
            'opened_at' => now(),
            'opening_cash' => $openingCash,
            'status' => 'open',
        ]);
    }

    public function close(PosShift $shift, float $countedCash): PosShift
    {
        if ($shift->status !== 'open') {
            throw new RuntimeException(__('This shift is already closed.'));
        }

        $expectedCash = round((float) $shift->opening_cash + $shift->cashSalesTotal(), 2);

        $shift->update([
            'status' => 'closed',
            'closed_by' => Auth::id(),
            'closed_at' => now(),
            'counted_cash' => $countedCash,
            'expected_cash' => $expectedCash,
            'cash_difference' => round($countedCash - $expectedCash, 2),
        ]);

        return $shift;
    }
}
