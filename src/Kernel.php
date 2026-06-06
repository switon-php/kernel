<?php

declare(strict_types=1);

namespace Switon\Kernel;

use Psr\Log\LoggerInterface;
use ReflectionClass;
use Switon\Core\App;
use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Attribute\Scene;
use Switon\Core\InjectorInterface;
use Switon\Core\Lazy;
use Switon\Core\Runtime;
use Switon\Core\SceneManagerInterface;
use Switon\Di\Container;
use Switon\Di\ServiceProvider as DiServiceProvider;
use Switon\Kernel\Exception\ConfigLoadException;
use Switon\Kernel\Exception\RootDirectoryNotFoundException;
use Throwable;

use function date_default_timezone_set;
use function dirname;
use function is_array;

/**
 * Base kernel implementation for bootstrapping Switon applications.
 *
 * Use when initializing container services, environment variables, configuration,
 * and fatal error handling before handing control to a concrete runtime entrypoint.
 *
 * Road-signs:
 * - detect root
 * - env + config
 * - apply class Scene after config registration
 * - kernel config may extend `services`
 * - YAML config (optional)
 * - container + App bridge
 * - ServiceBootstrapper
 * - fatal handler
 *
 * @see \Switon\Kernel\KernelInterface
 * @see \Switon\Kernel\KernelInterface::start()
 * @see \Switon\Kernel\ServiceBootstrapper::bootstrap()
 * @see \Switon\Cli\Kernel
 * @see \Switon\Http\Kernel
 * @see \Switon\Kernel\Env
 * @see \Switon\Kernel\Config
 * @see \Switon\Yaml\YamlReaderInterface
 * @see \Switon\Kernel\FatalHandlerInterface
 * @see \Switon\Kernel\FatalHandlerInterface::register()
 * @see \Switon\Kernel\Exception
 */
#[Scene('default')]
class Kernel implements KernelInterface
{
    /**
     * Project root directory path.
     */
    protected string $root;

    /**
     * Dependency injection container.
     */
    protected Container $container;

    /**
     * Kernel-specific service bindings applied after providers and config registration.
     *
     * Guidance: `<current kernel FQCN> => ['services' => [...]]` is a bootstrap-only collector; it is consumed before service registration and is not kept as a container definition.
     *
     * @var array<string, mixed>
     */
    protected array $services = [];

    /**
     * Application configuration and metadata.
     */
    #[Autowired] protected AppInterface|Lazy $app;
    #[Autowired] protected SceneManagerInterface|Lazy $sceneManager;
    #[Autowired] protected ServiceBootstrapper $serviceBootstrapper;
    #[Autowired] protected FatalHandlerInterface|Lazy $fatalHandler;

    /**
     * Initialize kernel with project root directory.
     *
     * @param string|null $root Project root directory, auto-detected if null
     *
     * @throws \Switon\Kernel\Exception\RootDirectoryNotFoundException If root cannot be detected
     */
    public function __construct(?string $root = null)
    {
        $this->root = $root ?? $this->detectRoot();
    }

    /**
     * Starts the kernel and routes bootstrap failures through startup exception handling.
     *
     * Subclasses should override this method to add post-bootstrap logic
     * (e.g., starting an HTTP or CLI server).
     */
    public function start(): void
    {
        try {
            $this->bootstrap();
        } catch (Throwable $e) {
            $this->handleStartupException($e);
        }
    }

    /**
     * Detects the project root directory automatically.
     *
     * Searches for autoload.php in included files and derives root directory from it.
     * Assumes standard Composer structure: root/vendor/autoload.php
     *
     * Detection Process:
     * 1. Gets all currently included PHP files
     * 2. Finds the first file ending with "vendor/autoload.php"
     * 3. Returns the directory two levels up (vendor/../..)
     *
     * @return string Project root directory path
     *
     * @throws \Switon\Kernel\Exception\RootDirectoryNotFoundException If autoload.php is not found in included files
     */
    protected function detectRoot(): string
    {
        $includedFiles = get_included_files();

        foreach ($includedFiles as $file) {
            if (str_ends_with($file, 'vendor/autoload.php')) {
                return dirname($file, 2);
            }
        }

        RootDirectoryNotFoundException::raise(
            'Cannot automatically detect project root directory: autoload.php not found in included files. '
            . 'Please provide $root parameter to Kernel constructor: new Kernel(__DIR__ . \'/..\')'
        );
    }

    /**
     * Bootstraps the application: loads env, creates the container, registers services, and installs fatal handling.
     *
     * This method initializes the core framework components in the correct order:
     * 1. Load environment variables (needed for container defaults)
     * 2. Define constants
     * 3. Create container with default services
     * 4. Load user configuration
     * 5. Bootstrap services (register providers, load config, boot providers)
     * 6. Set timezone and register the fatal shutdown handler
     */
    protected function bootstrap(): void
    {
        $this->loadEnv();

        $this->defineConstants();

        $this->container = new Container([KernelInterface::class => $this]);
        // DI/Core are bootstrap built-ins: Kernel needs them before provider discovery starts.
        (new DiServiceProvider())->register($this->container);

        // Set container in App for make() function
        App::setContainer($this->container);
        // Autowire Kernel properties after container is created
        $this->container->get(InjectorInterface::class)->inject($this);

        // Load configurations, merge current-kernel services into defaults, and fold them back into config.
        $configurations = $this->loadConfig();
        $this->services = ($configurations[static::class]['services'] ?? []) + $this->services;
        unset($configurations[static::class]);
        $configurations = $this->services + $configurations;

        // Bootstrap services. Class-level Scene must see final App config, but still run before provider boot.
        $this->serviceBootstrapper->bootstrap(
            $configurations,
            $this->applyClassScene(...),
            [
                DiServiceProvider::class,
            ],
        );

        date_default_timezone_set($this->app->timezone());

        $this->fatalHandler->register();
    }

