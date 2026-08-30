<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\User;
use App\Support\Installer\DatabaseConnectionTester;
use App\Support\Installer\EnvWriter;
use App\Support\Installer\InstallerLock;
use App\Support\Installer\RequirementsChecker;
use App\Support\Locales;
use Database\Seeders\AdminRoleSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

/**
 * The 6-step first-installation wizard (Module 24). Reachable at /install
 * only until InstallerLock says otherwise (see RedirectIfInstalled) — a
 * successful Step 5 both writes the lock file and, from that request
 * forward, makes every /install/* route redirect away, so this class
 * never needs to worry about being re-entered after a real install.
 *
 * Wizard state (database credentials, chosen app settings, the admin's
 * name/email and a password *hash*) rides in the session under the
 * 'installer' key — PrepareInstallerEnvironment forces file-based
 * sessions app-wide for as long as the app isn't installed, since the
 * app's normal SESSION_DRIVER (typically 'database') has no `sessions`
 * table to write to before Step 5's migration has run.
 */
class InstallController extends Controller
{
    /**
     * Currencies CurrencySeeder will actually create in Step 5 — kept in
     * sync with that seeder's list intentionally (a currency this wizard
     * lets someone pick has to exist afterward for makeDefault() to find).
     */
    private const CURRENCY_OPTIONS = ['SAR', 'USD', 'EUR', 'GBP', 'AED', 'KWD', 'BHD', 'QAR', 'OMR', 'EGP', 'JOD', 'INR'];

    public function index(): RedirectResponse
    {
        $data = session('installer', []);

        if (! ($data['requirements_ok'] ?? false)) {
            return redirect()->route('install.requirements');
        }

        if (! isset($data['database'])) {
            return redirect()->route('install.database');
        }

        if (! isset($data['application'])) {
            return redirect()->route('install.application');
        }

        if (! isset($data['admin'])) {
            return redirect()->route('install.admin');
        }

        return redirect()->route('install.finish');
    }

    // -----------------------------------------------------------------
    // Step 1 — Requirements
    // -----------------------------------------------------------------

    public function requirements(RequirementsChecker $checker)
    {
        $results = $checker->run();

        return view('installer.requirements', [
            'results' => $results,
            'grouped' => collect($results)->groupBy('group'),
            'canContinue' => $checker->allPassed($results),
        ]);
    }

    public function confirmRequirements(Request $request, RequirementsChecker $checker): RedirectResponse
    {
        $results = $checker->run();

        if (! $checker->allPassed($results)) {
            return back()->withErrors(['requirements' => __('Please resolve the failed requirements before continuing.')]);
        }

        $request->session()->put('installer.requirements_ok', true);

        return redirect()->route('install.database');
    }

    // -----------------------------------------------------------------
    // Step 2 — Database
    // -----------------------------------------------------------------

    public function showDatabase(Request $request)
    {
        if (! $request->session()->get('installer.requirements_ok')) {
            return redirect()->route('install.requirements');
        }

        $values = $request->session()->get('installer.database', []);
        unset($values['password']); // never re-render a submitted password

        return view('installer.database', ['values' => $values]);
    }

    public function saveDatabase(Request $request, DatabaseConnectionTester $tester): RedirectResponse
    {
        if (! $request->session()->get('installer.requirements_ok')) {
            return redirect()->route('install.requirements');
        }

        $data = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'numeric', 'between:1,65535'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $tester->test($data['host'], (string) $data['port'], $data['database'], $data['username'], $data['password'] ?? '');

        if (! $result['ok']) {
            return back()->withErrors(['connection' => $result['message']])->withInput($request->except('password'));
        }

        $request->session()->put('installer.database', $data);

        return redirect()->route('install.application');
    }

    // -----------------------------------------------------------------
    // Step 3 — Application
    // -----------------------------------------------------------------

    public function showApplication(Request $request)
    {
        if (! $request->session()->has('installer.database')) {
            return redirect()->route('install.database');
        }

        return view('installer.application', [
            'values' => $request->session()->get('installer.application', [
                'name' => 'Daftari',
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
                'currency' => config('daftari.default_currency'),
            ]),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'locales' => Locales::LIST,
            'currencies' => self::CURRENCY_OPTIONS,
        ]);
    }

    public function saveApplication(Request $request): RedirectResponse
    {
        if (! $request->session()->has('installer.database')) {
            return redirect()->route('install.database');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:255'],
            'timezone' => ['required', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
            'locale' => ['required', 'string', Rule::in(Locales::codes())],
            'currency' => ['required', 'string', Rule::in(self::CURRENCY_OPTIONS)],
        ]);

        $request->session()->put('installer.application', $data);

        return redirect()->route('install.admin');
    }

    // -----------------------------------------------------------------
    // Step 4 — Admin
    // -----------------------------------------------------------------

    public function showAdmin(Request $request)
    {
        if (! $request->session()->has('installer.application')) {
            return redirect()->route('install.application');
        }

        $values = $request->session()->get('installer.admin', []);
        unset($values['password_hash']);

        return view('installer.admin', ['values' => $values]);
    }

