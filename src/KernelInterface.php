<?php

declare(strict_types=1);

namespace Switon\Kernel;

/**
 * Contract for starting a Switon application kernel.
 *
 * Guidance:
 * - use this as the bootstrap entrypoint contract for app runtimes
 * - implementations are responsible for preparing services before handing off to HTTP, CLI, or worker loops
 *
 * Road-signs:
 * - start kernel
 * - bootstrap sequence in Kernel
 * - env + config before runtime
 * - startup exception handling
 * - HTTP / CLI kernels
 *
 * @see \Switon\Kernel\Kernel
 * @see \Switon\Kernel\KernelInterface::start()
 * @see \Switon\Kernel\Exception
 * @see \Switon\Cli\Kernel Typical consumer
 * @see \Switon\Http\Kernel Typical consumer
 */
interface KernelInterface
{
    /**
     * Bootstrap services and start the runtime entrypoint.
     */
    public function start(): void;
}
