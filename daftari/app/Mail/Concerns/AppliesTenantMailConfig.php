<?php

namespace App\Mail\Concerns;

use App\Models\Company;
use App\Services\CompanyMailService;

/**
 * QA follow-up finding: every mailable in this app that uses a tenant's
 * own SMTP account implements ShouldQueue, and Illuminate\Mail\Mailer::
 * sendMailable() queues a ShouldQueue mailable even when the caller
 * wrote Mail::send() rather than Mail::queue() — so the controller's own
 * CompanyMailService::applyFor() call (made in the web request, right
 * before dispatching) only ever reached the *queue job's* process, not
 * the process that actually opens the SMTP connection: with any real
 * queue connection, that is a separate worker that boots from .env's
 * platform-default mail config and never sees the request's runtime
 * Config::set() calls at all.
 *
 * envelope() runs once the job is actually executing — in the worker for
 * a real queue connection, or inline for QUEUE_CONNECTION=sync — so
 * applying the tenant's config as envelope()'s first line (not relying
 * on whatever the dispatching controller already did) is what makes the
 * SMTP transport actually used by *this* process match the recipient's
 * company, regardless of queue driver.
 */
trait AppliesTenantMailConfig
{
    private function applyTenantMailConfig(Company $company): void
    {
        app(CompanyMailService::class)->applyFor($company);
    }
}
