<?php

declare(strict_types=1);

namespace Switon\Kernel\Exception;

use Switon\Kernel\Exception;

/**
 * Exception for missing referenced environment variables.
 *
 * Thrown when <code>.env</code> parsing references <code>$VAR</code> or
 * <code>${VAR}</code> before the variable exists.
 *
 * @see \Switon\Kernel\KernelInterface::start()
 * @see \Switon\Kernel\Env
 * @see \Switon\Kernel\Env::load()
 */
class EnvVariableNotFoundException extends Exception
{
}
