<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FixedAsset;
use App\Models\Project;
use App\Models\ProjectTimesheet;
use App\Models\Supplier;
use App\Models\User;
use App\Support\FriendlyDatabaseError;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * "Column 'transport_allowance' cannot be null ... please fix this if
 * something required that missing please handle it with more
 * professional show error that's required or something not for this
 * for the whole app" — two fixes:
 *
 * 1. EmployeeController::validated() now defaults a blank housing/
 *    transport/other allowance to 0 instead of letting Laravel's
 *    ConvertEmptyStringsToNull middleware turn a left-blank form field
 *    into an explicit NULL, which the employees table's own
 *    decimal(...)->default(0) NOT NULL columns reject outright (a
 *    DEFAULT only applies when a column is omitted from the INSERT,
 *    never when it's present with a NULL value).
 * 2. FriendlyDatabaseError + the QueryException renderer registered in
 *    bootstrap/app.php are the app-wide safety net for the next time
 *    some other form has the same gap — translating a raw NOT NULL /
 *    unique / foreign-key engine message into one sentence a
 *    non-technical user can act on, instead of a raw SQL exception.
 */
class FriendlyDatabaseErrorTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    // ------------------------------------------------------------------
    // The actual reported bug
    // ------------------------------------------------------------------

    public function test_an_employee_can_be_created_with_blank_allowance_fields_left_empty_by_the_form(): void
    {
        [$company, $owner] = $this->makeOwner();

        // A real browser submits a blank <input type="number"> as an
        // empty string, not an absent key — exactly what tripped this.
        $response = $this->actingAs($owner)->post(route('app.employees.store'), [
            'full_name' => 'Shahid', 'hire_date' => now()->toDateString(), 'basic_salary' => 6000,
            'housing_allowance' => '', 'transport_allowance' => '', 'other_allowance' => '',
        ]);

        $response->assertRedirect(route('app.employees.index'));
        $response->assertSessionDoesntHaveErrors();
        $employee = Employee::where('company_id', $company->id)->first();
        $this->assertNotNull($employee);
        $this->assertEquals(0.0, (float) $employee->housing_allowance);
        $this->assertEquals(0.0, (float) $employee->transport_allowance);
        $this->assertEquals(0.0, (float) $employee->other_allowance);
    }

    public function test_updating_an_employee_with_blank_allowance_fields_resets_them_to_zero_instead_of_failing(): void
    {
        [$company, $owner] = $this->makeOwner();
        $employee = Employee::create([
            'company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Khalid',
            'hire_date' => now(), 'basic_salary' => 5000, 'transport_allowance' => 300,
        ]);

        $response = $this->actingAs($owner)->put(route('app.employees.update', $employee), [
            'full_name' => 'Khalid', 'hire_date' => now()->toDateString(), 'basic_salary' => 5500,
            'transport_allowance' => '',
        ]);

        $response->assertRedirect(route('app.employees.index'));
        $this->assertEquals(0.0, (float) $employee->fresh()->transport_allowance);
    }

    // ------------------------------------------------------------------
    // The same bug, found and fixed in four other places by an audit
    // prompted by this report — same shape: a 'nullable' numeric field
    // feeding a NOT-NULL-with-default(0) column, left blank by a real form.
    // ------------------------------------------------------------------

    public function test_a_fixed_asset_can_be_created_with_a_blank_salvage_value(): void
    {
        [$company, $owner] = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.fixed-assets.store'), [
            'name' => 'Road Roller', 'acquisition_date' => now()->toDateString(),
            'acquisition_cost' => 50000, 'useful_life_years' => 10, 'salvage_value' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertEquals(0.0, (float) FixedAsset::where('company_id', $company->id)->first()->salvage_value);
    }

    public function test_a_bank_account_can_be_created_with_a_blank_opening_balance(): void
    {
        [$company, $owner] = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.bank-accounts.store'), [
            'name' => 'SAB Bank', 'type' => 'bank', 'currency' => 'SAR', 'opening_balance' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertEquals(0.0, (float) BankAccount::where('company_id', $company->id)->first()->opening_balance);
    }

    public function test_a_supplier_can_be_created_with_a_blank_initial_balance(): void
    {
        [$company, $owner] = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.suppliers.store'), [
            'type' => 'company', 'name' => 'Local Supplier', 'initial_balance' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertEquals(0.0, (float) Supplier::where('company_id', $company->id)->first()->initial_balance);
    }

    public function test_a_client_can_be_created_with_a_blank_initial_balance(): void
    {
        [$company, $owner] = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.clients.store'), [
            'client_code' => 'CLI-000001', 'type' => 'company', 'name' => 'Al Rashid Trading', 'initial_balance' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertEquals(0.0, (float) Client::where('company_id', $company->id)->first()->initial_balance);
    }

    public function test_a_timesheet_entry_can_be_added_with_blank_overtime_hours(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active']);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Khalid', 'hire_date' => now()->subYear()->toDateString(), 'status' => 'active']);

        $response = $this->actingAs($owner)->post(route('app.projects.timesheets.store', $project), [
            'employee_id' => $employee->id, 'work_date' => now()->toDateString(), 'hours' => 8, 'overtime_hours' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertEquals(0.0, (float) ProjectTimesheet::where('project_id', $project->id)->first()->overtime_hours);
    }

    // ------------------------------------------------------------------
    // FriendlyDatabaseError::describe() against real thrown exceptions
    // ------------------------------------------------------------------

    private function catchQueryException(callable $callback): QueryException
    {
        try {
            $callback();
        } catch (QueryException $e) {
            return $e;
        }

        $this->fail('Expected a QueryException to be thrown.');
    }

    public function test_describes_a_not_null_violation_in_plain_language(): void
    {
        [$company] = $this->makeOwner();

        $e = $this->catchQueryException(fn () => DB::table('employees')->insert([
            'company_id' => $company->id, 'employee_number' => 'EMP-X', 'full_name' => 'Test',
            'hire_date' => now(), 'transport_allowance' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]));

        $message = FriendlyDatabaseError::describe($e);
        $this->assertNotNull($message);
        $this->assertStringContainsString('Transport Allowance', $message);
        $this->assertStringContainsString('required', $message);
    }

    public function test_describes_a_unique_constraint_violation_in_plain_language(): void
    {
        [$company] = $this->makeOwner();
        Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'First', 'hire_date' => now(), 'basic_salary' => 1000]);

        $e = $this->catchQueryException(fn () => DB::table('employees')->insert([
            'company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Duplicate',
            'hire_date' => now(), 'basic_salary' => 1000, 'created_at' => now(), 'updated_at' => now(),
        ]));

        $message = FriendlyDatabaseError::describe($e);
        $this->assertNotNull($message);
        $this->assertStringContainsString('already exists', $message);
    }

    public function test_describes_a_foreign_key_violation_in_plain_language(): void
    {
        $e = $this->catchQueryException(fn () => DB::table('employees')->insert([
            'company_id' => 999999, 'employee_number' => 'EMP-X', 'full_name' => 'Orphan',
            'hire_date' => now(), 'basic_salary' => 1000, 'created_at' => now(), 'updated_at' => now(),
        ]));

        $message = FriendlyDatabaseError::describe($e);
        $this->assertNotNull($message);
        $this->assertStringContainsString('refers to something', $message);
    }

    public function test_an_unrecognized_query_exception_is_left_for_default_handling(): void
    {
        $e = $this->catchQueryException(fn () => DB::select('select * from a_table_that_does_not_exist'));

        $this->assertNull(FriendlyDatabaseError::describe($e));
    }

    // ------------------------------------------------------------------
    // The registered renderer, end-to-end
    // ------------------------------------------------------------------

    public function test_a_not_null_violation_reaching_the_app_shows_a_friendly_banner_instead_of_a_raw_exception(): void
    {
        [$company, $owner] = $this->makeOwner();

        Route::middleware('web')->post('/__test/force-not-null-violation', function () use ($company) {
            DB::table('employees')->insert([
                'company_id' => $company->id, 'employee_number' => 'EMP-FORCE', 'full_name' => 'Forced',
                'hire_date' => now(), 'transport_allowance' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return 'unreachable';
        });

        $response = $this->actingAs($owner)->post('/__test/force-not-null-violation');

        $response->assertRedirect();
        $response->assertSessionHasErrors('form');
        $this->assertStringContainsString('Transport Allowance', session('errors')->first('form'));
    }
}
