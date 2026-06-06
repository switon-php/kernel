<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Kernel\Exception\ConfigLoadException;
use Switon\Kernel\Tests\Fixtures\TestableConfig;

use function file_put_contents;
use function getenv;
use function is_dir;
use function mkdir;
use function putenv;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function unlink;

class ConfigCoverageTest extends TestCase
{
    protected string $tempDir;
    protected string|false $originalSwitonConfigFile;
    protected string|false $originalAppEnv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/kernel_config_coverage_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->originalSwitonConfigFile = getenv('SWITON_CONFIG_FILE');
        $this->originalAppEnv = getenv('APP_ENV');
    }

    protected function tearDown(): void
    {
        if ($this->originalSwitonConfigFile === false) {
            putenv('SWITON_CONFIG_FILE');
        } else {
            putenv('SWITON_CONFIG_FILE=' . $this->originalSwitonConfigFile);
        }

        if ($this->originalAppEnv === false) {
            putenv('APP_ENV');
        } else {
            putenv('APP_ENV=' . $this->originalAppEnv);
        }

        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    public function testBuildInterpolationVariablesIncludesEnvironmentAndBooleanParameters(): void
    {
        putenv('APP_ENV=testing');
        $config = new TestableConfig($this->tempDir);

        $result = $config->buildInterpolationVariablesPublic([
            'parameters' => [
                'flag_true' => true,
                'flag_false' => false,
                'number' => 12,
            ],
        ]);

        $this->assertSame('testing', $result['APP_ENV']);
        $this->assertSame('true', $result['flag_true']);
        $this->assertSame('false', $result['flag_false']);
        $this->assertSame('12', $result['number']);
        $this->assertArrayNotHasKey('flag_null', $result);
    }

    public function testResolveYamlConfigPathUsesEnvironmentOverrideAndDefaultFallback(): void
    {
        $config = new TestableConfig($this->tempDir);

        putenv('SWITON_CONFIG_FILE=@root/custom.yml');
        $this->assertSame($this->tempDir . '/custom.yml', $config->resolveYamlConfigPathPublic($this->tempDir));

        putenv('SWITON_CONFIG_FILE=');
        file_put_contents($this->tempDir . '/switon.yml', 'root: true');
        $this->assertSame($this->tempDir . '/switon.yml', $config->resolveYamlConfigPathPublic($this->tempDir));
    }

    public function testMergeYamlAndPhpConfigOverridesSequencesAndMergesStructures(): void
    {
        $config = new TestableConfig($this->tempDir);

        $result = $config->mergeYamlAndPhpConfigPublic(
            ['items' => [1, 2], 'service' => ['name' => 'yaml', 'options' => ['a' => 1]]],
            ['items' => [3], 'service' => ['options' => ['b' => 2]]]
        );

        $this->assertSame([3], $result['items']);
        $this->assertSame('yaml', $result['service']['name']);
        $this->assertSame(['a' => 1, 'b' => 2], $result['service']['options']);
    }

    public function testMergeYamlAndPhpConfigPreservesYAMLOnlyEntries(): void
    {
        $config = new TestableConfig($this->tempDir);

        $result = $config->mergeYamlAndPhpConfigPublic(
            ['yaml-only' => ['enabled' => true]],
            ['php-only' => ['enabled' => false]]
        );

        $this->assertSame(['enabled' => true], $result['yaml-only']);
        $this->assertSame(['enabled' => false], $result['php-only']);
    }

    public function testLoadFromDirectorySkipsHiddenFilesAndNonArrayReturns(): void
    {
        $configFile = $this->tempDir . '/visible.php';
        file_put_contents($configFile, '<?php return ["visible" => true];');
        file_put_contents($this->tempDir . '/.hidden.php', '<?php return ["hidden" => true];');
        file_put_contents($this->tempDir . '/string.php', '<?php return "skip";');

        $config = new TestableConfig($this->tempDir);

        $result = $config->loadFromDirectory();

        $this->assertSame(['visible' => true], $result);
    }

    public function testMergeYamlAndPhpConfigThrowsForTypeMismatch(): void
    {
        $config = new TestableConfig($this->tempDir);

        $this->expectException(ConfigLoadException::class);
        $this->expectExceptionMessage('type mismatch');

        $config->mergeYamlAndPhpConfigPublic(['service' => ['name' => 'yaml']], ['service' => 'php']);
    }

    public function testMergeConfigArraysThrowsForStructureMismatch(): void
    {
        $config = new TestableConfig($this->tempDir);

        $this->expectException(ConfigLoadException::class);
        $this->expectExceptionMessage('structure mismatch');

        $config->mergeConfigArraysPublic(['a', 'b'], ['first' => true], 'demo');
    }

    public function testMergeConfigArraysThrowsForNestedValueTypeMismatch(): void
    {
        $config = new TestableConfig($this->tempDir);

        $this->expectException(ConfigLoadException::class);
        $this->expectExceptionMessage('value type mismatch');

        $config->mergeConfigArraysPublic(
            ['svc' => ['options' => ['host' => 'yaml']]],
            ['svc' => ['options' => 'php']],
            'svc',
        );
    }
}
