<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Unit;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\ComposerExtra\ComposerExtraInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Attribute\ResourceAlias;
use Switon\Core\ContainerInterface;
use Switon\Core\InjectorInterface;
use Switon\Core\PathAliasInterface;
use Switon\Di\ServiceProvider as DiServiceProvider;
use Switon\Kernel\Event\ServiceBootstrapped;
use Switon\Kernel\ServiceBootstrapper;
use Switon\Kernel\Tests\TestCase;

/**
 * @covers \Switon\Kernel\ServiceBootstrapper
 */
class ServiceBootstrapperTest extends TestCase
{
    public function testBootstrapWithNoProviders(): void
    {
        $composerExtra = $this->createMock(ComposerExtraInterface::class);
        $composerExtra->expects($this->once())
            ->method('collect')
            ->with('switon.providers')
            ->willReturn([]);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with(EventDispatcherInterface::class)->willReturn(true);
        $container->expects($this->never())->method('set');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof ServiceBootstrapped && $event->providers === [];
            }));

        $bootstrapper = $this->make(ServiceBootstrapper::class, [
            'composerExtra' => $composerExtra,
            'container' => $container,
            'eventDispatcher' => $eventDispatcher,
        ]);

        $bootstrapper->bootstrap();
    }

    public function testBootstrapWithProviders(): void
    {
        $testProviderClass = TestServiceProvider::class;

        $composerExtra = $this->createMock(ComposerExtraInterface::class);
        $composerExtra->expects($this->once())
            ->method('collect')
            ->with('switon.providers')
            ->willReturn([$testProviderClass]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);

        $injector = $this->createMock(InjectorInterface::class);
        $injector->expects($this->once())
            ->method('inject')
            ->with($this->isInstanceOf(TestServiceProvider::class));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($testProviderClass) {
                return $event instanceof ServiceBootstrapped
                    && $event->providers === [$testProviderClass];
            }));

        $bootstrapper = $this->make(ServiceBootstrapper::class, [
            'composerExtra' => $composerExtra,
            'container' => $container,
            'injector' => $injector,
            'eventDispatcher' => $eventDispatcher,
        ]);

        $bootstrapper->bootstrap();
    }

    public function testBootstrapWithConfigurations(): void
    {
        $composerExtra = $this->createMock(ComposerExtraInterface::class);
        $composerExtra->expects($this->once())
            ->method('collect')
            ->with('switon.providers')
            ->willReturn([]);

        $setCallCount = 0;
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $container->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function ($id, $definition) use (&$setCallCount, $container) {
                $setCallCount++;

                if ($setCallCount === 1) {
                    $this->assertSame('service1', $id);
                    $this->assertSame('definition1', $definition);
                } elseif ($setCallCount === 2) {
                    $this->assertSame('service2', $id);
                    $this->assertSame('definition2', $definition);
                }

                return $container;
            });

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);

        $bootstrapper = $this->make(ServiceBootstrapper::class, [
            'composerExtra' => $composerExtra,
            'container' => $container,
            'eventDispatcher' => $eventDispatcher,
        ]);

        $bootstrapper->bootstrap([
            'service1' => 'definition1',
            'service2' => 'definition2',
        ]);
    }

    public function testBootstrapRunsAfterRegisterHookAfterConfigurations(): void
    {
        $composerExtra = $this->createMock(ComposerExtraInterface::class);
        $composerExtra->expects($this->once())
            ->method('collect')
            ->with('switon.providers')
            ->willReturn([]);

        $setCallCount = 0;
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $container->expects($this->once())
            ->method('set')
            ->willReturnCallback(function () use (&$setCallCount, $container) {
                $setCallCount++;
                return $container;
            });

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);

        $bootstrapper = $this->make(ServiceBootstrapper::class, [
            'composerExtra' => $composerExtra,
            'container' => $container,
            'eventDispatcher' => $eventDispatcher,
        ]);

        $afterRegisterRun = false;

        $bootstrapper->bootstrap(
            ['service1' => 'definition1'],
            function () use (&$afterRegisterRun, &$setCallCount): void {
                $afterRegisterRun = true;
                $this->assertSame(1, $setCallCount);
            }
        );

        $this->assertTrue($afterRegisterRun);
    }

    public function testBootstrapWithMultiplePackages(): void
    {
        $testProviderClass = TestServiceProvider::class;

        $composerExtra = $this->createMock(ComposerExtraInterface::class);
        $composerExtra->expects($this->once())
            ->method('collect')
            ->with('switon.providers')
            ->willReturn([$testProviderClass]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);

        $injector = $this->createMock(InjectorInterface::class);
        $injector->expects($this->once())
            ->method('inject')
            ->with($this->isInstanceOf(TestServiceProvider::class));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($testProviderClass) {
                return $event instanceof ServiceBootstrapped
                    && $event->providers === [$testProviderClass];
            }));

        $bootstrapper = $this->make(ServiceBootstrapper::class, [
            'composerExtra' => $composerExtra,
            'container' => $container,
            'injector' => $injector,
            'eventDispatcher' => $eventDispatcher,
        ]);

        $bootstrapper->bootstrap();
    }

    public function testBootstrapSkipsPreRegisteredProvidersDiscoveredFromComposerExtra(): void
    {
        $composerExtra = $this->createMock(ComposerExtraInterface::class);
        $composerExtra->expects($this->once())
            ->method('collect')
            ->with('switon.providers')
            ->willReturn([
                PreRegisteredProviderA::class,
                PreRegisteredProviderB::class,
                TestServiceProvider::class,
            ]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);

        $injector = $this->createMock(InjectorInterface::class);
        $injector->expects($this->once())
            ->method('inject')
            ->with($this->isInstanceOf(TestServiceProvider::class));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof ServiceBootstrapped
                    && $event->providers === [TestServiceProvider::class];
            }));

        $bootstrapper = $this->make(ServiceBootstrapper::class, [
            'composerExtra' => $composerExtra,
            'container' => $container,
            'injector' => $injector,
            'eventDispatcher' => $eventDispatcher,
        ]);

        $bootstrapper->bootstrap([], null, [
            PreRegisteredProviderA::class,
            PreRegisteredProviderB::class,
        ]);
    }

    public function testBootstrapRegistersDefaultResourceAliasBeforeBoot(): void
    {
        ResourceAliasServiceProvider::$bootAlias = null;

        $composerExtra = $this->createMock(ComposerExtraInterface::class);
        $composerExtra->expects($this->once())
            ->method('collect')
            ->with('switon.providers')
            ->willReturn([
                ResourceAliasServiceProvider::class,
            ]);

        $bootstrapper = $this->make(ServiceBootstrapper::class, [
            'composerExtra' => $composerExtra,
        ]);

        $bootstrapper->bootstrap();

        $expected = dirname(__DIR__, 2) . '/resources';
        $pathAlias = $this->container->get(PathAliasInterface::class);

        $this->assertSame($expected, ResourceAliasServiceProvider::$bootAlias);
        $this->assertSame($expected, $pathAlias->get('@switon.kernel.resources'));
    }

    public function testBootstrapRegistersCustomResourceAliasBeforeBoot(): void
    {
        CustomResourceAliasServiceProvider::$bootAlias = null;

        $composerExtra = $this->createMock(ComposerExtraInterface::class);
        $composerExtra->expects($this->once())
            ->method('collect')
            ->with('switon.providers')
            ->willReturn([
                CustomResourceAliasServiceProvider::class,
            ]);

        $bootstrapper = $this->make(ServiceBootstrapper::class, [
            'composerExtra' => $composerExtra,
        ]);

        $bootstrapper->bootstrap();

        $expected = dirname(__DIR__, 2) . '/tests/Fixtures';
        $pathAlias = $this->container->get(PathAliasInterface::class);

        $this->assertSame($expected, CustomResourceAliasServiceProvider::$bootAlias);
        $this->assertSame($expected, $pathAlias->get('@switon.kernel.fixtures'));
    }
}

class TestServiceProvider extends \Switon\Di\ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
    }

    public function boot(): void
    {
    }
}

class PreRegisteredProviderA extends \Switon\Di\ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
    }

    public function boot(): void
    {
    }
}

class PreRegisteredProviderB extends \Switon\Di\ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
    }

    public function boot(): void
    {
    }
}

#[ResourceAlias]
class ResourceAliasServiceProvider extends \Switon\Di\ServiceProvider
{
    public static ?string $bootAlias = null;

    #[Autowired] protected PathAliasInterface $pathAlias;

    public function register(ContainerInterface $container): void
    {
    }

    public function boot(): void
    {
        self::$bootAlias = $this->pathAlias->get('@switon.kernel.resources');
    }
}

#[ResourceAlias(path: 'tests/Fixtures', alias: '@switon.kernel.fixtures')]
class CustomResourceAliasServiceProvider extends DiServiceProvider
{
    public static ?string $bootAlias = null;

    #[Autowired] protected PathAliasInterface $pathAlias;

    public function register(ContainerInterface $container): void
    {
    }

    public function boot(): void
    {
        self::$bootAlias = $this->pathAlias->get('@switon.kernel.fixtures');
    }
}
