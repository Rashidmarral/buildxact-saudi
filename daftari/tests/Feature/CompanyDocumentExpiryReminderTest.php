<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Company;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * "please check for another gap" — Settings already lets a company store
 * compliance documents (CR, VAT Certificate, Municipality License, ...)
 * with an expiry date, but nothing proactively warned when one was about
 * to lapse, unlike Machinery/Invoices/Subscriptions which all already
 * have a reminder job. This closes that gap the same way machinery's
 * does: notify once, clear the guard once resolved — except it keeps
 * reminding once a document has actually expired rather than going quiet,
 * since an expired CR is worse than one about to expire, not less urgent.
 */
class CompanyDocumentExpiryReminderTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeDocument(Company $company, string $expiryDate, string $type = 'cr'): Attachment
    {
        return Attachment::create([
            'company_id' => $company->id, 'attachable_type' => Company::class, 'attachable_id' => $company->id,
            'original_name' => 'cr.pdf', 'path' => 'company-documents/cr.pdf', 'size' => 1000, 'mime_type' => 'application/pdf',
            'document_type' => $type, 'expiry_date' => $expiryDate,
        ]);
    }

    public function test_a_document_expiring_within_the_window_notifies_users_with_settings_access_once(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $document = $this->makeDocument($company, now()->addDays(10)->toDateString());

        Artisan::call('company-documents:send-expiry-reminders');

        Notification::assertSentTo($owner, GenericNotification::class, fn (GenericNotification $n) => str_contains($n->body, 'Commercial Registration'));
        $this->assertNotNull($document->fresh()->expiry_reminder_sent_at);

        Artisan::call('company-documents:send-expiry-reminders');
        Notification::assertSentToTimes($owner, GenericNotification::class, 1);
    }

    public function test_a_document_far_in_the_future_does_not_notify(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->makeDocument($company, now()->addDays(90)->toDateString());

        Artisan::call('company-documents:send-expiry-reminders');

        Notification::assertNothingSentTo($owner);
    }

    public function test_an_already_expired_document_still_notifies(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $document = $this->makeDocument($company, now()->subDays(5)->toDateString());

        Artisan::call('company-documents:send-expiry-reminders');

        Notification::assertSentTo($owner, GenericNotification::class, fn (GenericNotification $n) => str_contains($n->title, 'expired'));
        $this->assertNotNull($document->fresh()->expiry_reminder_sent_at);
    }

    public function test_renewing_a_document_clears_the_reminder_guard(): void
    {
        $company = $this->makeCompany();
        $this->makeOwner($company);
        $document = $this->makeDocument($company, now()->addDays(10)->toDateString());
        $document->update(['expiry_reminder_sent_at' => now()->subDay()]);

        // Renewed to a date well outside the reminder window.
        $document->update(['expiry_date' => now()->addYear()->toDateString()]);

        Artisan::call('company-documents:send-expiry-reminders');

        $this->assertNull($document->fresh()->expiry_reminder_sent_at);
    }

    public function test_only_users_with_settings_access_are_notified(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $member = User::factory()->create(['role' => 'member', 'company_id' => $company->id, 'status' => 'active']);
        $this->makeDocument($company, now()->addDays(5)->toDateString());

        Artisan::call('company-documents:send-expiry-reminders');

        Notification::assertSentTo($owner, GenericNotification::class);
        Notification::assertNothingSentTo($member);
    }

    public function test_a_document_with_no_expiry_date_is_never_flagged(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        Attachment::create([
            'company_id' => $company->id, 'attachable_type' => Company::class, 'attachable_id' => $company->id,
            'original_name' => 'other.pdf', 'path' => 'company-documents/other.pdf', 'size' => 1000, 'mime_type' => 'application/pdf',
            'document_type' => 'other', 'expiry_date' => null,
        ]);

        Artisan::call('company-documents:send-expiry-reminders');

        Notification::assertNothingSentTo($owner);
    }
}
