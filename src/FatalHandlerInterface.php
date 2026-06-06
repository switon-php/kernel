<?php

declare(strict_types=1);

namespace Switon\Kernel;

/**
 * Contract for components that handle PHP fatal errors.
 *
 * Guidance:
 * - register this during bootstrap so shutdown-time fatal errors can still be observed
 * - implementations may translate fatal errors into framework events or other reporting hooks
 *
 * Road-signs:
 * - register shutdown callback
 * - fatal types only
 * - dispatch FatalErrorOccurred
 * - ignore non-fatal errors
 * - swallow dispatch failures
 *
 * @see \Switon\Kernel\FatalHandler
 * @see \Switon\Kernel\FatalHandlerInterface::register()
 * @see \Switon\Kernel\Event\FatalErrorOccurred
 */
interface FatalHandlerInterface
{
    /**
     * Register a shutdown callback for fatal error handling.
     */
    public function register(): void;
}
