<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/**
 * Documented, one-command way to install (or refresh) Daftri's safe demo
 * data (Module 23) — wraps DemoSeeder, which builds one self-contained
 * demo company ("Al Rashid Trading Co.") with demo users, customers,
 * suppliers, products, invoices, expenses and payments. Every report in
 * the app then reads live from that same data — there's nothing extra to
 * seed for reports.
 *
 * Safe to run against a live database: DemoSeeder only ever touches rows
 * belonging to that one company (matched by its fixed slug), so this
 * never modifies any real customer's data. See demo:reset to remove it
 * again.
 */
class InstallDemoData extends Command
{
    protected $signature = 'demo:install
                            {--fresh : Delete any existing demo data first, then install a clean copy (equivalent to running demo:reset --force first)}';

    protected $description = 'Install (or refresh) Daftri\'s safe, self-contained demo company and sample data — never touches real customer data';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->call('demo:reset', ['--force' => true]);
        }

        $this->info('Seeding the demo company and its sample data...');
        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $this->newLine();
        $this->info('Demo data is ready.');
        $this->line('  Company: Al Rashid Trading Co.');
        $this->line('  Owner login: owner@daftari.local / Demo@12345');
        $this->line('  Team logins: accountant@daftari.local, sales@daftari.local (same password)');
        $this->line('  Remove it anytime with: php artisan demo:reset');

        return self::SUCCESS;
    }
}