    public function saveAdmin(Request $request): RedirectResponse
    {
        if (! $request->session()->has('installer.application')) {
            return redirect()->route('install.application');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Only the hash is kept in session from this point on — never the
        // plain password, even for the few minutes until Step 5 runs.
        $request->session()->put('installer.admin', [
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => Hash::make($data['password']),
        ]);

        return redirect()->route('install.finish');
    }

    // -----------------------------------------------------------------
    // Step 5 — Installation
    // -----------------------------------------------------------------

    public function showFinish(Request $request)
    {
        $data = $request->session()->get('installer', []);

        if (! isset($data['database'], $data['application'], $data['admin'])) {
            return redirect()->route('install.index');
        }

        return view('installer.finish', [
            'database' => $data['database'],
            'application' => $data['application'],
            'admin' => $data['admin'],
        ]);
    }

    public function runInstall(Request $request, EnvWriter $writer)
    {
        $data = $request->session()->get('installer', []);

        if (! isset($data['database'], $data['application'], $data['admin'])) {
            return redirect()->route('install.index');
        }

        $log = [];

        try {
            $log[] = $this->writeEnvironment($data, $writer);
            $log[] = $this->connectToDatabase();
            $log[] = $this->runMigrations();
            $log[] = $this->runRequiredSeeders();
            $log[] = $this->applyChosenCurrency($data['application']['currency']);
            $log[] = $this->setUpStorage();
            $admin = $this->createAdministrator($data['admin']);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'install' => __('Installation failed: :message', ['message' => $e->getMessage()]),
            ]);
        }

        InstallerLock::lock($admin->email);
        $request->session()->forget('installer');
        $request->session()->regenerate();

        return view('installer.complete', [
            'log' => $log,
            'adminUrl' => route('admin.dashboard'),
            'loginUrl' => route('login'),
            'adminEmail' => $admin->email,
        ]);
    }

    /**
     * @param  array{database: array, application: array, admin: array}  $data
     */
    private function writeEnvironment(array $data, EnvWriter $writer): string
    {
        $db = $data['database'];
        $app = $data['application'];

        $writer->write([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $db['host'],
            'DB_PORT' => $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => $db['password'] ?? '',
            'APP_NAME' => $app['name'],
            'APP_URL' => $app['url'],
            'APP_TIMEZONE' => $app['timezone'],
            'APP_LOCALE' => $app['locale'],
            'DEFAULT_CURRENCY' => $app['currency'],
        ]);

        // Apply the same values to the already-booted config for the rest
        // of *this* request — the .env file only takes effect on the next
        // process/request otherwise, but migrations/seeders below need to
        // run right now, in this one.
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $db['host'],
            'database.connections.mysql.port' => $db['port'],
            'database.connections.mysql.database' => $db['database'],
            'database.connections.mysql.username' => $db['username'],
            'database.connections.mysql.password' => $db['password'] ?? '',
            'app.name' => $app['name'],
            'app.url' => $app['url'],
            'app.timezone' => $app['timezone'],
            'app.locale' => $app['locale'],
            'daftari.default_currency' => $app['currency'],
        ]);

        return __('Configuration saved.');
    }

    private function connectToDatabase(): string
    {
        // Forces a brand new connection using the config just written
        // above in writeEnvironment() — the app booted with whatever
        // .env had before this request, so the connection pool has to be
        // thrown away, not reused.
        DB::purge('mysql');
        DB::connection('mysql')->getPdo();

        return __('Connected to the database.');
    }

    private function runMigrations(): string
    {
        Artisan::call('migrate', ['--force' => true]);

        return __('Database migrations completed.');
    }

    private function runRequiredSeeders(): string
    {
        // Deliberately not the full DatabaseSeeder: AdminSeeder would
        // create a second, hardcoded admin account and RealCompanySeeder/
        // DemoSeeder seed data specific to this codebase's own dev
        // instance — none of that belongs in a real install. Only the
        // platform-wide reference data every install genuinely needs.
        foreach ([CurrencySeeder::class, PlanSeeder::class, AdminRoleSeeder::class] as $seeder) {
            Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
        }

        return __('Required setup completed.');
    }

    private function applyChosenCurrency(string $code): string
    {
        $currency = Currency::where('code', $code)->first();
        $currency?->makeDefault();

        return __('Default currency set to :code.', ['code' => $code]);
    }

    private function setUpStorage(): string
    {
        try {
            Artisan::call('storage:link');
        } catch (Throwable $e) {
            // Already linked is the only expected failure here — anything
            // else surfaces normally via the outer try/catch in runInstall().
            if (! str_contains($e->getMessage(), 'already exists') && ! str_contains($e->getMessage(), 'already linked')) {
                throw $e;
            }
        }

        return __('Storage set up.');
    }

    /**
     * updateOrCreate (rather than create) so a retry after a failure on a
     * later step in this same run — migrations/seeders already
     * succeeded, admin already created — doesn't die on a duplicate-email
     * constraint the second time through.
     */
    private function createAdministrator(array $admin): User
    {
        return User::updateOrCreate(
            ['email' => $admin['email']],
            [
                'company_id' => null,
                'name' => $admin['name'],
                'password' => $admin['password_hash'],
                'role' => 'super_admin',
                'status' => 'active',
            ]
        );
    }
}
