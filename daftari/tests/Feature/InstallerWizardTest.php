<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\User;
use App\Support\Installer\EnvWriter;
use App\Support\Installer\InstallerLock;
use App\Support\Installer\RequirementsChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Module 24 (Installation Wizard): the 6-step /install flow — requirements
 * gating, the database connection test, the application/admin steps
 * building up session state, the real Step 5 run (migrate + required
 * seeders + admin creation + InstallerLock), and the security guarantee
 * that the wizard refuses to run again once installed (short of the
 * explicit `installer:enable` CLI escape hatch).
 *
 * Deliberately does not use RefreshDatabase: Steps 1-4 never touch a
 * database at all (matching the wizard's own real-world constraint — the
 * database isn't configured yet), and the one full end-to-end test
 * manages its own throwaway in-memory connection so it can exercise the
 * real migrate/seed/create-admin path without touching this app's own
 * dev database.
 *
 * InstallerLock's marker file lives on the real filesystem (by design —
 * see its class docblock), so every test clears it in setUp/tearDown to
 * avoid leaving this dev instance's own /install locked after the suite
 * runs.
 *
 * Uses RefreshDatabase like every other feature test — not because
 * Steps 1-4's own logic needs a database, but because the app's global
 * CheckMaintenanceMode middleware (part of the 'web' group, so it runs
 * on every request regardless of route) queries the `settings` table
 * unconditionally. The one full end-to-end test still exercises a real,
 * separate throwaway connection for the actual install path — see its
 * own comment.
 */
class InstallerWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        InstallerLock::unlock();
    }

    protected function tearDown(): void
    {
        InstallerLock::unlock();
        parent::tearDown();
    }

    // -----------------------------------------------------------------
    // Step 1 — Requirements
    // -----------------------------------------------------------------

    public function test_requirements_page_renders_pass_fail_and_warn_labels(): void
    {
        $response = $this->get(route('install.requirements'));

        $response->assertOk()
            ->assertSee(__('Requirements check'))
            ->assertSee(__('PHP'))
            ->assertSee(__('Writable directories'));
    }

    public function test_requirements_continue_is_blocked_when_a_check_fails(): void
    {
        $this->app->bind(RequirementsChecker::class, fn () => new class extends RequirementsChecker
        {
            public function run(): array
            {
                return [['group' => 'PHP', 'label' => 'PHP version', 'status' => self::FAIL, 'detail' => 'too old']];
            }
        });

        $response = $this->post(route('install.requirements.store'));

        $response->assertSessionHasErrors('requirements');
        $response->assertSessionMissing('installer.requirements_ok');
    }

    public function test_requirements_continue_advances_to_database_step_when_all_pass(): void
    {
        $this->app->bind(RequirementsChecker::class, fn () => new class extends RequirementsChecker
        {
            public function run(): array
            {
                return [['group' => 'PHP', 'label' => 'PHP version', 'status' => self::PASS, 'detail' => 'ok']];
            }
        });

        $response = $this->post(route('install.requirements.store'));

        $response->assertRedirect(route('install.database'));
        $response->assertSessionHas('installer.requirements_ok', true);
    }

    // -----------------------------------------------------------------
    // Step 2 — Database
    // -----------------------------------------------------------------

    public function test_database_step_redirects_back_to_requirements_if_skipped(): void
    {
        $response = $this->get(route('install.database'));

        $response->assertRedirect(route('install.requirements'));
    }

    public function test_database_step_rejects_an_unreachable_connection(): void
    {
        $response = $this->withSession(['installer.requirements_ok' => true])
            ->post(route('install.database.store'), [
                'host' => '127.0.0.1',
                'port' => '1', // nothing listens here
                'database' => 'daftari',
                'username' => 'root',
                'password' => 'wrong',
            ]);

        $response->assertSessionHasErrors('connection');
        $response->assertSessionMissing('installer.database');
    }

    public function test_database_step_accepts_a_verified_connection_and_advances(): void
    {
        $this->fakeSuccessfulDatabaseTest();

        $response = $this->withSession(['installer.requirements_ok' => true])
            ->post(route('install.database.store'), [
                'host' => '127.0.0.1',
                'port' => '3306',
                'database' => 'daftari',
                'username' => 'root',
                'password' => 'super-secret',
            ]);

        $response->assertRedirect(route('install.application'));
        $response->assertSessionHas('installer.database.database', 'daftari');
    }

    public function test_database_password_is_never_redisplayed_on_the_form(): void
    {
        $response = $this->withSession(['installer' => [
            'requirements_ok' => true,
            'database' => ['host' => '127.0.0.1', 'port' => '3306', 'database' => 'daftari', 'username' => 'root', 'password' => 'super-secret-value'],
        ]])->get(route('install.database'));

        $response->assertOk();
        $response->assertDontSee('super-secret-value');
    }

    // -----------------------------------------------------------------
    // Step 3 — Application
    // -----------------------------------------------------------------

    public function test_application_step_redirects_back_to_database_if_skipped(): void
    {
        $response = $this->get(route('install.application'));

        $response->assertRedirect(route('install.database'));
    }

    public function test_application_step_saves_and_advances(): void
    {
        $response = $this->withSession(['installer' => [
            'requirements_ok' => true,
            'database' => ['host' => '127.0.0.1', 'port' => '3306', 'database' => 'daftari', 'username' => 'root', 'password' => ''],
        ]])->post(route('install.application.store'), [
            'name' => 'My Company Books',
            'url' => 'https://books.example.com',
            'timezone' => 'Asia/Riyadh',
            'locale' => 'ar',
            'currency' => 'SAR',
        ]);

        $response->assertRedirect(route('install.admin'));
        $response->assertSessionHas('installer.application.name', 'My Company Books');
        $response->assertSessionHas('installer.application.currency', 'SAR');
    }

    public function test_application_step_rejects_an_unsupported_currency(): void
    {
        $response = $this->withSession(['installer' => [
            'requirements_ok' => true,
            'database' => ['host' => '127.0.0.1', 'port' => '3306', 'database' => 'daftari', 'username' => 'root', 'password' => ''],
        ]])->post(route('install.application.store'), [
            'name' => 'My Company Books',
            'url' => 'https://books.example.com',
            'timezone' => 'Asia/Riyadh',
            'locale' => 'ar',
            'currency' => 'XXX',
        ]);

        $response->assertSessionHasErrors('currency');
    }

    // -----------------------------------------------------------------
    // Step 4 — Admin
    // -----------------------------------------------------------------

    public function test_admin_step_redirects_back_to_application_if_skipped(): void
    {
        $response = $this->get(route('install.admin'));

        $response->assertRedirect(route('install.application'));
    }

    public function test_admin_step_rejects_a_mismatched_password_confirmation(): void
    {
        $response = $this->withSession(['installer' => [
            'requirements_ok' => true,
            'database' => ['host' => '127.0.0.1', 'port' => '3306', 'database' => 'daftari', 'username' => 'root', 'password' => ''],
            'application' => ['name' => 'Books', 'url' => 'https://x.test', 'timezone' => 'UTC', 'locale' => 'en', 'currency' => 'SAR'],
        ]])->post(route('install.admin.store'), [
            'name' => 'Jane Admin',
            'email' => 'jane@example.test',
            'password' => 'Passw0rd!',
            'password_confirmation' => 'DoesNotMatch!',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_admin_step_stores_a_hash_not_the_plain_password_and_advances(): void
    {
        $response = $this->withSession(['installer' => [
            'requirements_ok' => true,
            'database' => ['host' => '127.0.0.1', 'port' => '3306', 'database' => 'daftari', 'username' => 'root', 'password' => ''],
            'application' => ['name' => 'Books', 'url' => 'https://x.test', 'timezone' => 'UTC', 'locale' => 'en', 'currency' => 'SAR'],
        ]])->post(route('install.admin.store'), [
            'name' => 'Jane Admin',
            'email' => 'jane@example.test',
            'password' => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
        ]);

        $response->assertRedirect(route('install.finish'));
        $hash = session('installer.admin.password_hash');
        $this->assertNotSame('Passw0rd!', $hash);
        $this->assertTrue(Hash::check('Passw0rd!', $hash));
    }

    // -----------------------------------------------------------------
    // Step 5 — Installation
    // -----------------------------------------------------------------

    public function test_finish_step_redirects_away_if_any_prior_step_is_missing(): void
    {
        $response = $this->get(route('install.finish'));

        $response->assertRedirect(route('install.index'));
    }

    public function test_finish_step_shows_a_summary_without_the_database_password(): void
    {
        $response = $this->withSession(['installer' => $this->completeWizardSessionData()])
            ->get(route('install.finish'));

        $response->assertOk();
        $response->assertSee('127.0.0.1');
        $response->assertDontSee('super-secret-db-password');
    }

    public function test_run_install_fails_gracefully_on_an_unreachable_database_without_locking(): void
    {
        $data = $this->completeWizardSessionData();
        $data['database'] = ['host' => '127.0.0.1', 'port' => '1', 'database' => 'daftari', 'username' => 'root', 'password' => ''];

        $tempEnv = tempnam(sys_get_temp_dir(), 'daftari-env-test-');
        $this->app->bind(EnvWriter::class, fn () => new EnvWriter($tempEnv));

        // writeEnvironment() sets database.default to 'mysql' before the
        // (failing) connection attempt — restore it before this method
        // returns, or RefreshDatabase's own tearDown() tries to roll back
        // a transaction on the now-default 'mysql' connection (pointed at
        // this same unreachable host) instead of the real testing one.
        $originalDefault = config('database.default');

        try {
            $response = $this->withSession(['installer' => $data])->post(route('install.finish.store'));

            $response->assertSessionHasErrors('install');
            $this->assertFalse(InstallerLock::isInstalled());
        } finally {
            config(['database.default' => $originalDefault]);
            \Illuminate\Support\Facades\DB::purge('mysql');
            @unlink($tempEnv);
        }
    }

    public function test_the_full_wizard_installs_successfully_end_to_end(): void
    {
        $tempEnv = tempnam(sys_get_temp_dir(), 'daftari-env-test-');
        $this->app->bind(EnvWriter::class, fn () => new EnvWriter($tempEnv));

        // A throwaway in-memory SQLite database standing in for the real
        // MySQL server the wizard's Database step would normally point
        // at — this app's own migrations and seeders already run against
        // SQLite in every other test in this suite, so this genuinely
        // exercises runInstall()'s real migrate/seed/create-admin path
        // rather than mocking it away. The session's 'database' key is
        // deliberately ':memory:' so writeEnvironment()'s runtime config
        // overwrite (which sets database.connections.mysql.database from
        // it) doesn't turn this into a stray file on disk.
        config(['database.connections.mysql' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        $data = $this->completeWizardSessionData();
        $data['database']['database'] = ':memory:';
        $data['application']['currency'] = 'USD';

        $originalDefault = config('database.default');

        try {
            $response = $this->withSession(['installer' => $data])->post(route('install.finish.store'));

            $response->assertOk();
            $response->assertSee(__('Installation successful'));

            $this->assertTrue(InstallerLock::isInstalled());

            $admin = User::where('email', 'jane@example.test')->first();
            $this->assertNotNull($admin);
            $this->assertSame('super_admin', $admin->role);
            $this->assertNull($admin->company_id);
            $this->assertTrue(Hash::check('Passw0rd!', $admin->password));

            $this->assertSame('USD', Currency::default()?->code);

            // Re-running any installer step must now be refused.
            $again = $this->get(route('install.requirements'));
            $again->assertRedirect(route('login'));
        } finally {
            // Same reasoning as the failure test above — runInstall()
            // permanently repointed database.default at the throwaway
            // in-memory connection this test set up; hand the real
            // testing connection back before RefreshDatabase's tearDown
            // tries to roll it back.
            config(['database.default' => $originalDefault]);
            \Illuminate\Support\Facades\DB::purge('mysql');
            @unlink($tempEnv);
        }
    }

    // -----------------------------------------------------------------
    // Security: refuse to run again once installed
    // -----------------------------------------------------------------

    public function test_every_installer_route_is_refused_once_installed(): void
    {
        InstallerLock::lock('admin@example.test');

        foreach ([
            route('install.index'),
            route('install.requirements'),
            route('install.database'),
            route('install.application'),
            route('install.admin'),
            route('install.finish'),
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_installer_enable_command_unlocks_it_and_is_a_noop_when_already_unlocked(): void
    {
        InstallerLock::lock('admin@example.test');

        $this->artisan('installer:enable', ['--force' => true])->assertExitCode(0);
        $this->assertFalse(InstallerLock::isInstalled());

        // Already unlocked — should say so and stay unlocked, not error.
        $this->artisan('installer:enable', ['--force' => true])->assertExitCode(0);
        $this->assertFalse(InstallerLock::isInstalled());
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function fakeSuccessfulDatabaseTest(): void
    {
        $this->app->bind(\App\Support\Installer\DatabaseConnectionTester::class, fn () => new class extends \App\Support\Installer\DatabaseConnectionTester
        {
            public function test(string $host, string $port, string $database, string $username, string $password): array
            {
                return ['ok' => true, 'message' => 'Connected successfully.'];
            }
        });
    }

    private function completeWizardSessionData(): array
    {
        return [
            'requirements_ok' => true,
            'database' => ['host' => '127.0.0.1', 'port' => '3306', 'database' => 'daftari', 'username' => 'root', 'password' => 'super-secret-db-password'],
            'application' => ['name' => 'Books', 'url' => 'https://x.test', 'timezone' => 'UTC', 'locale' => 'en', 'currency' => 'SAR'],
            'admin' => ['name' => 'Jane Admin', 'email' => 'jane@example.test', 'password_hash' => Hash::make('Passw0rd!')],
        ];
    }
}