    /**
     * Loads environment variables from <code>.env.local</code> and <code>.env</code>.
     *
     * Files are loaded in priority order (first loaded wins):
     * 1. `.env.local` — machine-specific overrides (git-ignored, optional)
     * 2. `.env` — project values (git-ignored)
     *
     * System environment variables always take highest priority.
     * Default values belong in config files via `env('KEY', 'default')`.
     *
     * @see env()
     */
    protected function loadEnv(): void
    {
        $root = $this->root;

        // Load in priority order (first loaded wins, Env never overrides existing vars)
        (new Env("$root/.env.local"))->load();
        (new Env("$root/.env"))->load();
    }

    /**
     * Loads the merged bootstrap configuration array for service registration.
     *
     * Delegates to {@see \Switon\Kernel\Config::load()} to combine optional root YAML (<code>switon.yml</code> or <code>SWITON_CONFIG_FILE</code>) with <code>config/*.php</code>.
     *
     * Road-signs:
     * - Config::load
     * - switon.yml optional
     * - config/*.php optional
     * - registerConfigurations next hop
     *
     * @return array<string, mixed> Configuration array (service ID => definition)
     *
     * @throws \Switon\Kernel\Exception\ConfigLoadException If configuration loading fails
     *
     * @see \Switon\Kernel\Config
     * @see \Switon\Kernel\Config::load()
     * @see \Switon\Kernel\ServiceBootstrapper::bootstrap()
     * @see \Switon\Kernel\ServiceBootstrapper::registerConfigurations()
     * @see \Switon\Di\Container::set()
     */
    protected function loadConfig(): array
    {
        try {
            return (new Config("$this->root/config"))->load($this->root);
        } catch (Throwable $e) {
            ConfigLoadException::raise(
                'Failed to load configuration from {configDir}: {error}',
                ['configDir' => "$this->root/config", 'error' => $e->getMessage()],
                0,
                $e
            );
        }
    }

    /**
     * Defines low-level runtime state used before regular services take over.
     *
     * Sets project root and runtime environment state used by low-level bootstrap services.
     *
     * @see detectCoroutineCanEnabled()
     */
    protected function defineConstants(): void
    {
        Runtime::setRoot($this->root);
        Runtime::setCoroutineEnabled($this->detectCoroutineCanEnabled());
    }

    /**
     * Applies the kernel class-level <code>#[Scene]</code> declaration when present.
     */
    protected function applyClassScene(): void
    {
        $attributes = (new ReflectionClass($this))->getAttributes(Scene::class);
        if ($attributes === []) {
            return;
        }

        /** @var Scene $scene */
        $scene = $attributes[0]->newInstance();
        if ($scene->name !== '') {
            $this->sceneManager->setScene($scene->name);
        }
    }

    /**
     * Detects whether coroutine support should be enabled for the current process.
     *
     * Checks if the environment supports coroutines by verifying:
     * - Running in CLI mode (not web server)
     * - Swoole extension is loaded
     *
     * @return bool True if coroutines can be enabled
     *
     * @see defineConstants()
     */
    protected function detectCoroutineCanEnabled(): bool
    {
        if (PHP_SAPI !== 'cli' || !extension_loaded('swoole')) {
            return false;
        }

        if ($this->isXdebugActive()) {
            return false;
        }

        return true;
    }

    /**
     * Returns whether Xdebug is effectively active for the current process.
     *
     * When <code>XDEBUG_MODE=off</code>, the extension may still be loaded but
     * <code>xdebug_info('mode')</code> returns an empty array.
     */
    protected function isXdebugActive(): bool
    {
        if (!extension_loaded('xdebug') || !function_exists('xdebug_info')) {
            return false;
        }

        /** @var mixed $modes */
        $modes = xdebug_info('mode');
        return is_array($modes) && $modes !== [];
    }

    /**
     * Handle startup exceptions with graceful error reporting.
     *
     * Attempts to log the exception using the configured logger if available,
     * falls back to console logging, and terminates the application.
     *
     * @param Throwable $e The startup exception to handle
     *
     * @see \Psr\Log\LoggerInterface
     * @see console_log()
     */
    protected function handleStartupException(Throwable $e): void
    {
        // Try to log with Logger (if container is initialized and Logger is registered)
        try {
            if (isset($this->container) && $this->container->has(LoggerInterface::class)) {
                $this->container->get(LoggerInterface::class)->error($e);
            }
        } catch (Throwable $loggerError) {
            $this->logToConsole('error', 'Logger failed: ' . $loggerError->getMessage());
        }

        // Always output original exception to console
        $this->logToConsole('error', (string)$e);
        $this->terminateStartupFailure();
    }

    protected function logToConsole(string $level, string $message): void
    {
        console_log($level, $message);
    }

    protected function terminateStartupFailure(): never
    {
        exit(1);
    }
}
