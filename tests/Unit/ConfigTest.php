<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Kernel\Config;
use Switon\Kernel\Exception\ConfigLoadException;

use function file_put_contents;
use function getenv;
use function is_dir;
use function mkdir;
use function putenv;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function unlink;

class ConfigTest extends TestCase
{
    protected string $tempDir;
    protected string|false $originalSwitonConfigFile;
    protected string|false $originalAppEnv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/kernel_config_test_' . uniqid();
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

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    protected function createConfigFile(string $filename, array $config): string
    {
        $file = $this->tempDir . '/' . $filename;
        file_put_contents($file, '<?php return ' . var_export($config, true) . ';');
        return $file;
    }

    public function testLoadReturnsEmptyArrayWhenDirectoryEmpty(): void
    {
        // Arrange
        $config = new Config($this->tempDir);

        // Act
        $result = $config->loadFromDirectory();

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testLoadReturnsConfigFromSingleFile(): void
    {
        // Arrange
        $this->createConfigFile('app.php', ['key' => 'value']);
        $config = new Config($this->tempDir);

        // Act
        $result = $config->loadFromDirectory();

        // Assert
        $this->assertSame(['key' => 'value'], $result);
    }

    public function testLoadReturnsMergedConfigFromMultipleFiles(): void
    {
        // Arrange
        $this->createConfigFile('app.php', ['key1' => 'value1']);
        $this->createConfigFile('db.php', ['key2' => 'value2']);
        $config = new Config($this->tempDir);

        // Act
        $result = $config->loadFromDirectory();

        // Assert
        $this->assertSame('value1', $result['key1']);
        $this->assertSame('value2', $result['key2']);
    }

    public function testLoadIgnoresDotfiles(): void
    {
        // Arrange
        $this->createConfigFile('.hidden.php', ['key' => 'value']);
        $this->createConfigFile('app.php', ['key2' => 'value2']);
        $config = new Config($this->tempDir);

        // Act
        $result = $config->loadFromDirectory();

        // Assert
        $this->assertArrayNotHasKey('key', $result);
        $this->assertSame('value2', $result['key2']);
    }

    public function testLoadIgnoresNonArrayReturns(): void
    {
        // Arrange
        file_put_contents($this->tempDir . '/invalid.php', '<?php return "string";');
        $this->createConfigFile('app.php', ['key' => 'value']);
        $config = new Config($this->tempDir);

        // Act
        $result = $config->loadFromDirectory();

        // Assert
        $this->assertSame(['key' => 'value'], $result);
    }

    public function testLoadWithComplexConfigStructure(): void
    {
        // Arrange
        $complexConfig = [
            'Switon\Service' => [
                'name' => 'Test Service',
                'options' => [
                    'option1' => 'value1',
                    'option2' => 'value2',
                ],
            ],
        ];
        $this->createConfigFile('service.php', $complexConfig);
        $config = new Config($this->tempDir);

        // Act
        $result = $config->loadFromDirectory();

        // Assert
        $this->assertSame($complexConfig, $result);
    }

    public function testLoadWithOverlappingKeys(): void
    {
        // Arrange
        $this->createConfigFile('app.php', ['key' => 'value1']);
        $this->createConfigFile('override.php', ['key' => 'value2']);
        $config = new Config($this->tempDir);

        // Act
        $result = $config->loadFromDirectory();

        // Assert - Array union (+) preserves first value
        $this->assertSame('value1', $result['key']);
    }

    public function testLoadReturnsYamlConfigWhenConfigDirectoryMissing(): void
    {
        // Arrange
        file_put_contents(
            $this->tempDir . '/switon.yml',
            "demo.service:\n  class: App\\\\Demo\n  enabled: true\n"
        );
        $config = new Config($this->tempDir . '/missing-config-dir');

        // Act
        $result = $config->load($this->tempDir);

        // Assert
        $this->assertIsArray($result['demo.service'] ?? null);
        $this->assertSame('App\\\\Demo', $result['demo.service']['class'] ?? null);
        $this->assertTrue($result['demo.service']['enabled'] ?? false);
    }

    public function testLoadMergesYamlAndPhpConfigRecursively(): void
    {
        // Arrange
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0755, true);
        file_put_contents(
            $this->tempDir . '/switon.yml',
            "service.demo:\n  class: App\\\\Demo\n  options:\n    host: yaml-host\n    ports: [3306, 3307]\n"
        );
        file_put_contents(
            $configDir . '/service.php',
            '<?php return ["service.demo" => ["options" => ["host" => "php-host"], "enabled" => true]];'
        );

        $config = new Config($configDir);

        // Act
        $result = $config->load($this->tempDir);

        // Assert
        $this->assertSame('App\\\\Demo', $result['service.demo']['class'] ?? null);
        $this->assertSame('php-host', $result['service.demo']['options']['host'] ?? null);
        $this->assertSame([3306, 3307], $result['service.demo']['options']['ports'] ?? null);
        $this->assertTrue($result['service.demo']['enabled'] ?? false);
    }

    public function testLoadThrowsWhenYamlAndPhpTypesMismatch(): void
    {
        // Arrange
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0755, true);
        file_put_contents($this->tempDir . '/switon.yml', "service.demo:\n  class: App\\\\Demo\n");
        file_put_contents($configDir . '/service.php', '<?php return ["service.demo" => "not-an-array"];');

        $config = new Config($configDir);

        // Assert
        $this->expectException(ConfigLoadException::class);
        $this->expectExceptionMessage('Configuration type mismatch');

