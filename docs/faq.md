# Kernel FAQ {#kernel-faq}
Short answers for bootstrap order, configuration, and startup failures.

> **Prerequisites:** [Kernel Package](README.md)

## Getting Started {#getting-started}

### Which kernel should I extend? {#which-kernel}

Extend `Switon\Kernel\Kernel` for a custom runtime entrypoint, or use the runtime-specific kernel that matches your transport: `Switon\Http\Kernel` for HTTP and `Switon\Cli\Kernel` for CLI.

### Why do I call `parent::start()`? {#parent-start}

Because the base kernel owns bootstrap. `parent::start()` loads env, config, providers, and fatal handling before your runtime starts.

### What if root detection fails? {#root-detection}

Pass the project root explicitly to the constructor. Auto-detection depends on `vendor/autoload.php` already being loaded.

## Environment {#environment}

### Which env file loads first? {#env-priority}

`.env.local` loads before `.env`, and system environment variables still win.

### Can I reference a variable defined later in the same file? {#env-forward-reference}

No. `$VAR` and `${VAR}` must already exist in system env or be defined earlier in the same file. Missing references raise `EnvVariableNotFoundException`.

## Configuration {#configuration}

### Which config file is primary? {#primary-config}

`switon.yml` is the base config. `config/*.php` applies as a PHP patch on top of `switon.yml` when you need PHP-only values or pre-built instances.

### How do I choose a different YAML file? {#custom-yaml}

Set `SWITON_CONFIG_FILE` to a non-empty path. `@root/...` is a convenience prefix that resolves against the project root.

### What happens if two files define the same service ID? {#duplicate-service-id}

`switon.yml` is the base; `config/*.php` patches matching array values on top of it. Multiple PHP files keep the first top-level ID. Kernel-specific `services` entries merge before provider boot and win on duplicate top-level IDs.

### Is missing `config/` an error? {#missing-config-dir}

No. Kernel continues with YAML only or an empty config map.

## Errors {#errors}

### Which fatal errors become events? {#fatal-error-types}

Shutdown-stage `E_ERROR`, `E_CORE_ERROR`, `E_COMPILE_ERROR`, `E_PARSE`, and `E_RECOVERABLE_ERROR` can become `Switon\Kernel\Event\FatalErrorOccurred` when an event dispatcher is bound.

`Switon\Kernel\Event\ServiceBootstrapped` fires after provider registration and boot when an event dispatcher is bound.

### Do fatal errors before `start()` count? {#fatal-before-start}

No. Fatal handling starts after bootstrap reaches the shutdown-handler phase.

### How do startup failures surface? {#startup-failures}

Bootstrap fails fast. When logging is available, the kernel uses it; the original failure is also written to `console_log('error', ...)` before the process exits.

### When is coroutine mode enabled? {#coroutine-mode}

Only when the runtime is CLI, the Swoole extension is loaded, and Xdebug is not active.
