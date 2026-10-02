<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Three findings from a settings security audit:
 *
 * 1. The API token routes had no permission:settings gate, unlike every
 *    sibling settings route — any team member, even with zero granted
 *    permissions, could mint a standing Sanctum API credential.
 * 2. Webhook::secret was a plain string column, permanently re-displayed
 *    in full on every visit to the webhook's page — a DB leak or any
 *    settings-permission teammate could read the raw HMAC signing secret
 *    indefinitely. Now encrypted at rest and shown in full only right
 *    after creation/regeneration, masked afterward.
 * 3. Admin storage settings (S3 credentials controlling where every
 *    tenant's files are read/written) had no step-up re-authentication,
 *    unlike the comparably sensitive payment gateway settings.
 */
class SettingsSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithLimitedMember(): array
    {
        $company = Company::create(['name' => 'Sec Co.', 'slug' => 'sec-'.uniqid()]);
        $role = Role::create(['company_id' => $company->id, 'name' => 'No Settings', 'slug' => 'no-settings-'.uniqid(), 'permissions' => ['dashboard']]);
        $member = User::factory()->create(['role' => 'member', 'company_id' => $company->id, 'status' => 'active']);
        $member->roles()->attach($role->id);

        return [$company, $member];
    }

    public function test_a_team_member_without_settings_permission_cannot_create_an_api_token(): void
    {
        [, $member] = $this->makeCompanyWithLimitedMember();

        $response = $this->actingAs($member)->post(route('app.settings.api-tokens.store'), ['name' => 'Sneaky token']);

        $response->assertForbidden();
    }

    public function test_a_team_member_without_settings_permission_cannot_list_api_tokens(): void
    {
        [, $member] = $this->makeCompanyWithLimitedMember();

        $response = $this->actingAs($member)->get(route('app.settings.api-tokens'));

        $response->assertForbidden();
    }

    public function test_webhook_secret_is_encrypted_at_rest(): void
    {
        $company = Company::create(['name' => 'Webhook Co.', 'slug' => 'webhook-'.uniqid()]);
        $webhook = Webhook::create(['company_id' => $company->id, 'url' => 'https://example.com/hook', 'events' => ['invoice.created']]);

        $rawColumn = \Illuminate\Support\Facades\DB::table('webhooks')->where('id', $webhook->id)->value('secret');

        $this->assertNotSame($webhook->secret, $rawColumn);
        $this->assertSame($webhook->secret, $webhook->fresh()->secret);
    }

    public function test_webhook_secret_shows_in_full_right_after_creation_but_is_masked_on_a_later_visit(): void
    {
        $company = Company::create(['name' => 'Webhook Co 2.', 'slug' => 'webhook2-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $this->actingAs($owner);

        $createResponse = $this->post(route('app.settings.webhooks.store'), [
            'url' => 'https://example.com/hook',
            'events' => ['invoice.created'],
        ]);
        $webhook = Webhook::first();
        $createResponse->assertRedirect(route('app.settings.webhooks.show', $webhook));

        // The flash set by store()'s redirect is available for exactly the
        // next request in the same session — this is that request.
        $revealed = $this->get(route('app.settings.webhooks.show', $webhook));
        $revealed->assertSee($webhook->secret);

        $laterVisit = $this->get(route('app.settings.webhooks.show', $webhook));
        $laterVisit->assertDontSee($webhook->secret);
    }

    public function test_admin_storage_settings_require_a_freshly_confirmed_password(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);

        $response = $this->actingAs($admin)->post(route('admin.settings.storage.update'), [
            'storage_disk' => 'public',
        ]);

        $response->assertRedirect(route('admin.password.confirm'));
    }
}
