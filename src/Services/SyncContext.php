<?php

namespace Sosupp\SlimerDesktop\Services;

class SyncContext
{
    protected static bool $enabled = true;
    protected static array $buffer = [];

    public static function disable(): void 
    {
        self::$enabled = false;
    }

    public static function enable(): void 
    {
        self::$enabled = true;
    }

    public static function isEnabled(): bool 
    {
        return self::$enabled;
    }

    public static function addToBuffer(array $data): void 
    {
        self::$buffer[] = $data;
    }

    public static function flush(): array 
    {
        $data = self::$buffer;
        self::$buffer = [];
        return $data;
    }
}