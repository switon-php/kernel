<?php

declare(strict_types=1);

namespace Switon\Kernel\Event;

/**
 * Service bootstrap completed event.
 *
 * Dispatched after all service providers have been registered and booted.
 * Contains the list of all provider class names that were bootstrapped.
 *
 * Log category: <code>switon.kernel.service.bootstrapped</code>
 *
 * @see \Switon\Kernel\ServiceBootstrapper
 * @see \Switon\Kernel\ServiceBootstrapper::bootstrap()
 * @see \Switon\Core\ServiceProviderInterface
 */
readonly class ServiceBootstrapped
{
    /**
     * @param array<string> $providers Provider class names that were bootstrapped
     */
    public function __construct(
        public array $providers
    ) {
    }
}
