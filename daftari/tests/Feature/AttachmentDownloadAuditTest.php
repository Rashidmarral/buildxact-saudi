<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Audit finding SEC-03: tenant isolation on attachment downloads was
 * already closed by FileServeController (see CRIT-01 and
 * PublicStorageSymlinkRemovedTest — a cross-company request 404s before
 * this session touched anything). What was genuinely missing was any
 * record of who viewed a sensitive attachment and when, so this only adds
 * an AuditLog entry on a successful download — it does not change the
 * access check itself.
 */
class AttachmentDownloadAuditTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithOwner(): array
    {
        $company = Company::create(['name' => 'Attachment Co.', 'slug' => 'attachment-co-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function makeAttachment(Company $company): Attachment
    {
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier']);
        $file = UploadedFile::fake()->create('bank-statement.pdf', 10, 'application/pdf');
        $path = $file->store('bills-attachments', 'public');

        return Attachment::create([
            'company_id' => $company->id,
            'attachable_type' => Supplier::class,
            'attachable_id' => $supplier->id,
            'original_name' => 'bank-statement.pdf',
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => 'application/pdf',
        ]);
    }

    public function test_downloading_an_attachment_within_your_own_company_is_audit_logged(): void
    {
        [$company, $owner] = $this->makeCompanyWithOwner();
        $attachment = $this->makeAttachment($company);

        $this->actingAs($owner)->get('/files/'.$attachment->path)->assertOk();

        $log = AuditLog::where('action', 'attachment.download')->first();
        $this->assertNotNull($log, 'A successful attachment download must be audit logged.');
        $this->assertSame($owner->id, $log->admin_user_id);
        $this->assertSame($company->id, $log->company_id);
    }

    public function test_a_cross_company_attachment_request_is_not_found_and_not_logged(): void
    {
        [$companyA, $ownerA] = $this->makeCompanyWithOwner();
        [$companyB] = $this->makeCompanyWithOwner();
        $attachmentB = $this->makeAttachment($companyB);

        $this->actingAs($ownerA)->get('/files/'.$attachmentB->path)->assertNotFound();

        $this->assertSame(0, AuditLog::where('action', 'attachment.download')->count());
    }

    public function test_an_unauthenticated_request_for_an_attachment_is_not_found(): void
    {
        [$company] = $this->makeCompanyWithOwner();
        $attachment = $this->makeAttachment($company);

        $this->get('/files/'.$attachment->path)->assertNotFound();
    }
}
