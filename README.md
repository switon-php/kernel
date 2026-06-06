# Switon Kernel Package

[![Kernel CI](https://img.shields.io/github/actions/workflow/status/switon-php/kernel/ci.yml?branch=main&label=Kernel%20CI)](https://github.com/switon-php/kernel/actions/workflows/ci.yml) [![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://www.php.net/)

Switon's application bootstrap kernel for env loading, config merging, provider lifecycle, and fatal handling.

## Highlights

- **Single bootstrap entrypoint:** `KernelInterface::start()` starts the application from one contract.
- **Config loading:** root YAML and PHP config files are merged together.
- **Provider discovery and overrides:** built-in and app providers are loaded in one bootstrap flow.
- **Autowiring and boot order:** services are wired before runtime control is handed over.
- **Fatal handling:** the kernel sets up fatal handling during bootstrap.
- **Runtime checks:** the kernel includes a version command for quick verification.

## Installation

```bash
composer require switon/kernel
```

## Quick Start

```php
use Switon\Kernel\Kernel;

class AppKernel extends Kernel
{
    public function start(): void
    {
        parent::start();
        // Attach HTTP server, CLI loop, worker, etc.
    }
}

(new AppKernel(__DIR__))->start();
```

Docs: https://docs.switon.dev/latest/kernel

## License

MIT.
