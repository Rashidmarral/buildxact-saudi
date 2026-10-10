<?php

namespace App\Support\Installer;

/**
 * Writes the wizard's collected values into the .env file (Module 24,
 * Step 5) — the only place database credentials or admin details ever
 * land on disk; nothing from the wizard is ever committed to source
 * control or hardcoded, by construction, since this only ever writes to
 * the gitignored .env at runtime.
 *
 * Takes an explicit path (defaults to base_path('.env')) so tests can
 * point it at a throwaway file instead of the real one.
 */
class EnvWriter
{
    public function __construct(private readonly string $path = '')
    {
    }

    private function targetPath(): string
    {
        return $this->path !== '' ? $this->path : base_path('.env');
    }

    /**
     * @param  array<string, string|int|bool|null>  $values
     */
    public function write(array $values): void
    {
        $path = $this->targetPath();
        $contents = file_exists($path) ? file_get_contents($path) : '';
        $lines = $contents === '' ? [] : explode("\n", $contents);

        foreach ($values as $key => $value) {
            $lines = $this->setKey($lines, $key, $this->format($value));
        }

        file_put_contents($path, implode("\n", $lines));
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<int, string>
     */
    private function setKey(array $lines, string $key, string $formattedValue): array
    {
        $pattern = '/^'.preg_quote($key, '/').'=/';
        $found = false;

        foreach ($lines as $i => $line) {
            if (preg_match($pattern, $line)) {
                $lines[$i] = "{$key}={$formattedValue}";
                $found = true;
                break;
            }
        }

        if (! $found) {
            $lines[] = "{$key}={$formattedValue}";
        }

        return $lines;
    }

    private function format(string|int|bool|null $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        if ($value === '' || preg_match('/[\s#"\'\\\\]/', $value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
