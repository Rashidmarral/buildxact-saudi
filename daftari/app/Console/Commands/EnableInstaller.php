<?php

namespace App\Console\Commands;

use App\Support\Installer\InstallerLock;
use Illuminate\Console\Command;

/**
 * The only supported way to make the /install wizard reachable again
 * after a successful install (Module 24) — deleting InstallerLock's
 * marker file is exactly what this does, deliberately gated behind a CLI
 * command (and a confirmation prompt) rather than anything reachable
 * from the web, since the wizard can reconfigure the database and create
 * a new platform administrator.
 */
class EnableInstaller extends Command
{
    protected $signature = 'installer:enable {--force : Skip the confirmation prompt}';

    protected $description = 'Re-enable the /install wizard after a completed installation';

    public function handle(): int
    {
        if (! InstallerLock::isInstalled()) {
            $this->info('The installer is already enabled — nothing to do.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('This re-opens /install to anyone who can reach this server, letting them reconfigure the database and create a new administrator. Continue?')) {
            $this->warn('Cancelled — the installer stays locked.');

            return self::SUCCESS;
        }

        InstallerLock::unlock();
        $this->info('The installer is re-enabled. Lock it again by completing the wizard, or restrict access to /install at the web server level.');

        return self::SUCCESS;
    }
}
