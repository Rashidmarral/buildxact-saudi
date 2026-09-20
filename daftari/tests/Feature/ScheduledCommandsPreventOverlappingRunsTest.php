<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Security audit finding M-10: only backup:run used withoutOverlapping().
 * An overrunning recurring-document or ZATCA-sync command left on the
 * same schedule at its next tick would duplicate whatever it creates or
 * posts, not just waste work. Every command-based scheduled event now
 * guards against that (the one Schedule::call() heartbeat is exempt —
 * setting a timestamp twice is harmless).
 */
class ScheduledCommandsPreventOverlappingRunsTest extends TestCase
{
    public function test_every_scheduled_artisan_command_prevents_overlapping_runs(): void
    {
        $schedule = $this->app->make(Schedule::class);

        $commandEvents = array_filter(
            $schedule->events(),
            fn ($event) => ! ($event instanceof \Illuminate\Console\Scheduling\CallbackEvent)
        );

        $this->assertNotEmpty($commandEvents);

        foreach ($commandEvents as $event) {
            $this->assertTrue(
                $event->withoutOverlapping,
                "Scheduled command [{$event->command}] does not guard against overlapping runs."
            );
        }
    }
}
