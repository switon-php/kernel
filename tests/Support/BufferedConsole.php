<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Support;

use Stringable;
use Switon\Core\ConsoleInterface;

final class BufferedConsole implements ConsoleInterface
{
    /** @var list<string> */
    private array $output = [];

    /** @return list<string> */
    public function getOutput(): array
    {
        return $this->output;
    }

    public function clearOutput(): void
    {
        $this->output = [];
    }

    public function isSupportColor(): bool
    {
        return false;
    }

    public function colorize(string $text, int $options = 0, int $width = 0): string
    {
        return $text;
    }

    public function sampleColorizer(): void
    {
    }

    public function write(string|Stringable $message, array $context = [], int $options = 0): void
    {
    }

    public function writeLn(string|Stringable $message = '', array $context = [], int $options = 0): void
    {
        $this->output[] = (string)$message;
    }

    public function debug(string|Stringable $message = '', array $context = [], int $options = 0): void
    {
        $this->writeLn($message, $context, $options);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->writeLn($message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->writeLn($message, $context);
    }

    public function success(string|Stringable $message, array $context = []): void
    {
        $this->writeLn($message, $context);
    }

    public function error(string|Stringable $message, array $context = [], int $code = 1): int
    {
        $this->writeLn($message, $context);

        return $code;
    }

    public function progress(string|Stringable $message, mixed $value = null): void
    {
        $this->writeLn($message);
    }

    public function read(): string
    {
        return '';
    }

    public function ask(string $message): string
    {
        $this->writeLn($message);

        return '';
    }

    public function confirm(string $message, bool $default = true): bool
    {
        $this->writeLn($message);

        return $default;
    }

    public function choice(string $message, array $options, string|int|null $default = null): string|int
    {
        $this->writeLn($message);

        if ($default !== null) {
            return $default;
        }

        return array_key_first($options) ?? 0;
    }

    public function secret(string $message): string
    {
        $this->writeLn($message);

        return '';
    }

    public function block(string|array $messages, ?string $type = null, ?string $prefix = null, bool $padding = true): void
    {
        foreach ((array)$messages as $message) {
            $this->writeLn(($prefix ?? '') . ' ' . $message);
        }
    }

    public function section(string $message): void
    {
        $this->writeLn($message);
    }

    public function note(string $message): void
    {
        $this->writeLn($message);
    }

    public function caution(string $message): void
    {
        $this->writeLn($message);
    }

    public function listing(array $items): void
    {
        foreach ($items as $item) {
            $this->writeLn(' - ' . $item);
        }
    }

    public function table(array $headers, array $rows, int $minWidth = 8, bool $withRowNumber = true): void
    {
    }

    public function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->writeLn();
        }
    }

    public function line(string $message = ''): void
    {
        $this->writeLn($message);
    }
}
