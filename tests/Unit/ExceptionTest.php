<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Kernel\Exception\MalformedEnvFileException;

class ExceptionTest extends TestCase
{
    public function testMalformedEnvFileExceptionContainsHelpfulMessage(): void
    {
        // Act & Assert
        try {
            MalformedEnvFileException::raise(
                'Malformed .env file "{file}": missing closing {quote} quote for "{name}" starting at line {line}',
                ['file' => '/tmp/.env', 'quote' => '"', 'name' => 'MULTI', 'line' => 3]
            );
            $this->fail('Expected MalformedEnvFileException to be thrown');
        } catch (MalformedEnvFileException $e) {
            $this->assertStringContainsString('/tmp/.env', $e->getMessage());
            $this->assertStringContainsString('MULTI', $e->getMessage());
            $this->assertStringContainsString('line 3', $e->getMessage());
        }
    }
}
