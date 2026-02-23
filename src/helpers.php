<?php

use Illuminate\Support\Facades\File;

if (!function_exists('updateEnv')) {
    function updateEnv(string $key, mixed $value, bool $override = true): bool
    {
        $envFilePath = base_path('.env');

        if (!File::exists($envFilePath)) {
            return false;
        }

        $envContents = File::get($envFilePath);

        // Normalize value (quote if contains spaces)
        $value = trim((string) $value);
        if (str_contains($value, ' ') && !str_starts_with($value, '"')) {
            $value = '"' . $value . '"';
        }

        $keyPattern = "/^" . preg_quote($key, '/') . "=(.*)$/m";

        if (preg_match($keyPattern, $envContents)) {
            // Key exists
            if (!$override) {
                return true; // do nothing
            }

            // Replace existing key
            $envContents = preg_replace(
                $keyPattern,
                $key . '=' . $value,
                $envContents
            );
        } else {
            // Append new key
            $envContents .= PHP_EOL . $key . '=' . $value;
        }

        File::put($envFilePath, $envContents);

        return true;
    }
}