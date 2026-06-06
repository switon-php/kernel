<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Switon\Core\App;
use Switon\Core\AppInterface;
use Switon\Core\Attribute\Scene;
use Switon\Core\Runtime;
use Switon\Core\SceneManagerInterface;
use Switon\Di\Container;
use Switon\Kernel\Exception\MalformedEnvFileException;
use Switon\Kernel\Kernel;
use Switon\Kernel\Tests\Fixtures\KernelFunctionOverrides;
use Switon\Kernel\Tests\Fixtures\RealDetectionKernel;
use Switon\Kernel\Tests\Fixtures\SceneBootstrapKernel;
use Switon\Kernel\Tests\Fixtures\StartupExceptionTestKernel;
use Switon\Kernel\Tests\Fixtures\StartupTerminationException;
use Switon\Kernel\Tests\Fixtures\TestableKernel;
use RuntimeException;

use function extension_loaded;
use function function_exists;
use function is_array;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;

require_once __DIR__ . '/../Fixtures/KernelFunctionOverrides.php';
require_once __DIR__ . '/../Fixtures/KernelFunctions.php';

class KernelTest extends TestCase
{
    protected string $tempDir;

    protected array $originalEnv = [];

    protected string $originalRuntimeRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalRuntimeRoot = Runtime::getRoot();
        $this->tempDir = sys_get_temp_dir() . '/kernel_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->backupEnvVars(['DB_HOST', 'SWITON_CONFIG_FILE']);
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            $value === false ? putenv($key) : putenv("$key=$value");
        }
        KernelFunctionOverrides::reset();
        Runtime::setRoot($this->originalRuntimeRoot);
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    protected function backupEnvVars(array $vars): void
    {
        foreach ($vars as $var) {
            $this->originalEnv[$var] = getenv($var);
        }
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

    public function testConstructorAcceptsRootParameter(): void
    {
        // Act
        $kernel = new TestableKernel($this->tempDir);

        // Assert
        $this->assertSame($this->tempDir, $kernel->getRoot());
    }

    public function testApplyClassSceneSkipsSetSceneWhenSceneNameIsEmpty(): void
    {
        $sceneManager = $this->createMock(SceneManagerInterface::class);
        $sceneManager->expects($this->never())->method('setScene');

        $kernel = new #[Scene('')] class ($this->tempDir) extends TestableKernel {
        };
        $kernel->setSceneManager($sceneManager);
        $kernel->exposedApplyClassScene();
    }

    public function testApplyClassSceneSkipsWhenSceneAttributeMissing(): void
    {
        $sceneManager = $this->createMock(SceneManagerInterface::class);
        $sceneManager->expects($this->never())->method('setScene');

        $kernel = new TestableKernel($this->tempDir);
        $kernel->setSceneManager($sceneManager);
        $kernel->exposedApplyClassScene();
    }

    public function testConstructorDetectsProjectRootWhenRootNotProvided(): void
    {
        $kernel = new class () extends Kernel {
            public function getRootForTest(): string
            {
                return $this->root;
            }
        };
        $detectedRoot = $kernel->getRootForTest();

        $expectedRoot = null;
        foreach (get_included_files() as $file) {
            if (str_ends_with($file, 'vendor/autoload.php')) {
                $expectedRoot = dirname($file, 2);
                break;
            }
        }

        $this->assertIsString($detectedRoot);
        $this->assertNotEmpty($detectedRoot);
        $this->assertNotNull($expectedRoot);
        $this->assertSame($expectedRoot, $detectedRoot);
    }

    public function testDetectRootThrowsWhenAutoloadFileCannotBeFound(): void
    {
        KernelFunctionOverrides::$includedFiles = [$this->tempDir . '/index.php'];

        $kernel = new class () extends Kernel {
            public function __construct()
            {
            }

            public function exposedDetectRoot(): string
            {
                return $this->detectRoot();
            }
        };

        $this->expectException(\Switon\Kernel\Exception\RootDirectoryNotFoundException::class);
        $this->expectExceptionMessage('autoload.php not found');

        $kernel->exposedDetectRoot();
    }

    public function testLoadConfigReturnsEmptyArrayWhenConfigDirNotExists(): void
    {
        // Arrange - tempDir has no config subdirectory
        $kernel = new TestableKernel($this->tempDir);

        // Act
        $result = $kernel->exposedLoadConfig();

        // Assert
        $this->assertSame([], $result);
    }

    public function testLoadConfigLoadsFilesFromConfigDir(): void
    {
        // Arrange
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0755, true);
        file_put_contents($configDir . '/app.php', '<?php return ["key" => "value"];');

        $kernel = new TestableKernel($this->tempDir);

        // Act
        $result = $kernel->exposedLoadConfig();

        // Assert
        $this->assertSame(['key' => 'value'], $result);
    }

    public function testLoadConfigThrowsWhenYamlConfigIsInvalid(): void
    {
        file_put_contents($this->tempDir . '/switon.yml', "service:\n  class: \"foo\"\n  - bad\n");
        $kernel = new TestableKernel($this->tempDir);

        $this->expectException(\Switon\Kernel\Exception\ConfigLoadException::class);
        $this->expectExceptionMessage('Failed to load configuration');

        $kernel->exposedLoadConfig();
    }

    public function testCurrentKernelServicesOverrideDefaultsAndReplaceConfigEntries(): void
    {
        $kernel = new TestableKernel($this->tempDir);
        $kernel->setServices([
            'default' => 'from-code',
            'overridden' => 'from-code',
        ]);

        $configurations = [
            TestableKernel::class => [
                'services' => [
                    'overridden' => 'from-config',
                    'config-only' => 'from-config',
                ],
                'note' => 'ignored',
            ],
        ];

        $result = $kernel->exposedFoldKernelServices($configurations);

        $this->assertSame(
            [
                'overridden' => 'from-config',
                'config-only' => 'from-config',
                'default' => 'from-code',
            ],
            $kernel->getServices()
        );
        $this->assertSame(
            [
                'overridden' => 'from-config',
                'config-only' => 'from-config',
                'default' => 'from-code',
            ],
            $result
        );
    }

    public function testBootstrapAppliesClassSceneUsingBuiltinCoreProviderRegistration(): void
    {
        $this->writeComposerExtraCache([
            'fixture/app' => [
                'switon' => [
                    'commands' => [],
                ],
            ],
        ]);
        mkdir($this->tempDir . '/config', 0755, true);
        file_put_contents(
            $this->tempDir . '/config/app.php',
            '<?php return ['
            . var_export(AppInterface::class, true)
            . " => ['timezone' => 'Asia/Shanghai']];"
        );

        $kernel = new SceneBootstrapKernel($this->tempDir);

        try {
            $kernel->exposedBootstrap();

            $this->assertSame('fixture-scene', $kernel->getBootstrappedScene());
            $this->assertSame('Asia/Shanghai', $kernel->getBootstrappedTimezone());
        } finally {
            App::setContainer(null);
        }
    }

    public function testDetectCoroutineCanEnabledReturnsFalseByDefault(): void
    {
        // Arrange
        $kernel = new TestableKernel($this->tempDir);

        // Act
        $result = $kernel->exposedDetectCoroutineCanEnabled();

        // Assert - In test environment, this depends on CLI mode and Swoole
        // Our TestableKernel overrides to return false by default
        $this->assertFalse($result);
    }

    public function testDetectCoroutineCanEnabledReturnsConfiguredValue(): void
    {
        // Arrange
        $kernel = new TestableKernel($this->tempDir);
        $kernel->coroutineEnabled = true;

        // Act
        $result = $kernel->exposedDetectCoroutineCanEnabled();

        // Assert
        $this->assertTrue($result);
    }

    public function testDetectCoroutineCanEnabledReturnsFalseWhenSwooleIsMissing(): void
    {
        KernelFunctionOverrides::$extensionLoaded['swoole'] = false;

        $kernel = new RealDetectionKernel($this->tempDir);

        $this->assertFalse($kernel->exposedDetectCoroutineCanEnabled());
    }

    public function testIsXdebugActiveReturnsFalseWhenExtensionIsMissing(): void
    {
        KernelFunctionOverrides::$extensionLoaded['xdebug'] = false;
        KernelFunctionOverrides::$functionExists['xdebug_info'] = false;

        $kernel = new RealDetectionKernel($this->tempDir);

        $this->assertFalse($kernel->exposedIsXdebugActive());
    }

    public function testDetectCoroutineCanEnabledReturnsFalseWhenXdebugIsActive(): void
    {
        KernelFunctionOverrides::$extensionLoaded['swoole'] = true;

        $kernel = new class ($this->tempDir) extends Kernel {
            public function __construct(string $root)
            {
                parent::__construct($root);
            }

            protected function isXdebugActive(): bool
            {
                return true;
            }

            public function exposedDetectCoroutineCanEnabled(): bool
            {
                return $this->detectCoroutineCanEnabled();
            }
        };

        $this->assertFalse($kernel->exposedDetectCoroutineCanEnabled());
    }

    // ========================================================================
    // loadEnv() Tests
    // ========================================================================

    public function testLoadEnvLoadsEnvFile(): void
    {
        // Arrange
        file_put_contents("$this->tempDir/.env", "DB_HOST=localhost");
        $kernel = new TestableKernel($this->tempDir);

        // Act
        $kernel->exposedLoadEnv();

        // Assert
        $this->assertSame('localhost', getenv('DB_HOST'));
    }

    public function testLoadEnvLocalOverridesEnvFile(): void
    {
        // Arrange
        file_put_contents("$this->tempDir/.env", "DB_HOST=localhost");
        file_put_contents("$this->tempDir/.env.local", "DB_HOST=my-machine");
        $kernel = new TestableKernel($this->tempDir);

        // Act
        $kernel->exposedLoadEnv();

        // Assert
        $this->assertSame('my-machine', getenv('DB_HOST'), '.env.local should override .env');
    }

    public function testLoadEnvLoadsEnvLocalWhenBaseEnvMissing(): void
    {
        // Arrange
        file_put_contents("$this->tempDir/.env.local", "DB_HOST=local-only");
        $kernel = new TestableKernel($this->tempDir);

        // Act
        $kernel->exposedLoadEnv();

        // Assert
        $this->assertSame('local-only', getenv('DB_HOST'));
    }

    public function testLoadEnvSystemEnvWinsOverAllFiles(): void
    {
        // Arrange
        putenv('DB_HOST=from-system');
        file_put_contents("$this->tempDir/.env", "DB_HOST=from-env");
        file_put_contents("$this->tempDir/.env.local", "DB_HOST=from-local");
        $kernel = new TestableKernel($this->tempDir);

        // Act
        $kernel->exposedLoadEnv();

        // Assert
        $this->assertSame('from-system', getenv('DB_HOST'), 'System env must never be overridden');
    }

    public function testLoadEnvGracefulWhenNoEnvFile(): void
    {
        // Arrange — no .env file in tempDir
        $kernel = new TestableKernel($this->tempDir);

        // Act
        $kernel->exposedLoadEnv();

        // Assert — no error
        $this->assertFalse(getenv('DB_HOST'));
    }

    public function testLoadEnvThrowsMalformedEnvWhenLocalHasUnterminatedQuote(): void
    {
        file_put_contents(
            "$this->tempDir/.env.local",
            "BROKEN=\"starts" . PHP_EOL . 'still open'
        );
        $kernel = new TestableKernel($this->tempDir);

        $this->expectException(MalformedEnvFileException::class);
        $this->expectExceptionMessage('BROKEN');

        $kernel->exposedLoadEnv();
    }

    public function testLoadEnvThrowsMalformedEnvWhenBaseEnvHasUnterminatedQuote(): void
    {
        file_put_contents(
            "$this->tempDir/.env",
            "DB_HOST=\"starts" . PHP_EOL . 'still open'
        );
        $kernel = new TestableKernel($this->tempDir);

        $this->expectException(MalformedEnvFileException::class);
        $this->expectExceptionMessage('DB_HOST');

        $kernel->exposedLoadEnv();
    }

    public function testIsXdebugActiveMatchesRuntimeMode(): void
    {
        $kernel = new RealDetectionKernel($this->tempDir);

        $expected = false;
        if (extension_loaded('xdebug') && function_exists('xdebug_info')) {
            $modes = xdebug_info('mode');
            $expected = is_array($modes) && $modes !== [];
        }

        $this->assertSame($expected, $kernel->exposedIsXdebugActive());
    }

    public function testRealCoroutineDetectionHonorsXdebugGuard(): void
    {
        $kernel = new RealDetectionKernel($this->tempDir);

        $expected = PHP_SAPI === 'cli'
            && extension_loaded('swoole')
            && !$kernel->exposedIsXdebugActive();

        $this->assertSame($expected, $kernel->exposedDetectCoroutineCanEnabled());
    }

    public function testLogToConsoleUsesConsoleLogHelper(): void
    {
        KernelFunctionOverrides::$consoleLogs = [];

        $kernel = new class ($this->tempDir) extends Kernel {
            public function __construct(string $root)
            {
                parent::__construct($root);
            }

            public function exposedLogToConsole(string $level, string $message): void
            {
                $this->logToConsole($level, $message);
            }
        };

        $kernel->exposedLogToConsole('error', 'kernel failed');

        $this->assertSame(
            [
                [
                    'level' => 'error',
                    'message' => 'kernel failed',
                    'context' => [],
                ],
            ],
            KernelFunctionOverrides::$consoleLogs
        );
    }

    public function testStartReportsBootstrapFailureAndTerminatesGracefully(): void
    {
        $kernel = new StartupExceptionTestKernel($this->tempDir);
        $kernel->setBootstrapException(new RuntimeException('Bootstrap exploded'));

        try {
            $kernel->start();
            $this->fail('Expected startup termination');
        } catch (StartupTerminationException) {
            $this->assertCount(1, $kernel->consoleLogs);
            $this->assertSame('error', $kernel->consoleLogs[0]['level']);
            $this->assertStringContainsString('Bootstrap exploded', $kernel->consoleLogs[0]['message']);
        }
    }

    public function testHandleStartupExceptionLogsLoggerFailureAndOriginalException(): void
    {
        $kernel = new StartupExceptionTestKernel($this->tempDir);
        $container = new Container();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->willThrowException(new RuntimeException('logger down'));

        $container->set(LoggerInterface::class, $logger);
        $kernel->setTestContainer($container);

        try {
            $kernel->exposedHandleStartupException(new RuntimeException('Startup failed'));
            $this->fail('Expected startup termination');
        } catch (StartupTerminationException) {
            $this->assertCount(2, $kernel->consoleLogs);
            $this->assertSame('error', $kernel->consoleLogs[0]['level']);
            $this->assertStringContainsString('Logger failed: logger down', $kernel->consoleLogs[0]['message']);
            $this->assertSame('error', $kernel->consoleLogs[1]['level']);
            $this->assertStringContainsString('Startup failed', $kernel->consoleLogs[1]['message']);
        }
    }

    public function testHandleStartupExceptionLogsOriginalExceptionWhenLoggerUnavailable(): void
    {
        $kernel = new StartupExceptionTestKernel($this->tempDir);

        try {
            $kernel->exposedHandleStartupException(new RuntimeException('No logger configured'));
            $this->fail('Expected startup termination');
        } catch (StartupTerminationException) {
            $this->assertCount(1, $kernel->consoleLogs);
            $this->assertSame('error', $kernel->consoleLogs[0]['level']);
            $this->assertStringContainsString('No logger configured', $kernel->consoleLogs[0]['message']);
        }
    }

    public function testHandleStartupExceptionLogsOriginalExceptionWhenLoggerAvailable(): void
    {
        $kernel = new StartupExceptionTestKernel($this->tempDir);
        $container = new Container();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error');

        $container->set(LoggerInterface::class, $logger);
        $kernel->setTestContainer($container);

        try {
            $kernel->exposedHandleStartupException(new RuntimeException('Logger ready'));
            $this->fail('Expected startup termination');
        } catch (StartupTerminationException) {
            $this->assertCount(1, $kernel->consoleLogs);
            $this->assertStringContainsString('Logger ready', $kernel->consoleLogs[0]['message']);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $packages
     */
    protected function writeComposerExtraCache(array $packages): void
    {
        $cacheDir = $this->tempDir . '/vendor/switon';
        mkdir($cacheDir, 0755, true);
        file_put_contents($cacheDir . '/composer-extra.json', json_encode($packages, JSON_THROW_ON_ERROR));
    }
}
