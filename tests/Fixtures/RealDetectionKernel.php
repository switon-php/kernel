<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Fixtures;

use Switon\Kernel\Kernel;

class RealDetectionKernel extends Kernel
{
    public function exposedDetectCoroutineCanEnabled(): bool
    {
        return $this->detectCoroutineCanEnabled();
    }

    public function exposedIsXdebugActive(): bool
    {
        return $this->isXdebugActive();
    }
}
