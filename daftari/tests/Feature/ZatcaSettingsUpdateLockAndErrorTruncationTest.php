<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Low-severity audit findings: Company::switchZatcaEnvironment() had no
 * row lock against a double-submitted settings save (narrow, single-
 * tenant-only race — see ZatcaController::updateSettings()'s own
 * lockForUpdate() now), and raw ZATCA upstream error bodies were flashed
 * into the browser verbatim on OTP/CSID-exchange failures.
 *
 * Source-inspection rather than a concurrency test, the same way
 * ZatcaSubmissionHashChainLockingTest documents that true concurrent-lock
 * behavior isn't observable via a single-process PHPUnit + SQLite run.
 */
class ZatcaSettingsUpdateLockAndErrorTruncationTest extends TestCase
{
    public function test_update_settings_locks_the_company_row_before_mutating_it(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/User/ZatcaController.php'));

        $this->assertStringContainsString('lockForUpdate()', $source);
        $this->assertStringContainsString('DB::transaction(function () use (&$company, &$capabilitiesChanged', $source);
    }

    public function test_every_flashed_upstream_error_body_is_truncated(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/User/ZatcaController.php'));

        $this->assertStringNotContainsString("'body' => \$response->body(),", $source);
        $this->assertStringContainsString('truncatedErrorBody', $source);
    }

    public function test_truncated_error_body_bounds_an_unexpectedly_large_response(): void
    {
        $reflection = new \ReflectionMethod(\App\Http\Controllers\User\ZatcaController::class, 'truncatedErrorBody');
        $reflection->setAccessible(true);
        $controller = new \App\Http\Controllers\User\ZatcaController;

        $response = new \Illuminate\Http\Client\Response(
            new \GuzzleHttp\Psr7\Response(500, [], str_repeat('x', 10000))
        );

        $result = $reflection->invoke($controller, $response);

        $this->assertLessThanOrEqual(504, strlen($result));
    }
}
