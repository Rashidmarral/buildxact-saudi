<?php

namespace App\Support\Installer;

use PDO;
use PDOException;

/**
 * Step 2 of the installer (Module 24): tests a set of database credentials
 * with a raw, throwaway PDO connection — deliberately not through
 * Laravel's `DB` facade, since the app's own connection is still pointed
 * at whatever .env already has (or nothing at all) at this point in the
 * wizard. Never returns the password back to the caller, only a
 * pass/fail and a driver-provided message (which — like any standard
 * "access denied" error — may echo the username, never the password).
 */
class DatabaseConnectionTester
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function test(string $host, string $port, string $database, string $username, string $password): array
    {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

        try {
            new PDO($dsn, $username, $password, [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            return ['ok' => true, 'message' => __('Connected successfully.')];
        } catch (PDOException $e) {
            return ['ok' => false, 'message' => $this->friendlyMessage($e, $database)];
        }
    }

    /**
     * PDO's raw exception text is safe to show (drivers don't echo the
     * password), but "SQLSTATE[HY000] [1049] Unknown database..." isn't
     * exactly welcoming — this maps the handful of common cases to plain
     * language and falls back to the driver message for anything else.
     */
    private function friendlyMessage(PDOException $e, string $database): string
    {
        $code = (int) $e->getCode();
        $message = $e->getMessage();

        if ($code === 2002 || str_contains($message, 'Connection refused') || str_contains($message, 'getaddrinfo')) {
            return __('Could not reach the database server — check the host and port.');
        }

        if (str_contains($message, 'Access denied')) {
            return __('Access denied — check the username and password.');
        }

        if (str_contains($message, 'Unknown database')) {
            return __('Database ":database" does not exist. Create it first, then try again.', ['database' => $database]);
        }

        return $message;
    }
}
