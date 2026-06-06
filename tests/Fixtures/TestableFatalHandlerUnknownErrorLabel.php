<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Fixtures;

class TestableFatalHandlerUnknownErrorLabel extends TestableFatalHandler
{
    public const SYNTHETIC_FATAL_TYPE = 901_901;

    protected function isFatalError(?array $error): bool
    {
        return isset($error['type']) && $error['type'] === self::SYNTHETIC_FATAL_TYPE;
    }
}
