<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Unit;

use PHPUnit\Framework\MockObject\MockObject;
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
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(
            static fn (string $id): bool => $id === EventDispatcherInterface::class,
        );
        $container->expects($this->never())->method('set');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof ServiceBootstrapped && $event->providers === [];
            }));

        $bootstrapper = $this->make(ServiceBootstrapper::class, [
            'composerExtra' => $this->composerExtraMock(),
            'container' => $container,
            'eventDispatcher' => $eventDispatcher,
        ]);

        $bootstrapper->bootstrap();
    }

    public function testBootstrapWithProviders(): void
    {
        $testProviderClass = TestServiceProvider::class;

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
            'composerExtra' => $this->composerExtraMock([$testProviderClass]),
            'container' => $container,
            'injector' => $injector,
            'eventDispatcher' => $eventDispatcher,
        ]);

        $bootstrapper->bootstrap();
    }

    public function testBootstrapWithConfigurations(): void
    {
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
            'composerExtra' => $this->composerExtraMock(),
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
            'composerExtra' => $this->composerExtraMock(),
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
            'composerExtra' => $this->composerExtraMock([$testProviderClass]),
            'container' => $container,
            'injector' => $injector,
            'eventDispatcher' => $eventDispatcher,
        ]);

        $bootstrapper->bootstrap();
    }

    public function testBootstrapSkipsPreRegisteredProvidersDiscoveredFromComposerExtra(): void
    {
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
            'composerExtra' => $this->composerExtraMock([
                PreRegisteredProviderA::class,
                PreRegisteredProviderB::class,
                TestServiceProvider::class,
            ]),
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

        $bootstrapper = $this->make(ServiceBootstrapper::class, [
            'composerExtra' => $this->composerExtraMock([
                ResourceAliasServiceProvider::class,
            ]),
        ]);

        $bootstrapper->bootstrap();

        $expected = strtr(dirname(__DIR__, 2), '\\', '/') . '/resources';
        $pathAlias = $this->container->get(PathAliasInterface::class);

        $this->assertSame($expected, ResourceAliasServiceProvider::$bootAlias);
        $this->assertSame($expected, $pathAlias->get('@switon.kernel.resources'));
    }

    public function testBootstrapRegistersCustomResourceAliasBeforeBoot(): void
    {
        CustomResourceAliasServiceProvider::$bootAlias = null;

        $bootstrapper = $this->make(ServiceBootstrapper::class, [
            'composerExtra' => $this->composerExtraMock([
                CustomResourceAliasServiceProvider::class,
            ]),
        ]);

        $bootstrapper->bootstrap();

        $expected = strtr(dirname(__DIR__, 2), '\\', '/') . '/tests/Fixtures';
        $pathAlias = $this->container->get(PathAliasInterface::class);

        $this->assertSame($expected, CustomResourceAliasServiceProvider::$bootAlias);
        $this->assertSame($expected, $pathAlias->get('@switon.kernel.fixtures'));
    }

    /**
     * Bootstrap now collects both providers and tools metadata.
     *
     * @param list<class-string> $providers
     * @param array<string, class-string> $tools
     *
     * @return ComposerExtraInterface&MockObject
     */
    protected function composerExtraMock(array $providers = [], array $tools = []): ComposerExtraInterface
    {
        $composerExtra = $this->createMock(ComposerExtraInterface::class);
        $composerExtra->expects($this->atLeast(1))
            ->method('collect')
            ->willReturnCallback(static function (string $key) use ($providers, $tools): array {
                return match ($key) {
                    'switon.providers' => $providers,
                    'switon.tools' => $tools,
                    default => [],
                };
            });

        return $composerExtra;
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
