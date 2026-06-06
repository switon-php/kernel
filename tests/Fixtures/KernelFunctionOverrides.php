<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Fixtures;

class KernelFunctionOverrides
{
    public static ?array $includedFiles = null;

    /** @var array<string, bool> */
    public static array $extensionLoaded = [];

    /** @var array<string, bool> */
    public static array $functionExists = [];

    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public static array $consoleLogs = [];

    /** @var array{type?: int, message?: string, file?: string, line?: int}|null */
    public static ?array $lastError = null;

    public static function reset(): void
    {
        self::$includedFiles = null;
        self::$extensionLoaded = [];
        self::$functionExists = [];
        self::$consoleLogs = [];
        self::$lastError = null;
    }
}
