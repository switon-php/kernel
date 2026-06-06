<?php

declare(strict_types=1);

namespace Switon\Kernel\Exception;

use Switon\Kernel\Exception;

/**
 * Exception for malformed <code>.env</code> file syntax.
 *
 * Thrown when quoted values are not closed correctly during <code>.env</code> parsing.
 *
 * @see \Switon\Kernel\Env
 * @see \Switon\Kernel\Env::load()
 * @see \Switon\Kernel\Exception\EnvVariableNotFoundException
 */
class MalformedEnvFileException extends Exception
{
}
