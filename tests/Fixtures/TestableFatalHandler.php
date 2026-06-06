<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Fixtures;

use Closure;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Lazy;
use Switon\Kernel\FatalHandler;

class TestableFatalHandler extends FatalHandler
{
    protected ?Closure $registeredShutdownHandler = null;

    /** @var array{type?: int, message?: string, file?: string, line?: int}|null */
    protected ?array $lastError = null;

    public function setEventDispatcher(EventDispatcherInterface|Lazy $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /** @param array{type?: int, message?: string, file?: string, line?: int}|null $error */
    public function setLastError(?array $error): void
    {
        $this->lastError = $error;
    }

    public function runRegisteredShutdown(): void
    {
        ($this->registeredShutdownHandler)();
    }

    protected function registerShutdownFunction(callable $handler): void
    {
        $this->registeredShutdownHandler = Closure::fromCallable($handler);
    }

    protected function getLastError(): ?array
    {
        return $this->lastError;
    }
}
