<?php

namespace Database\Seeders;

use App\Models\SalesTarget;
use Illuminate\Database\Seeder;

/**
 * A suggested starting 90-day (13-week) plan — ramping from "build the
 * pipeline" to "close what you built" — that the operator edits from
 * Admin -> Sales Center to match their own reality. Idempotent per week
 * number: never overwrites a week an admin has since edited.
 */
class SalesTargetSeeder extends Seeder
{
    public function run(): void
    {
        $plan = [
            1 => [10, 2, 0],
            2 => [12, 3, 0],
            3 => [15, 4, 1],
            4 => [15, 5, 1],
            5 => [18, 5, 2],
            6 => [18, 6, 2],
            7 => [20, 7, 3],
            8 => [20, 7, 3],
            9 => [22, 8, 4],
            10 => [22, 8, 4],
            11 => [25, 9, 5],
            12 => [25, 9, 5],
            13 => [25, 10, 6],
        ];

        foreach ($plan as $week => [$leads, $demos, $won]) {
            if (SalesTarget::where('week_number', $week)->exists()) {
                continue;
            }

            SalesTarget::create([
                'week_number' => $week,
                'new_leads_target' => $leads,
                'demos_target' => $demos,
                'won_target' => $won,
            ]);
        }
    }
}
