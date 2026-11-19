<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Module 18 (Customer Support Tickets): ticket creation/numbering, the
 * public-reply vs internal-note split (internal notes must never reach a
 * company user, in the thread view or via attachment download), the admin
 * capabilities (reply/assign/priority/status/note — all audit-logged),
 * company ticket history, cross-company tenant isolation, and upload
 * validation.
 */
class TicketManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    private function makeCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Acme Trading', 'slug' => 'acme-'.uniqid(), 'status' => 'active',
        ], $overrides));
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);
    }

    // ---------------------------------------------------------------
    // Creation + numbering
    // ---------------------------------------------------------------

    public function test_company_user_can_open_a_ticket_with_an_attachment(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->post(route('app.tickets.store'), [
            'subject' => 'Cannot generate ZATCA CSR',
            'description' => 'Getting an OpenSSL error.',
            'priority' => 'high',
            'attachment' => UploadedFile::fake()->create('screenshot.pdf', 200, 'application/pdf'),
        ]);

        $ticket = Ticket::firstOrFail();
        $response->assertRedirect(route('app.tickets.show', $ticket));
        $this->assertMatchesRegularExpression('/^TKT-\d{6}$/', $ticket->ticket_number);
        $this->assertSame($company->id, $ticket->company_id);
        $this->assertSame($owner->id, $ticket->user_id);
        $this->assertSame(1, $ticket->attachments()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket.create', 'company_id' => $company->id]);
    }

    public function test_ticket_creation_rejects_a_disallowed_file_type(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->post(route('app.tickets.store'), [
            'subject' => 'Test',
            'description' => 'Test description',
            'priority' => 'normal',
            'attachment' => UploadedFile::fake()->create('malicious.php', 10, 'application/x-httpd-php'),
        ]);

        $response->assertSessionHasErrors('attachment');
        $this->assertSame(0, Ticket::count());
    }

    // ---------------------------------------------------------------
    // Internal notes never reach the customer
    // ---------------------------------------------------------------

    public function test_customer_never_sees_internal_notes_in_the_ticket_thread(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $admin = $this->makeAdmin();

        $ticket = Ticket::create([
            'company_id' => $company->id, 'user_id' => $owner->id,
            'subject' => 'Billing question', 'description' => 'Why was I charged twice?',
            'priority' => 'normal', 'status' => 'open',
        ]);
        $ticket->replies()->create(['author_id' => $admin->id, 'body' => 'Visible support reply', 'is_internal_note' => false]);
        $ticket->replies()->create(['author_id' => $admin->id, 'body' => 'SECRET-INTERNAL-DISCUSSION-ABOUT-REFUND-POLICY', 'is_internal_note' => true]);

        $response = $this->actingAs($owner)->get(route('app.tickets.show', $ticket));

        $response->assertOk()
            ->assertSee('Visible support reply')
            ->assertDontSee('SECRET-INTERNAL-DISCUSSION-ABOUT-REFUND-POLICY');
    }

    public function test_customer_cannot_download_an_internal_note_attachment(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $admin = $this->makeAdmin();

        $ticket = Ticket::create([
            'company_id' => $company->id, 'user_id' => $owner->id,
            'subject' => 'Test', 'description' => 'Test', 'priority' => 'normal', 'status' => 'open',
        ]);
        $note = $ticket->replies()->create(['author_id' => $admin->id, 'body' => 'internal', 'is_internal_note' => true]);
        $attachment = TicketAttachment::storeUpload($ticket, $note, UploadedFile::fake()->create('internal.pdf', 50, 'application/pdf'), $admin->id, true);

        $response = $this->actingAs($owner)->get(route('app.tickets.attachments.download', $attachment));

        $response->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Tenant isolation
    // ---------------------------------------------------------------

    public function test_company_user_cannot_view_another_companys_ticket(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);

        $ticketB = Ticket::create([
            'company_id' => $companyB->id, 'user_id' => $this->makeOwner($companyB)->id,
            'subject' => 'Other company issue', 'description' => 'x', 'priority' => 'normal', 'status' => 'open',
        ]);

        $response = $this->actingAs($ownerA)->get(route('app.tickets.show', $ticketB));

        $response->assertNotFound();
    }

    public function test_company_user_cannot_download_another_companys_attachment(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $ownerB = $this->makeOwner($companyB);

        $ticketB = Ticket::create([
            'company_id' => $companyB->id, 'user_id' => $ownerB->id,
            'subject' => 'x', 'description' => 'x', 'priority' => 'normal', 'status' => 'open',
        ]);
        $attachment = TicketAttachment::storeUpload($ticketB, null, UploadedFile::fake()->create('file.pdf', 50, 'application/pdf'), $ownerB->id, false);

        $response = $this->actingAs($ownerA)->get(route('app.tickets.attachments.download', $attachment));

        $response->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Customer reply flips waiting_customer back to open
    // ---------------------------------------------------------------

    public function test_customer_reply_reopens_a_ticket_waiting_on_them(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $ticket = Ticket::create([
            'company_id' => $company->id, 'user_id' => $owner->id,
            'subject' => 'x', 'description' => 'x', 'priority' => 'normal', 'status' => 'waiting_customer',
        ]);

        $this->actingAs($owner)->post(route('app.tickets.reply', $ticket), ['body' => 'Here is more info.']);

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNotNull($ticket->last_reply_at);
    }

    // ---------------------------------------------------------------
    // Admin capabilities: reply, assign, priority, status, internal note
    // ---------------------------------------------------------------

    public function test_admin_can_reply_and_it_notifies_the_customer(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $ticket = Ticket::create([
            'company_id' => $company->id, 'user_id' => $owner->id,
            'subject' => 'x', 'description' => 'x', 'priority' => 'normal', 'status' => 'open',
        ]);

        $this->actingAs($admin)->post(route('admin.tickets.reply', $ticket), ['body' => 'We are looking into it.']);

        $this->assertSame(1, $ticket->publicReplies()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket.reply', 'company_id' => $company->id]);
        $this->assertCount(1, $owner->fresh()->notifications);
    }

    public function test_admin_can_add_an_internal_note_and_it_is_audit_logged_without_content(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $ticket = Ticket::create([
            'company_id' => $company->id, 'user_id' => $owner->id,
            'subject' => 'x', 'description' => 'x', 'priority' => 'normal', 'status' => 'open',
        ]);

        $this->actingAs($admin)->post(route('admin.tickets.note', $ticket), ['body' => 'CONFIDENTIAL-NOTE-CONTENT']);

        $this->assertSame(1, $ticket->internalNotes()->count());
        $log = AuditLog::where('action', 'ticket.internal_note')->firstOrFail();
        $this->assertStringNotContainsString('CONFIDENTIAL-NOTE-CONTENT', $log->description ?? '');
    }

    public function test_admin_can_assign_a_ticket_to_another_admin(): void
    {
        $admin = $this->makeAdmin();
        $assignee = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
        $company = $this->makeCompany();
        $ticket = Ticket::create([
            'company_id' => $company->id, 'user_id' => $this->makeOwner($company)->id,
            'subject' => 'x', 'description' => 'x', 'priority' => 'normal', 'status' => 'open',
        ]);

        $this->actingAs($admin)->post(route('admin.tickets.assign', $ticket), ['assigned_admin_id' => $assignee->id]);

        $ticket->refresh();
        $this->assertSame($assignee->id, $ticket->assigned_admin_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket.assign', 'company_id' => $company->id]);
    }

    public function test_admin_can_change_priority_and_status(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $ticket = Ticket::create([
            'company_id' => $company->id, 'user_id' => $owner->id,
            'subject' => 'x', 'description' => 'x', 'priority' => 'low', 'status' => 'open',
        ]);

        $this->actingAs($admin)->post(route('admin.tickets.priority', $ticket), ['priority' => 'urgent']);
        $this->actingAs($admin)->post(route('admin.tickets.status', $ticket), ['status' => 'resolved']);

        $ticket->refresh();
        $this->assertSame('urgent', $ticket->priority);
        $this->assertSame('resolved', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket.priority_change', 'company_id' => $company->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket.status_change', 'company_id' => $company->id]);
        $this->assertCount(1, $owner->fresh()->notifications);
    }

    public function test_admin_can_view_internal_notes_in_the_thread(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $ticket = Ticket::create([
            'company_id' => $company->id, 'user_id' => $this->makeOwner($company)->id,
            'subject' => 'x', 'description' => 'x', 'priority' => 'normal', 'status' => 'open',
        ]);
        $ticket->replies()->create(['author_id' => $admin->id, 'body' => 'VISIBLE-TO-ADMIN-ONLY', 'is_internal_note' => true]);

        $response = $this->actingAs($admin)->get(route('admin.tickets.show', $ticket));

        $response->assertOk()->assertSee('VISIBLE-TO-ADMIN-ONLY');
    }

    public function test_admin_ticket_view_shows_company_history(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $older = Ticket::create([
            'company_id' => $company->id, 'user_id' => $owner->id,
            'subject' => 'Older ticket subject', 'description' => 'x', 'priority' => 'normal', 'status' => 'closed',
        ]);
        $current = Ticket::create([
            'company_id' => $company->id, 'user_id' => $owner->id,
            'subject' => 'Current ticket', 'description' => 'x', 'priority' => 'normal', 'status' => 'open',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.tickets.show', $current));

        $response->assertOk()->assertSee('Older ticket subject');
    }

    // ---------------------------------------------------------------
    // Permission gating
    // ---------------------------------------------------------------

    public function test_admin_staff_without_tickets_permission_is_forbidden(): void
    {
        $staff = User::factory()->create(['role' => 'admin_staff', 'company_id' => null]);

        $response = $this->actingAs($staff)->get(route('admin.tickets.index'));

        $response->assertForbidden();
    }

    public function test_company_user_without_support_permission_is_forbidden(): void
    {
        $company = $this->makeCompany();
        $role = Role::create(['company_id' => $company->id, 'name' => 'No Support', 'slug' => 'no-support-'.uniqid(), 'permissions' => ['dashboard']]);
        $member = User::factory()->create(['company_id' => $company->id, 'role' => 'member']);
        $member->roles()->attach($role->id);

        $response = $this->actingAs($member)->get(route('app.tickets.index'));

        $response->assertForbidden();
    }
}
