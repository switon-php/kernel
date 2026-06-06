<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Switon\Kernel\Version;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function unlink;

#[CoversClass(Version::class)]
class VersionTest extends TestCase
{
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/kernel_version_test_' . uniqid('', true);
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testVersionReadsExtraSwitonVersion(): void
    {
        $file = $this->tempDir . '/composer.json';
        file_put_contents(
            $file,
            <<<'JSON'
{
    "extra": {
        "switon": {
            "version": "3.4.2"
        }
    }
}
JSON
        );

        $version = new Version($file);

        $this->assertSame('3.4.2', $version->version());
    }

    public function testVersionFallsBackWhenFieldMissing(): void
    {
        $file = $this->tempDir . '/composer.json';
        file_put_contents($file, '{"extra":{"switon":[]}}');

        $version = new Version($file);

        $this->assertSame('0.0.0', $version->version());
    }

    public function testVersionFallsBackWhenExtraIsNotAnArray(): void
    {
        $file = $this->tempDir . '/composer.json';
        file_put_contents($file, '{"extra":"not-array"}');

        $version = new Version($file);

        $this->assertSame('0.0.0', $version->version());
    }

    public function testVersionFallsBackWhenSwitonExtraIsNotAnArray(): void
    {
        $file = $this->tempDir . '/composer.json';
        file_put_contents($file, '{"extra":{"switon":"bad"}}');

        $version = new Version($file);

        $this->assertSame('0.0.0', $version->version());
    }

    public function testVersionFallsBackWhenVersionFieldIsEmptyString(): void
    {
        $file = $this->tempDir . '/composer.json';
        file_put_contents($file, '{"extra":{"switon":{"version":""}}}');

        $version = new Version($file);

        $this->assertSame('0.0.0', $version->version());
    }

    public function testVersionFallsBackWhenComposerJsonRootIsNotAnArray(): void
    {
        $file = $this->tempDir . '/composer.json';
        file_put_contents($file, '"not-an-object"');

        $version = new Version($file);

        $this->assertSame('0.0.0', $version->version());
    }

    public function testVersionFallsBackWhenComposerFileMissing(): void
    {
        $version = new Version($this->tempDir . '/missing-composer.json');

        $this->assertSame('0.0.0', $version->version());
    }

    public function testVersionFallsBackWhenComposerJsonInvalid(): void
    {
        $file = $this->tempDir . '/composer.json';
        file_put_contents($file, '{invalid json');

        $version = new Version($file);

        $this->assertSame('0.0.0', $version->version());
    }

    public function testVersionCachesResolvedValue(): void
    {
        $file = $this->tempDir . '/composer.json';
        file_put_contents(
            $file,
            <<<'JSON'
{
    "extra": {
        "switon": {
            "version": "1.0.0"
        }
    }
}
JSON
        );

        $version = new Version($file);

        $this->assertSame('1.0.0', $version->version());

        file_put_contents(
            $file,
            <<<'JSON'
{
    "extra": {
        "switon": {
            "version": "2.0.0"
        }
    }
}
JSON
        );

        $this->assertSame('1.0.0', $version->version());
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
}
