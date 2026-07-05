<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Switon\Core\App;
use Switon\Core\InjectorInterface;
use Switon\Core\PathAlias;
use Switon\Core\PathAliasInterface;
use Switon\Di\Container as DiContainer;
use Switon\Di\ServiceProvider as DiServiceProvider;

use function sys_get_temp_dir;

abstract class TestCase extends BaseTestCase
{
    protected DiContainer $container;
    protected InjectorInterface $injector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new DiContainer();
        $diProvider = new DiServiceProvider();
        $diProvider->register($this->container);
        $diProvider->boot();

        App::setContainer($this->container);

        $pathAlias = new PathAlias();
        $pathAlias->set('@runtime', sys_get_temp_dir());
        $this->container->set(PathAliasInterface::class, $pathAlias);
        $this->container->set(PathAlias::class, $pathAlias);

        $this->injector = $this->container->get(InjectorInterface::class);
        $this->setUpContainer();
        $this->injector->inject($this);
    }

    protected function setUpContainer(): void
    {
    }

    protected function make(string $name, array $parameters = []): mixed
    {
        return $this->container->make($name, $parameters);
    }
}
