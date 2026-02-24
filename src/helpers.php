<?php

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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

if (!function_exists('remoteSyncToken')) {
    function remoteSyncToken()
    {
        $token = cache()->get('sync_remote_token');

        if ($token) {
            return $token;
        }

        $secret = config('slimerdesktop.jwt.secret');
        $payload = [
            'iss' => config('services.desktop.jwt.iss'),
            'iat' => now()->timestamp,
            'exp' => now()->addHours(12)->timestamp,
            'source' => 'local',
        ];

        $token = JWT::encode($payload, $secret, 'HS256');

        cache()->put('sync_remote_token', $token, now()->addHours(12));

        return $token;
    }
}

if (!function_exists('cleanTenantName')) {
    function cleanTenantName(string $name): string
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9]/', '', $name);
        return Str::lower($cleaned);
    }
}