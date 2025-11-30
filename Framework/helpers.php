<?php

if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Must contain "="
            if (!str_contains($line, '=')) {
                continue;
            }

            // Split at first "=" only
            [$key, $value] = explode('=', $line, 2);

            $key = trim($key);
            $value = trim($value);

            // Strip surrounding quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Set PHP environment variables
            putenv("$key=$value");
            $_ENV[$key] = $value; // Most frameworks rely on $_ENV
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        // Prefer $_ENV first (PHP best practice)
        if (array_key_exists($key, $_ENV)) {
            return castEnvValue($_ENV[$key]);
        }

        // Fallback to getenv()
        $value = getenv($key);
        if ($value !== false) {
            return castEnvValue($value);
        }

        return $default;
    }
}

/**
 * Cast environment values to PHP types:
 * - "true"  => true
 * - "false" => false
 * - "1"     => 1 (int)
 * - "0"     => 0 (int)
 */
if (!function_exists('castEnvValue')) {
    function castEnvValue(string $value): mixed
    {
        $lower = strtolower($value);

        return match ($lower) {
            'true' => true,
            'false' => false,
            '1' => 1,
            '0' => 0,
            default => $value,
        };
    }
}
