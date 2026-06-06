<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Fixtures;

use Switon\Core\Lazy;
use Switon\Core\SceneManagerInterface;
use Switon\Kernel\Kernel;

class TestableKernel extends Kernel
{
    public bool $coroutineEnabled = false;

    public function __construct(string $root)
    {
        parent::__construct($root);
    }

    public function getRoot(): string
    {
        return $this->root;
    }

    public function exposedDetectCoroutineCanEnabled(): bool
    {
        return $this->detectCoroutineCanEnabled();
    }

    protected function detectCoroutineCanEnabled(): bool
    {
        return $this->coroutineEnabled;
    }

    public function exposedLoadConfig(): array
    {
        return $this->loadConfig();
    }

    public function exposedApplyClassScene(): void
    {
        $this->applyClassScene();
    }

    public function exposedLoadEnv(): void
    {
        $this->loadEnv();
    }

    public function exposedBootstrap(): void
    {
        $this->bootstrap();
    }

    /** @param array<string, mixed> $configurations */
    public function exposedFoldKernelServices(array $configurations): array
    {
        $this->services = ($configurations[static::class]['services'] ?? []) + $this->services;
        unset($configurations[static::class]);

        return $this->services + $configurations;
    }

    /** @param array<string, mixed> $services */
    public function setServices(array $services): void
    {
        $this->services = $services;
    }

    public function setSceneManager(SceneManagerInterface|Lazy $sceneManager): void
    {
        $this->sceneManager = $sceneManager;
    }

    /** @return array<string, mixed> */
    public function getServices(): array
    {
        return $this->services;
    }
}
