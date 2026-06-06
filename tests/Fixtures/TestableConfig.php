<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Fixtures;

use Switon\Kernel\Config;

class TestableConfig extends Config
{
    public function loadYamlConfigPublic(string $root): array
    {
        return $this->loadYamlConfig($root);
    }

    public function resolveYamlConfigPathPublic(string $root): ?string
    {
        return $this->resolveYamlConfigPath($root);
    }

    public function buildInterpolationVariablesPublic(array $yaml): array
    {
        return $this->buildInterpolationVariables($yaml);
    }

    public function mergeYamlAndPhpConfigPublic(array $yaml, array $php): array
    {
        return $this->mergeYamlAndPhpConfig($yaml, $php);
    }

    public function mergeConfigArraysPublic(array $yaml, array $php, string $id): array
    {
        return $this->mergeConfigArrays($yaml, $php, $id);
    }

    public function isAssocArrayPublic(array $array): bool
    {
        return $this->isAssocArray($array);
    }
}