        // Act
        $config->load($this->tempDir);
    }

    public function testLoadInterpolatesYamlVariablesFromEnvironmentAndParameters(): void
    {
        // Arrange
        putenv('APP_ENV=testing');
        file_put_contents(
            $this->tempDir . '/switon.yml',
            "parameters:\n  app_name: demo-app\nservice.demo:\n  name: \"\${app_name}\"\n  env: \"\${APP_ENV}\"\n"
        );
        $config = new Config($this->tempDir . '/missing-config-dir');

        // Act
        $result = $config->load($this->tempDir);

        // Assert
        $this->assertSame('demo-app', $result['service.demo']['name'] ?? null);
        $this->assertSame('testing', $result['service.demo']['env'] ?? null);
    }

    public function testLoadThrowsWhenNestedArrayStructureMismatch(): void
    {
        // Arrange
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0755, true);
        file_put_contents($this->tempDir . '/switon.yml', "service.demo:\n  tags: [a, b]\n");
        file_put_contents(
            $configDir . '/service.php',
            '<?php return ["service.demo" => ["tags" => ["primary" => true]]];'
        );
        $config = new Config($configDir);

        // Assert
        $this->expectException(ConfigLoadException::class);
        $this->expectExceptionMessage('structure mismatch');

        // Act
        $config->load($this->tempDir);
    }

    public function testLoadResolvesYamlPathFromRootAliasEnvVariable(): void
    {
        // Arrange
        putenv('SWITON_CONFIG_FILE=@root/custom/switon.custom.yml');
        mkdir($this->tempDir . '/custom', 0755, true);
        file_put_contents(
            $this->tempDir . '/custom/switon.custom.yml',
            "custom.service:\n  class: App\\\\Custom\n"
        );
        $config = new Config($this->tempDir . '/missing-config-dir');

        // Act
        $result = $config->load($this->tempDir);

        // Assert
        $this->assertSame('App\\\\Custom', $result['custom.service']['class'] ?? null);
    }

    public function testLoadThrowsWhenYamlSyntaxIsInvalid(): void
    {
        // Arrange
        file_put_contents($this->tempDir . '/switon.yml', "service:\n  class: \"foo\"\n  - bad\n");
        $config = new Config($this->tempDir . '/missing-config-dir');

        // Assert
        $this->expectException(ConfigLoadException::class);
        $this->expectExceptionMessage('Invalid YAML configuration file');

        // Act
        $config->load($this->tempDir);
    }

    public function testLoadThrowsWhenYamlScalarRootIsRejectedByParser(): void
    {
        // Arrange
        file_put_contents($this->tempDir . '/switon.yml', "'just-a-string'");
        $config = new Config($this->tempDir . '/missing-config-dir');

        // Assert
        $this->expectException(ConfigLoadException::class);
        $this->expectExceptionMessage('Invalid YAML configuration file');

        // Act
        $config->load($this->tempDir);
    }

    public function testLoadThrowsWhenYamlInterpolationReferencesMissingPlaceholder(): void
    {
        file_put_contents(
            $this->tempDir . '/switon.yml',
            "service.demo:\n  class: \"\${SWITON_KERNEL_TEST_CONFIG_MISSING_PLACEHOLDER_9a7f2e}\"\n",
        );
        $config = new Config($this->tempDir . '/missing-config-dir');

        $this->expectException(ConfigLoadException::class);
        $this->expectExceptionMessage('Failed to interpolate YAML configuration file');

        $config->load($this->tempDir);
    }

    public function testLoadThrowsWhenSwConfigFilePointsToMissingAbsolutePath(): void
    {
        $missing = $this->tempDir . '/does-not-exist/switon.yml';
        putenv('SWITON_CONFIG_FILE=' . $missing);
        $config = new Config($this->tempDir . '/missing-config-dir');

        $prevHandler = set_error_handler(static function (int $errno, string $errstr): bool {
            if ($errno === E_WARNING && str_contains($errstr, 'file_get_contents')) {
                return true;
            }

            return false;
        });

        try {
            $this->expectException(ConfigLoadException::class);
            $this->expectExceptionMessage('Failed to read YAML configuration file');
            $config->load($this->tempDir);
        } finally {
            restore_error_handler();
        }
    }

    public function testLoadFallsBackToDefaultSwitonYmlWhenSwConfigFileEnvIsEmptyString(): void
    {
        putenv('SWITON_CONFIG_FILE=');
        file_put_contents(
            $this->tempDir . '/switon.yml',
            "demo.service:\n  class: App\\\\Demo\n"
        );
        $config = new Config($this->tempDir . '/missing-config-dir');

        $result = $config->load($this->tempDir);

        $this->assertSame('App\\\\Demo', $result['demo.service']['class'] ?? null);
    }

    public function testLoadThrowsWhenYamlFileIsUnreadable(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('chmod-based unreadable file is not portable on Windows');
        }

        $yaml = $this->tempDir . '/locked.yml';
        file_put_contents($yaml, "svc:\n  class: App\\\\Svc\n");
        chmod($yaml, 0000);
        putenv('SWITON_CONFIG_FILE=' . $yaml);

        $config = new Config($this->tempDir . '/missing-config-dir');

        $prevHandler = set_error_handler(static function (int $errno, string $errstr): bool {
            if ($errno === E_WARNING && str_contains($errstr, 'file_get_contents')) {
                return true;
            }

            return false;
        });

        try {
            $this->expectException(ConfigLoadException::class);
            $this->expectExceptionMessage('Failed to read YAML configuration file');
            $this->expectExceptionMessage('locked.yml');
            $config->load($this->tempDir);
        } finally {
            restore_error_handler();
            chmod($yaml, 0644);
        }
    }
}
