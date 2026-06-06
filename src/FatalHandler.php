<?php

declare(strict_types=1);

namespace Switon\Kernel;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Lazy;
use Switon\Kernel\Event\FatalErrorOccurred;
use Throwable;

use function in_array;
use function register_shutdown_function;

/**
 * Default fatal error handler for kernel shutdown.
 *
 * Use when reporting PHP fatal errors as kernel events during shutdown.
 *
 * @see \Switon\Kernel\FatalHandlerInterface
 * @see \Switon\Kernel\FatalHandlerInterface::register()
 * @see \Switon\Kernel\Event\FatalErrorOccurred
 */
class FatalHandler implements FatalHandlerInterface
{
    #[Autowired] protected EventDispatcherInterface|Lazy $eventDispatcher;

    /**
     * Fatal error types that should be handled.
     */
    protected const array FATAL_ERRORS = [
        E_ERROR,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_PARSE,
        E_RECOVERABLE_ERROR,
    ];

    /**
     * Error type constant to string name mapping.
     */
    protected const array ERROR_TYPE_NAMES = [
        E_ERROR => 'E_ERROR',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_PARSE => 'E_PARSE',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
    ];

    /**
     * Registers the shutdown callback that inspects the last PHP error state.
     */
    public function register(): void
    {
        $this->registerShutdownFunction(function (): void {
            $this->handleLastError($this->getLastError());
        });
    }

    /**
     * Registers the low-level shutdown function used for fatal error capture.
     */
    protected function registerShutdownFunction(callable $handler): void
    {
        register_shutdown_function($handler);
    }

    /**
     * Returns the final PHP error recorded for the current request or process.
     *
     * @return array{type?: int, message?: string, file?: string, line?: int}|null
     */
    protected function getLastError(): ?array
    {
        return error_get_last();
    }

    /**
     * Dispatches a fatal error event when the captured last error matches a fatal type.
     *
     * @param array{type?: int, message?: string, file?: string, line?: int}|null $error
     */
    protected function handleLastError(?array $error): void
    {
        if (!$this->isFatalError($error)) {
            return;
        }

        try {
            /** @var array{type: int, message?: string, file?: string, line?: int} $error */
            $type = $error['type'];
            $this->eventDispatcher->dispatch(new FatalErrorOccurred(
                $error['message'] ?? 'Unknown fatal error',
                $error['file'] ?? 'unknown',
                $error['line'] ?? 0,
                self::ERROR_TYPE_NAMES[$type] ?? 'UNKNOWN',
            ));
        } catch (Throwable) {
            // Silently ignore if event dispatch fails (e.g., container not fully initialized)
        }
    }

    /**
     * Returns whether the captured last error is one of the handled fatal PHP error types.
     *
     * @param array{type?: int, message?: string, file?: string, line?: int}|null $error
     */
    protected function isFatalError(?array $error): bool
    {
        return isset($error['type']) && in_array($error['type'], self::FATAL_ERRORS, true);
    }
}
