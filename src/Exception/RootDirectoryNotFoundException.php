<?php

declare(strict_types=1);

namespace Switon\Kernel\Exception;

use Switon\Kernel\Exception;

/**
 * Exception for missing auto-detected project root directory.
 *
 * Thrown when kernel bootstrap cannot resolve root from <code>vendor/autoload.php</code>.
 *
 * @see \Switon\Kernel\KernelInterface::start()
 * @see \Switon\Kernel\Kernel
 */
class RootDirectoryNotFoundException extends Exception
{
}
