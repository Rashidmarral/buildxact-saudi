<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * "sometime took too much time on load and in end shows error laravel
 * error execution time exceed" — a PHP max-execution-time fatal (which
 * Laravel's shutdown handler turns into a Symfony FatalError) used to
 * fall through to the generic 500 handling, or a raw debug trace if
 * APP_DEBUG was left on. bootstrap/app.php now recognizes this specific
 * fatal by its message and renders the branded error page with a
 * message that actually explains what happened, via a 504.
 */
class ExecutionTimeoutErrorPageTest extends TestCase
{
    public function test_a_max_execution_time_fatal_renders_the_branded_page_with_a_specific_message(): void
    {
        config(['app.debug' => false]);
        Route::get('/__test-timeout', function () {
            throw new \RuntimeException('Maximum execution time of 30 seconds exceeded');
        });

        $response = $this->get('/__test-timeout');

        $response->assertStatus(504);
        $response->assertSee('took longer than expected');
        $response->assertDontSee('Maximum execution time of 30 seconds exceeded');
    }

    public function test_a_max_execution_time_fatal_returns_json_for_api_requests(): void
    {
        config(['app.debug' => false]);
        Route::get('/__test-timeout-json', function () {
            throw new \RuntimeException('Maximum execution time of 30 seconds exceeded');
        });

        $response = $this->getJson('/__test-timeout-json');

        $response->assertStatus(504);
        $response->assertJsonFragment(['message' => __('This took longer than expected to complete. Try narrowing your date range or filters and try again — if it keeps happening, let us know.')]);
    }

    public function test_an_unrelated_exception_is_not_caught_by_the_timeout_handler(): void
    {
        config(['app.debug' => false]);
        Route::get('/__test-not-a-timeout', function () {
            throw new \RuntimeException('Something else entirely went wrong');
        });

        $response = $this->get('/__test-not-a-timeout');

        $response->assertStatus(500);
        $response->assertDontSee('took longer than expected');
    }
}
