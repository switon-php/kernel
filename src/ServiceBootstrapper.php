<?php

declare(strict_types=1);

namespace Switon\Kernel;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\ComposerExtra\ComposerExtraInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ContainerInterface;
use Switon\Core\InjectorInterface;
use Switon\Core\Lazy;
use Switon\Core\PathAliasInterface;
use Switon\Core\ResourceAliasRegistrarInterface;
use Switon\Kernel\Event\ServiceBootstrapped;

/**
 * Bootstraps service providers from Composer extra metadata.
 *
 * Guidance:
 * - use this after bootstrap config is known but before runtime entrypoints start
 * - provider registration happens before user overrides; provider boot happens after injection
 *
 * Road-signs:
 * - discover providers
 * - register provider services
 * - apply user overrides
 * - inject providers
 * - boot providers and dispatch `ServiceBootstrapped`
 *
 * Core rules:
 * - pre-registered providers are excluded from Composer-extra discovery
 * - user config is applied after provider registration
 * - provider injection happens before `boot()`
 * - the completion event is dispatched only when an event dispatcher is bound
 *
 * @see \Switon\Kernel\Kernel
 * @see \Switon\ComposerExtra\ComposerExtraInterface
 * @see \Switon\Core\ServiceProviderInterface
 * @see \Switon\Core\ServiceProviderInterface::register()
 * @see \Switon\Core\ServiceProviderInterface::boot()
 * @see \Switon\Core\ContainerInterface::set()
 * @see \Switon\Kernel\Event\ServiceBootstrapped
 * @see \Switon\Kernel\Kernel::loadConfig()
 */
class ServiceBootstrapper
{
    #[Autowired] protected ContainerInterface $container;
    #[Autowired] protected InjectorInterface $injector;
    #[Autowired] protected ComposerExtraInterface $composerExtra;
    #[Autowired] protected EventDispatcherInterface|Lazy $eventDispatcher;
    #[Autowired] protected PathAliasInterface|Lazy $pathAlias;
    #[Autowired] protected ResourceAliasRegistrarInterface $resourceAliasRegistrar;

    /**
     * Run provider lifecycle: discover -> register -> config -> optional post-register hook -> inject -> boot -> event.
     *
     * @param array<string, mixed> $configurations User overrides registered after providers
     * @param callable|null $afterRegister Runs after config registration and before provider autowire/boot
     * @param list<class-string> $preRegisteredProviders Providers already registered before Composer-extra discovery
     */
    public function bootstrap(
        array     $configurations = [],
        ?callable $afterRegister = null,
        array     $preRegisteredProviders = [],
    ): void {
        $providerClasses = $this->discoverProviders($preRegisteredProviders);

        $providers = $this->registerProviders($providerClasses);
        $this->registerConfigurations($configurations);
        $this->registerProviderResourceAliases($providerClasses);

        if ($afterRegister !== null) {
            $afterRegister();
        }

        $this->autowireProviders($providers);
        $this->bootProviders($providers);

        if ($this->container->has(EventDispatcherInterface::class)) {
            $this->eventDispatcher->dispatch(new ServiceBootstrapped($providerClasses));
        }
    }

    /**
     * Registers all discovered service providers and keeps the created instances for later boot.
     *
     * Creates provider instances and calls register() on each.
     *
     * @param array<string> $providerClasses Provider class names
     *
     * @return array<object> Provider instances for later boot
     */
    protected function registerProviders(array $providerClasses): array
    {
        $providers = [];
        foreach ($providerClasses as $providerClass) {
            $provider = new $providerClass();
            $provider->register($this->container);
            $providers[] = $provider;
        }
        return $providers;
    }

    /**
     * Registers user configuration overrides after provider registration.
     *
     * User configurations can override services registered by providers.
     *
     * @param array<string, mixed> $configurations Service definitions
     *
     * @see \Switon\Di\Container::set() Definition registration boundary
     * @see \Switon\Kernel\Config::load() Config merge semantics (first wins)
     */
    protected function registerConfigurations(array $configurations): void
    {
        foreach ($configurations as $id => $definition) {
            $this->container->set($id, $definition);
        }
    }

    /**
     * Autowires all provider instances before any <code>boot()</code> call runs.
     *
     * Injects #[Autowired] properties into all provider instances.
     * This must be done before calling boot() on any provider.
     *
     * @param array<object> $providers Provider instances
     */
    protected function autowireProviders(array $providers): void
    {
        foreach ($providers as $provider) {
            $this->injector->inject($provider);
        }
    }

    /**
     * Register aliases declared by provider {@see \Switon\Core\Attribute\ResourceAlias} attributes.
     *
     * @param list<class-string> $providerClasses
     */
    protected function registerProviderResourceAliases(array $providerClasses): void
    {
        foreach ($providerClasses as $providerClass) {
            $this->resourceAliasRegistrar->register($this->pathAlias, $providerClass);
        }
    }

    /**
     * Boots all provider instances after every provider has been injected.
     *
     * Calls boot() on each provider after all providers have been autowired.
     * This ensures all providers have their dependencies available during boot.
     *
     * @param array<object> $providers Provider instances
     */
    protected function bootProviders(array $providers): void
    {
        foreach ($providers as $provider) {
            $provider->boot();
        }
    }

    /**
     * Discovers provider class names from <code>composer.json</code> <code>extra.switon.providers</code>.
     *
     * @param list<class-string> $preRegisteredProviders
     *
     * @return array<string> Provider class names
     */
    protected function discoverProviders(array $preRegisteredProviders = []): array
    {
        return array_values(array_filter(
            $this->composerExtra->collect('switon.providers'),
            static fn (string $providerClass): bool => !in_array($providerClass, $preRegisteredProviders, true)
        ));
    }
}
