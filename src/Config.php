<?php

declare(strict_types=1);

namespace Switon\Kernel;

use Switon\Kernel\Exception\ConfigLoadException;
use Switon\Yaml\Exception\YamlParseException;
use Switon\Yaml\YamlReader;

use function file_get_contents;
use function getenv;
use function glob;
use function is_array;
use function is_dir;
use function is_file;

/**
 * Loads user configuration during kernel bootstrap.
 *
 * Use when building container definitions from optional project-root YAML and optional <code>config/*.php</code> return arrays.
 *
 * Road-signs:
 * - <code>switon.yml</code> or <code>SWITON_CONFIG_FILE</code>
 * - optional <code>config/*.php</code> merge
 * - recursive YAML + PHP patch
 * - YAML parameters + env interpolation
 * - ConfigLoadException
 *
 * Guidance: Prefer bindings in root <code>switon.yml</code>; use <code>config/*.php</code> for PHP-only values or pre-built instances.
 *
 * @see \Switon\Kernel\Kernel
 * @see \Switon\Kernel\Kernel::loadConfig()
 * @see \Switon\Kernel\Config::loadYamlConfig()
 * @see \Switon\Kernel\Config::resolveYamlConfigPath()
 * @see \Switon\Kernel\ServiceBootstrapper::registerConfigurations()
 * @see \Switon\Kernel\Exception\ConfigLoadException
 * @see \Switon\Yaml\YamlReader
 */
class Config
{
    /**
     * Create a new configuration loader instance for a PHP config directory.
     *
     * @param string $dir The directory path containing configuration files
     */
    public function __construct(protected string $dir)
    {
    }

    /**
     * Load and merge all visible PHP configuration files in the directory.
     *
     * Files starting with <code>.</code> are ignored. Merging uses array union, so
     * the first definition for a duplicated service ID is kept.
     *
     * @return array<string, mixed> Merged configuration definitions
     */
    public function loadFromDirectory(): array
    {
        $configs = [];

        foreach (glob("$this->dir/*.php") ?: [] as $file) {
            // Ignore dotfiles (hidden files) in configuration directory
            if (str_starts_with(basename($file), '.')) {
                continue;
            }

            $config = require $file;
            if (is_array($config)) {
                $configs += $config;
            }
        }

        return $configs;
    }

    /**
     * Loads kernel configuration from root YAML and optional <code>config/*.php</code> patches.
     *
     * Behaviour is defined by <code>spec/config/yaml.md</code>.
     *
     * @param string $root Project root directory
     *
     * @return array<string, mixed>
     */
    public function load(string $root): array
    {
        $phpConfig = [];

        if (is_dir($this->dir)) {
            $phpConfig = $this->loadFromDirectory();
        }

        $yamlConfig = $this->loadYamlConfig($root);

        if ($yamlConfig === []) {
            return $phpConfig;
        }

        if ($phpConfig === []) {
            return $yamlConfig;
        }

        return $this->mergeYamlAndPhpConfig($yamlConfig, $phpConfig);
    }

    /**
     * Read and parse root YAML when {@see \Switon\Kernel\Config::resolveYamlConfigPath()} returns a path.
     *
     * Road-signs:
     * - resolveYamlConfigPath
     * - YamlReader parse
     * - interpolate parameters + env
     * - empty when no YAML file
     *
     * @return array<string, mixed>
     *
     * @see \Switon\Kernel\Config::resolveYamlConfigPath()
     * @see \Switon\Yaml\YamlReader
     */
    protected function loadYamlConfig(string $root): array
    {
        $path = $this->resolveYamlConfigPath($root);
        if ($path === null) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            ConfigLoadException::raise(
                'Failed to read YAML configuration file: {file}',
                ['file' => $path]
            );
        }

        $reader = new YamlReader();

        try {
            /** @var array<string, mixed> $data */
            $data = $reader->parse($contents);
        } catch (YamlParseException $e) {
            ConfigLoadException::raise(
                'Invalid YAML configuration file {file}: {error}',
                ['file' => $path, 'error' => $e->getMessage()],
                0,
                $e
            );
        }

        $variables = $this->buildInterpolationVariables($data);

        try {
            /** @var array<string, mixed> $interpolated */
            $interpolated = $reader->interpolate($data, $variables);
        } catch (YamlParseException $e) {
            ConfigLoadException::raise(
                'Failed to interpolate YAML configuration file {file}: {error}',
                ['file' => $path, 'error' => $e->getMessage()],
                0,
                $e
            );
        }

