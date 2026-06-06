<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Kernel\Env;
use Switon\Kernel\Exception\EnvVariableNotFoundException;
use Switon\Kernel\Exception\MalformedEnvFileException;

use function file_put_contents;
use function getenv;
use function is_file;
use function putenv;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

class EnvTest extends TestCase
{
    protected string $tempFile;
    protected array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'env_test_');

        // Backup environment variables that tests may set
        $this->backupEnvVars([
            'TEST_VAR', 'BASE_URL', 'API_URL', 'NESTED_VAR',
            'KEY', 'KEY1', 'KEY2', 'KEY3', 'MULTI',
            'UNDEFINED_VAR', 'BASE', 'DB_HOST', 'SEG', 'COMBINED',
            'EMPTY', 'spaced_key',
        ]);
    }

    protected function tearDown(): void
    {
        // Restore environment variables
        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv("$key=$value");
            }
        }

        if (is_file($this->tempFile)) {
            unlink($this->tempFile);
        }
        parent::tearDown();
    }

    protected function backupEnvVars(array $vars): void
    {
        foreach ($vars as $var) {
            $this->originalEnv[$var] = getenv($var);
        }
    }

    protected function writeEnvFile(string $content): void
    {
        file_put_contents($this->tempFile, $content);
    }

    public function testLoadDoesNothingWhenFileDoesNotExist(): void
    {
        // Arrange
        $env = new Env('/nonexistent/file');

        // Act
        $env->load();

        // Assert - Should not throw exception
        $this->assertTrue(true);
    }

    public function testLoadIgnoresEmptyLines(): void
    {
        // Arrange
        $this->writeEnvFile("\n\nKEY=value\n\n");
        $env = new Env($this->tempFile);

        // Act
        $env->load();

        // Assert
        $this->assertSame('value', getenv('KEY'));
    }

    public function testLoadIgnoresComments(): void
    {
        // Arrange
        $this->writeEnvFile("# Comment\nKEY=value\n# Another comment");
        $env = new Env($this->tempFile);

        // Act
        $env->load();

        // Assert
        $this->assertSame('value', getenv('KEY'));
    }

    public function testLoadSetsSimpleKeyValue(): void
    {
        // Arrange
        $this->writeEnvFile('KEY=value');
        $env = new Env($this->tempFile);

        // Act
        $env->load();

        // Assert
        $this->assertSame('value', getenv('KEY'));
    }

    public function testLoadSetsEmptyStringWhenValueIsEmpty(): void
    {
        $this->writeEnvFile("EMPTY=\nKEY=still");
        $env = new Env($this->tempFile);

        $env->load();

        $this->assertSame('', getenv('EMPTY'));
        $this->assertSame('still', getenv('KEY'));
    }

    public function testLoadTrimsKeyAndUnquotedValueWhitespace(): void
    {
        $this->writeEnvFile("  spaced_key  =  trimmed  \n");
        $env = new Env($this->tempFile);

        $env->load();

        $this->assertSame('trimmed', getenv('spaced_key'));
    }

    public function testLoadSetsMultipleVariables(): void
    {
        // Arrange
        $this->writeEnvFile("KEY1=value1\nKEY2=value2\nKEY3=value3");
        $env = new Env($this->tempFile);

        // Act
        $env->load();

        // Assert
        $this->assertSame('value1', getenv('KEY1'));
        $this->assertSame('value2', getenv('KEY2'));
        $this->assertSame('value3', getenv('KEY3'));
    }

    public function testLoadWithSingleQuotesLiteral(): void
    {
        // Arrange - Write file with single quotes, dollar sign should be preserved literally
        file_put_contents($this->tempFile, "KEY='value'\n");
        $env = new Env($this->tempFile);

        // Act
        $env->load();

        // Assert - Single quotes preserve literal value (no variable substitution)
        $this->assertSame('value', getenv('KEY'));
    }

    public function testLoadWithDoubleQuotes(): void
    {
        // Arrange
        $this->writeEnvFile('KEY="value"');
        $env = new Env($this->tempFile);

        // Act
        $env->load();

        // Assert
        $this->assertSame('value', getenv('KEY'));
    }

    public function testLoadWithVariableSubstitution(): void
    {
        // Arrange
        $this->writeEnvFile("BASE_URL=http://example.com\nAPI_URL=\${BASE_URL}/api");
        $env = new Env($this->tempFile);

        // Act
        $env->load();

        // Assert
        $this->assertSame('http://example.com/api', getenv('API_URL'));
    }

    public function testLoadWithVariableSubstitutionSimpleSyntax(): void
    {
        // Arrange
        $this->writeEnvFile("BASE_URL=http://example.com\nAPI_URL=\$BASE_URL/api");
        $env = new Env($this->tempFile);

        // Act
        $env->load();

        // Assert
        $this->assertSame('http://example.com/api', getenv('API_URL'));
    }

    public function testLoadWithVariableSubstitutionThrowsExceptionWhenVariableNotFound(): void
    {
        // Arrange
        $this->writeEnvFile('API_URL=${BASE_URL}/api');
        $env = new Env($this->tempFile);

        // Assert
        $this->expectException(EnvVariableNotFoundException::class);
        $this->expectExceptionMessage('BASE_URL');

        // Act
        $env->load();
    }

    public function testLoadDoesNotOverrideExistingEnvVariables(): void
    {
        // Arrange
        putenv('TEST_VAR=existing');
        $this->writeEnvFile('TEST_VAR=new_value');
        $env = new Env($this->tempFile);

        // Act
        $env->load();

        // Assert - Should keep existing value
        $this->assertSame('existing', getenv('TEST_VAR'));
    }

    public function testLoadWithMultiLineValue(): void
    {
        // Arrange - Write file with actual line breaks (not escaped \n)
        $content = "MULTI=\"line1" . PHP_EOL . "line2" . PHP_EOL . "line3\"" . PHP_EOL;
        file_put_contents($this->tempFile, $content);
        $env = new Env($this->tempFile);

        // Act
        $env->load();

        // Assert
        $expected = "line1" . PHP_EOL . "line2" . PHP_EOL . "line3";
        $this->assertSame($expected, getenv('MULTI'));
    }

    public function testLoadThrowsExceptionWhenQuotedValueIsNotClosed(): void
    {
        // Arrange
        $this->writeEnvFile("MULTI=\"line1" . PHP_EOL . "line2");
        $env = new Env($this->tempFile);

        // Assert
        $this->expectException(MalformedEnvFileException::class);
        $this->expectExceptionMessage('missing closing');
        $this->expectExceptionMessage('MULTI');

        // Act
        $env->load();
    }

    public function testLoadThrowsWhenSingleQuotedMultilineValueIsNotClosed(): void
    {
        $this->writeEnvFile("NOTE='line1" . PHP_EOL . 'line2');
        $env = new Env($this->tempFile);

        $this->expectException(MalformedEnvFileException::class);
        $this->expectExceptionMessage("missing closing");
        $this->expectExceptionMessage('NOTE');
        $this->expectExceptionMessage("'");

        $env->load();
    }

    public function testLoadSubstitutesSameVariableMultipleTimesInOneValue(): void
    {
        // Avoid "PATH" — system env is never overridden by .env
        $this->writeEnvFile("SEG=part\nCOMBINED=\${SEG}/\${SEG}/end");
        $env = new Env($this->tempFile);

        $env->load();

        $this->assertSame('part/part/end', getenv('COMBINED'));
    }

    public function testLoadIgnoresLineWithoutEqualsSign(): void
    {
        $this->writeEnvFile("not_a_valid_assignment\nKEY=kept\n");
        $env = new Env($this->tempFile);

        $env->load();

        $this->assertSame('kept', getenv('KEY'));
        $this->assertFalse(getenv('not_a_valid_assignment'));
    }

    public function testGetReturnsDefaultWhenVariableNotFound(): void
    {
        // Arrange
        $env = new Env($this->tempFile);

        // Act & Assert
        $this->assertSame('default', $env->get('NONEXISTENT', 'default'));
        $this->assertNull($env->get('NONEXISTENT'));
    }

    public function testGetReturnsValueFromEnvFile(): void
    {
        // Arrange
        $this->writeEnvFile('KEY=value');
        $env = new Env($this->tempFile);

        // Act & Assert
        $this->assertSame('value', $env->get('KEY'));
    }

    public function testGetReturnsValueFromSystemEnvironment(): void
    {
        // Arrange
        putenv('TEST_VAR=system_value');
        $env = new Env($this->tempFile);

        // Act & Assert
        $this->assertSame('system_value', $env->get('TEST_VAR'));
    }

    public function testLoadIsIdempotent(): void
    {
        // Arrange
        $this->writeEnvFile('KEY=value');
        $env = new Env($this->tempFile);

        // Act
        $env->load();
        $env->load(); // Load again

        // Assert
        $this->assertSame('value', getenv('KEY'));
    }

    public function testLoadWithUndefinedVariableThrowsException(): void
    {
        // Arrange
        $this->writeEnvFile('KEY=${UNDEFINED_VAR}');
        $env = new Env($this->tempFile);

        // Assert
        $this->expectException(EnvVariableNotFoundException::class);
        $this->expectExceptionMessage('UNDEFINED_VAR');

        // Act
        $env->load();
    }
}
