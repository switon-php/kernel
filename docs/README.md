# Kernel Package {#kernel-package}
`switon/kernel` gives Switon apps one bootstrap contract for env loading, configuration loading, provider bootstrap, and fatal handling. Choose it when an application needs a single, predictable startup boundary; the kernel does not run the HTTP, CLI, or worker loop itself.

**Key features:**

- **One start contract:** `KernelInterface::start()` runs bootstrap before runtime control returns.
- **Env before config:** `.env.local` and `.env` load before YAML interpolation and `config/*.php`.
- **Config merge surface:** root `switon.yml` (or `SWITON_CONFIG_FILE`) plus optional `config/*.php` patch.
- **Provider bootstrap:** providers are discovered from Composer extra metadata, registered, autowired, and booted in one sequence.
- **Fatal shutdown events:** fatal errors can become `Switon\Kernel\Event\FatalErrorOccurred`.

## Installation {#installation}

**Requirements:** PHP 8.3+, `switon/core`, `switon/di`, `switon/event`, `switon/yaml`

Provider discovery also needs `switon/composer-extra`, or an explicit `Switon\ComposerExtra\ComposerExtraInterface` binding.

```bash
composer require switon/kernel
```

## Quick Start {#quick-start}

Extend `Switon\Kernel\Kernel`, call `parent::start()`, and then hand control to your runtime.

```php
use Switon\Kernel\Kernel;

class AppKernel extends Kernel
{
    public function start(): void
    {
        parent::start();

        // Start HTTP, CLI, worker, or custom runtime here.
    }
}

(new AppKernel(__DIR__))->start();
```

`parent::start()` loads env, creates the container, loads config, boots providers, applies timezone, and registers fatal handling.

If root detection cannot find `vendor/autoload.php`, pass the project root explicitly to the constructor.

For HTTP and CLI apps, extend the runtime-specific kernel (`Switon\Http\Kernel` or `Switon\Cli\Kernel`); those subclasses call `parent::start()` and then enter their own runtime loop.

## Core Concepts {#core-concepts}

### Start Contract {#start-contract}

`KernelInterface::start()` is the public bootstrap boundary. The base kernel handles initialization in a fixed order; your subclass owns whatever runs after `parent::start()`.

### Env Loading {#env-loading}

`Switon\Kernel\Env` loads two files in priority order:

- `.env.local` overrides `.env` when both define the same key
- system environment variables override both

Variable interpolation is strict: `$VAR` and `${VAR}` references must already exist in system env or in an earlier-loaded file. Missing references raise `EnvVariableNotFoundException`.

### Configuration Loading {#configuration-loading}

`Switon\Kernel\Config` loads the root YAML file and visible `config/*.php` files, then merges them into one service-definition map.

- `switon.yml` is the base config; `SWITON_CONFIG_FILE` can point to another YAML file (`@root/...` is a convenience prefix)
- `config/*.php` applies as a PHP patch on top of YAML for PHP-only values or pre-built instances
- both YAML and `config/` are optional; missing means an empty contribution
- assoc arrays merge recursively; sequences are replaced as a whole

### Service Providers {#service-providers}

Providers run in one bootstrap flow: discover from `composer.json` extra (`switon.providers`), register, register user configurations, autowire, boot. After boot, `Switon\Kernel\Event\ServiceBootstrapped` is dispatched when an event dispatcher is bound.

A kernel-specific `<YourKernel>::class => ['services' => [...]]` entry merges into config before provider boot, and wins on duplicate top-level service IDs.

A class-level `#[Scene]` on the kernel applies after config registration and before provider boot.

### Fatal Handling {#fatal-handling}

After bootstrap, the kernel registers a fatal handler. When an event dispatcher is available, fatal shutdown errors can be dispatched as `Switon\Kernel\Event\FatalErrorOccurred`.

Coroutine mode is enabled only for CLI runtimes with the Swoole extension loaded and Xdebug not active.

## Configuration {#configuration}

Keep bootstrap config in one root YAML file and use `config/*.php` only when PHP is the better fit.

```yaml
# switon.yml
Switon\Core\AppInterface:
  id: myapp
  name: My Application
  env: ${APP_ENV:-prod}
  debug: false
  timezone: ${APP_TIMEZONE:-UTC}

Switon\Kernel\FatalHandlerInterface:
  class: Switon\Kernel\FatalHandler
```

## Keep in Mind {#keep-in-mind}

- The kernel coordinates startup; it does not run the HTTP, CLI, or worker loop by itself.
- Root auto-detection only works when `vendor/autoload.php` is already included.
- `config/*.php` is optional and dotfiles are skipped.
- Fatal handling starts after bootstrap, so failures before `start()` reaches that step are outside the handler.
- Kernel-specific `services` entries merge before provider boot and override duplicate top-level IDs.

## Next {#next}

- **To read short answers:** [FAQ](faq.md#kernel-faq)
- **To understand the trade-offs:** [Design Rationale](design.md#design-rationale)
