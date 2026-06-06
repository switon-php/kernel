<?php

declare(strict_types=1);

namespace Switon\Kernel;

use Switon\Kernel\Exception\EnvVariableNotFoundException;
use Switon\Kernel\Exception\MalformedEnvFileException;

use function count;
use function file;
use function getenv;
use function is_file;
use function ltrim;
use function preg_match_all;
use function putenv;
use function str_contains;
use function str_ends_with;
use function strpos;
use function strtr;
use function substr;
use function trim;

use const FILE_IGNORE_NEW_LINES;
use const PREG_SET_ORDER;

/**
 * Loads environment variables from a single <code>.env</code> file.
 *
 * Supports quoted values, multiline values, and variable substitution
 * via <code>$VAR</code> and <code>${VAR}</code>. Existing system variables are not overridden.
 *
 * Use when bootstrapping environment-dependent configuration before the container
 * is fully initialized.
 *
 * Road-signs:
 * - loads once (<code>$loaded</code>)
 * - no override system env
 * - supports quotes + multiline
 * - substitute <code>$VAR</code>/<code>${VAR}</code>
 * - fail EnvVariableNotFoundException
 *
 * @see \Switon\Kernel\Kernel
 * @see \Switon\Kernel\KernelInterface::start()
 * @see \Switon\Kernel\Exception
 * @see \Switon\Kernel\Exception\EnvVariableNotFoundException
 * @see \Switon\Kernel\Exception\MalformedEnvFileException
 * @see \Switon\Kernel\Env::load()
 */
class Env
{
    protected bool $loaded = false;

    /**
     * @param string $file Environment file to read lazily on first access.
     */
    public function __construct(protected string $file)
    {
    }

    /**
     * Loads the file once and exports variables that are not already present in the process environment.
     */
    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        if (!is_file($this->file)) {
            return;
        }

        $lines = file($this->file, FILE_IGNORE_NEW_LINES);
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = trim($lines[$i]);

            // Skip empty lines and comments
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            // Parse KEY=VALUE
            if (($pos = strpos($line, '=')) === false) {
                continue;
            }

            $name = trim(substr($line, 0, $pos));
            $value = substr($line, $pos + 1);

            // Parse value with quote handling
            [$value, $i] = $this->parseValue($name, $value, $lines, $i, $count);

            // Existing env vars are never overridden (system env always wins)
            if (getenv($name) !== false) {
                continue;
            }

            putenv("$name=$value");
        }
    }

    /**
     * Parses one <code>KEY=VALUE</code> entry, including quoted and multiline forms.
     *
     * @param list<string> $lines
     *
     * @return array{0: string, 1: int}
     */
    protected function parseValue(string $name, string $value, array $lines, int $index, int $count): array
    {
        $value = ltrim($value);

        if ($value === '') {
            return ['', $index];
        }

        $quote = $value[0];

        // Single quote - no variable substitution
        if ($quote === "'") {
            [$parsed, $newIndex] = $this->parseQuotedValue($name, $value, $lines, $index, $count, "'");
            return [$parsed, $newIndex];
        }

        // Double quote - with variable substitution
        if ($quote === '"') {
            [$parsed, $newIndex] = $this->parseQuotedValue($name, $value, $lines, $index, $count, '"');
            return [$this->substituteVariables($parsed), $newIndex];
        }

        // No quotes - trim and support variable substitution
        $value = trim($value);
        return [$this->substituteVariables($value), $index];
    }

    /**
     * Parses a quoted env value and advances the cursor when it spans multiple lines.
     *
     * @param list<string> $lines
     *
     * @return array{0: string, 1: int}
     */
    protected function parseQuotedValue(string $name, string $value, array $lines, int $index, int $count, string $quote): array
    {
        $startLine = $index + 1;

        // Remove opening quote
        $value = substr($value, 1);

        // Check if closing quote exists
        if (str_ends_with($value, $quote)) {
            $value = substr($value, 0, -1);
        } else {
            // Multi-line value
            $value .= PHP_EOL;
            for ($index++; $index < $count; $index++) {
                $line = $lines[$index];
                if (str_ends_with($line, $quote)) {
                    $value .= substr($line, 0, -1);
                    break;
                } else {
                    $value .= $line . PHP_EOL;
                }
            }

            if ($index === $count) {
                MalformedEnvFileException::raise(
                    'Malformed .env file "{file}": missing closing {quote} quote for "{name}" starting at line {line}',
                    ['file' => $this->file, 'quote' => $quote, 'name' => $name, 'line' => $startLine]
                );
            }
        }

        return [$value, $index];
    }

    /**
     * Expands <code>$VAR</code> and <code>${VAR}</code> placeholders using the current process environment.
     */
    protected function substituteVariables(string $value): string
    {
        if (!str_contains($value, '$')) {
            return $value;
        }

        $replaces = [];

        // Match ${VAR} or $VAR
        if (preg_match_all('#\$\{(\w+)\}|\$(\w+)#', $value, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $varName = ($match[2] ?? '') !== '' ? $match[2] : ($match[1] ?? '');
                $varValue = getenv($varName);

                if ($varValue === false) {
                    EnvVariableNotFoundException::raise('Env variable not found: {var}', ['var' => $varName]);
                }

                $replaces[$match[0]] = $varValue;
            }
        }

        return strtr($value, $replaces);
    }

    /**
     * Returns one environment value after ensuring the file has been loaded.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->load();

        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}
