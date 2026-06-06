<?php

declare(strict_types=1);

namespace Switon\Kernel;

use Switon\Kernel\Tests\Fixtures\KernelFunctionOverrides;
use Stringable;

use function extension_loaded as global_extension_loaded;
use function function_exists as global_function_exists;
use function get_included_files as global_get_included_files;

function get_included_files(): array
{
    if (KernelFunctionOverrides::$includedFiles !== null) {
        return KernelFunctionOverrides::$includedFiles;
    }

    return global_get_included_files();
}

function extension_loaded(string $name): bool
{
    if (array_key_exists($name, KernelFunctionOverrides::$extensionLoaded)) {
        return KernelFunctionOverrides::$extensionLoaded[$name];
    }

    return global_extension_loaded($name);
}

function function_exists(string $name): bool
{
    if (array_key_exists($name, KernelFunctionOverrides::$functionExists)) {
        return KernelFunctionOverrides::$functionExists[$name];
    }

    return global_function_exists($name);
}

function console_log(string $level, string|Stringable $message, array $context = []): void
{
    KernelFunctionOverrides::$consoleLogs[] = [
        'level' => $level,
        'message' => (string)$message,
        'context' => $context,
    ];
}

function error_get_last(): ?array
{
    return KernelFunctionOverrides::$lastError ?? \error_get_last();
}
