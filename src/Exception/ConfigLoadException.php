<?php

declare(strict_types=1);

namespace Switon\Kernel\Exception;

use Switon\Kernel\Exception;

/**
 * Exception for kernel configuration loading failures.
 *
 * Thrown when configuration files cannot be loaded from the config directory.
 *
 * @see \Switon\Kernel\KernelInterface::start()
 * @see \Switon\Kernel\Config
 * @see \Switon\Kernel\Config::load()
 */
class ConfigLoadException extends Exception
{
}
