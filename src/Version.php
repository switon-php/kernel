<?php

declare(strict_types=1);

namespace Switon\Kernel;

use Switon\Core\Json;
use Switon\Core\Runtime;
use Throwable;

use function file_get_contents;
use function is_array;
use function is_string;

/**
 * Reads the Switon distribution version from the current app root composer.json.
 *
 * Use when runtime output should distinguish framework/distribution version from
 * <code>AppInterface::version()</code>, which remains the application version.
 *
 * Road-signs:
 * - root path via Runtime::getRoot()
 * - source: composer.json extra.switon.version
 * - fallback 0.0.0 on missing or unreadable metadata
 *
 * @see \Switon\Kernel\VersionInterface
 * @see \Switon\Core\Runtime::getRoot()
 * @see \Switon\Core\AppInterface::version()
 */
class Version implements VersionInterface
{
    protected const string DEFAULT_VERSION = '0.0.0';

    protected ?string $resolvedVersion = null;

    /**
     * @param string|null $composerFile Override root composer.json for tests or special runtimes
     */
    public function __construct(protected ?string $composerFile = null)
    {
    }

    /**
     * Returns the resolved distribution version, caching the first successful lookup.
     */
    public function version(): string
    {
        return $this->resolvedVersion ?? ($this->resolvedVersion = $this->readVersion());
    }

    /**
     * Reads <code>extra.switon.version</code> from the configured or runtime root composer.json file.
     */
    protected function readVersion(): string
    {
        try {
            $composerFile = $this->composerFile ?? Runtime::getRoot() . '/composer.json';
        } catch (Throwable) {
            return self::DEFAULT_VERSION;
        }

        $content = @file_get_contents($composerFile);
        if ($content === false) {
            return self::DEFAULT_VERSION;
        }

        try {
            $composer = Json::parse($content);
        } catch (Throwable) {
            return self::DEFAULT_VERSION;
        }

        if (!is_array($composer)) {
            return self::DEFAULT_VERSION;
        }

        $extra = $composer['extra'] ?? null;
        if (!is_array($extra)) {
            return self::DEFAULT_VERSION;
        }

        $switon = $extra['switon'] ?? null;
        if (!is_array($switon)) {
            return self::DEFAULT_VERSION;
        }

        $version = $switon['version'] ?? null;
        if (!is_string($version) || $version === '') {
            return self::DEFAULT_VERSION;
        }

        return $version;
    }
}