        return $interpolated;
    }

    /**
     * Resolve project YAML path: <code>SWITON_CONFIG_FILE</code> or existing <code>{root}/switon.yml</code>.
     *
     * Road-signs:
     * - <code>SWITON_CONFIG_FILE</code>
     * - <code>@root/</code> prefix on env path
     * - default <code>switon.yml</code>
     * - null when absent
     *
     * @see \Switon\Kernel\Config::loadYamlConfig()
     */
    protected function resolveYamlConfigPath(string $root): ?string
    {
        $envPath = getenv('SWITON_CONFIG_FILE');
        if (is_string($envPath) && $envPath !== '') {
            if (str_starts_with($envPath, '@root/')) {
                return $root . substr($envPath, strlen('@root'));
            }

            return $envPath;
        }

        $default = $root . '/switon.yml';
        if (is_file($default)) {
            return $default;
        }

        return null;
    }

    /**
     * Builds interpolation variables from process env and YAML <code>parameters</code>.
     *
     * @param array<string, mixed> $yaml
     *
     * @return array<string, string>
     */
    protected function buildInterpolationVariables(array $yaml): array
    {
        $variables = [];

        // Environment variables
        $env = getenv();
        if (is_array($env)) {
            foreach ($env as $name => $value) {
                if (!is_string($value)) {
                    continue;
                }

                $variables[$name] = $value;
            }
        }

        // YAML parameters section
        if (isset($yaml['parameters']) && is_array($yaml['parameters'])) {
            foreach ($yaml['parameters'] as $name => $value) {
                if (!is_string($name)) {
                    continue;
                }

                if (is_scalar($value)) {
                    if (is_bool($value)) {
                        $variables[$name] = $value ? 'true' : 'false';
                        continue;
                    }
                    if ($value === null) {
                        $variables[$name] = '';
                        continue;
                    }

                    $variables[$name] = (string)$value;
                }
            }
        }

        return $variables;
    }

    /**
     * Merges YAML base configuration with PHP overrides according to the kernel protocol.
     *
     * @param array<string, mixed> $yaml
     * @param array<string, mixed> $php
     *
     * @return array<string, mixed>
     */
    protected function mergeYamlAndPhpConfig(array $yaml, array $php): array
    {
        $merged = $yaml;

        foreach ($php as $id => $phpValue) {
            if (!array_key_exists($id, $merged)) {
                $merged[$id] = $phpValue;
                continue;
            }

            $yamlValue = $merged[$id];

            if (!is_array($yamlValue) || !is_array($phpValue)) {
                ConfigLoadException::raise(
                    'Configuration type mismatch for id {id}: both sides must be arrays to merge',
                    ['id' => $id]
                );
            }

            $merged[$id] = $this->mergeConfigArrays($yamlValue, $phpValue, $id);
        }

        return $merged;
    }

    /**
     * Recursively merges one service definition from YAML with its PHP patch.
     *
     * @param array<array-key, mixed> $yaml
     * @param array<array-key, mixed> $php
     *
     * @return array<array-key, mixed>
     */
    protected function mergeConfigArrays(array $yaml, array $php, string $id): array
    {
        $yamlIsAssoc = $this->isAssocArray($yaml);
        $phpIsAssoc = $this->isAssocArray($php);

        if ($yamlIsAssoc !== $phpIsAssoc) {
            ConfigLoadException::raise(
                'Configuration structure mismatch for id {id}',
                ['id' => $id]
            );
        }

        if ($yamlIsAssoc) {
            foreach ($php as $key => $phpValue) {
                if (!array_key_exists($key, $yaml)) {
                    $yaml[$key] = $phpValue;
                    continue;
                }

                $yamlValue = $yaml[$key];

                if (is_array($yamlValue) && is_array($phpValue)) {
                    $yaml[$key] = $this->mergeConfigArrays($yamlValue, $phpValue, $id);
                    continue;
                }

                if (is_array($yamlValue) !== is_array($phpValue)) {
                    ConfigLoadException::raise(
                        'Configuration value type mismatch for id {id}',
                        ['id' => $id]
                    );
                }

                $yaml[$key] = $phpValue;
            }

            return $yaml;
        }

        // Sequence + sequence: PHP overrides YAML (no concatenation)
        return $php;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    protected function isAssocArray(array $array): bool
    {
        $expectedKey = 0;
        foreach ($array as $key => $_) {
            if ($key !== $expectedKey) {
                return true;
            }
            $expectedKey++;
        }

        return false;
    }
}
