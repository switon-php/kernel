<?php

declare(strict_types=1);

namespace Switon\Kernel;

/**
 * Contract for reading the Switon distribution version declared by the current app.
 *
 * Guidance:
 * - use this when callers need the framework or distribution version instead of the app version
 * - implementations read from root <code>composer.json</code> metadata and provide a fallback when absent
 *
 * Road-signs:
 * - root composer.json
 * - extra.switon.version
 * - fallback 0.0.0
 *
 * @see \Switon\Kernel\Version
 */
interface VersionInterface
{
    /**
     * Get the Switon distribution version declared by the current app.
     */
    public function version(): string;
}
