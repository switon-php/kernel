<?php

declare(strict_types=1);

namespace Switon\Kernel;

use Switon\Core\Exception as BaseException;

/**
 * Base exception for Kernel-related errors.
 *
 * @see \Switon\Kernel\KernelInterface
 * @see \Switon\Kernel\KernelInterface::start()
 * @see \Switon\Kernel\Kernel
 */
class Exception extends BaseException
{
}
