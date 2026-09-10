<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class EnvManager
{
    protected string $envPath;

    public function __construct()
    {
        $this->envPath = base_path('.env');
    }

    /**
     * Get a configuration value from .env
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $env = $this->parseEnv();

        return $env[$key] ?? $default;
    }

    /**
     * Set a configuration value in .env
     */
    public function put(string $key, ?string $value): void
    {
        $env = $this->parseEnv();
        $env[$key] = $value;

        $this->writeEnv($env);

        // Bust any cached env values
        Cache::forget("env.{$key}");
    }

    /**
     * Get multiple configuration values at once
     *
     * @param  array<int, string>  $keys
     * @return array<string, ?string>
     */
    public function getMany(array $keys): array
    {
        $env = $this->parseEnv();
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $env[$key] ?? null;
        }

        return $result;
    }

    /**
     * Set multiple configuration values at once
     */
    /** @param array<string, ?string> $values */
    public function putMany(array $values): void
    {
        $env = $this->parseEnv();

        foreach ($values as $key => $value) {
            $env[$key] = $value;
        }

        $this->writeEnv($env);

        // Bust cache for all updated keys
        foreach (array_keys($values) as $key) {
            Cache::forget("env.{$key}");
        }
    }

    /**
     * Parse the .env file into an associative array
     */
    /** @return array<string, string> */
    protected function parseEnv(): array
    {
        if (! file_exists($this->envPath)) {
            return [];
        }

        $env = [];
        $lines = file($this->envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            // Skip comments
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // Skip lines without =
            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            $env[$key] = $value;
        }

        return $env;
    }

    /**
     * Write the configuration array back to .env file
     */
    /** @param array<string, ?string> $env */
    protected function writeEnv(array $env): void
    {
        $lines = [];

        // Try to preserve original file structure for existing keys
        if (file_exists($this->envPath)) {
            $originalLines = file($this->envPath, FILE_IGNORE_NEW_LINES);

            if ($originalLines === false) {
                $originalLines = [];
            }

            foreach ($originalLines as $line) {
                // Preserve comments and empty lines
                if (empty(trim($line)) || str_starts_with(trim($line), '#')) {
                    $lines[] = $line;

                    continue;
                }

                // Check if this line has a key we're updating
                if (str_contains($line, '=')) {
                    [$key] = explode('=', $line, 2);
                    $key = trim($key);

                    if (isset($env[$key])) {
                        $value = $env[$key];
                        // Quote value if it contains spaces or special chars
                        if ($value && (str_contains($value, ' ') || str_contains($value, '"'))) {
                            $value = '"'.addslashes($value).'"';
                        }
                        $lines[] = "{$key}={$value}";
                        unset($env[$key]);
                    }
                }
            }
        }

        // Add any new keys that weren't in the original file
        foreach ($env as $key => $value) {
            if ($value && (str_contains($value, ' ') || str_contains($value, '"'))) {
                $value = '"'.addslashes($value).'"';
            }
            $lines[] = "{$key}={$value}";
        }

        file_put_contents($this->envPath, implode(PHP_EOL, $lines).PHP_EOL);
    }
}
