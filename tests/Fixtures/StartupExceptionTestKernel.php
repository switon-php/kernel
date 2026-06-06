<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Fixtures;

use Switon\Di\Container;
use Switon\Kernel\Kernel;
use Throwable;
use RuntimeException;

class StartupExceptionTestKernel extends Kernel
{
    /** @var list<array{level: string, message: string}> */
    public array $consoleLogs = [];

    protected ?Throwable $bootstrapException = null;

    public function setBootstrapException(Throwable $bootstrapException): void
    {
        $this->bootstrapException = $bootstrapException;
    }

    public function setTestContainer(Container $container): void
    {
        $this->container = $container;
    }

    public function exposedHandleStartupException(Throwable $e): void
    {
        $this->handleStartupException($e);
    }

    protected function bootstrap(): void
    {
        throw $this->bootstrapException ?? new RuntimeException('Bootstrap failed');
    }

    protected function logToConsole(string $level, string $message): void
    {
        $this->consoleLogs[] = ['level' => $level, 'message' => $message];
    }

    protected function terminateStartupFailure(): never
    {
        throw new StartupTerminationException('Kernel startup terminated');
    }
}
