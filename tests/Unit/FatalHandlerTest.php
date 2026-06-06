<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Switon\Kernel\Event\FatalErrorOccurred;
use Switon\Kernel\FatalHandler;
use Switon\Kernel\Tests\Fixtures\KernelFunctionOverrides;
use Switon\Kernel\Tests\Fixtures\TestableFatalHandler;
use Switon\Kernel\Tests\Fixtures\TestableFatalHandlerUnknownErrorLabel;
use Switon\Kernel\Tests\TestCase;

require_once __DIR__ . '/../Fixtures/KernelFunctionOverrides.php';
require_once __DIR__ . '/../Fixtures/KernelFunctions.php';

#[AllowMockObjectsWithoutExpectations]
class FatalHandlerTest extends TestCase
{
    public function testRegisterWithoutEventDispatcher(): void
    {
        // Arrange
        $handler = new TestableFatalHandler();

        // Act
        $handler->register();

        // Assert - Should not throw exception when eventDispatcher is null
        $this->assertTrue(true);
    }

    public function testRegisterDispatchesFatalErrorEvent(): void
    {
        // Arrange
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (object $event): bool {
                if (!$event instanceof FatalErrorOccurred) {
                    return false;
                }

                return $event->message === 'Fatal boom'
                    && $event->file === '/tmp/test.php'
                    && $event->line === 42
                    && $event->type === 'E_ERROR';
            }));

        $handler = new TestableFatalHandler();
        $handler->setEventDispatcher($eventDispatcher);
        $handler->setLastError([
            'type' => E_ERROR,
            'message' => 'Fatal boom',
            'file' => '/tmp/test.php',
            'line' => 42,
        ]);

        // Act
        $handler->register();
        $handler->runRegisteredShutdown();

        // Assert - Expectations above verify dispatch behavior
        $this->assertTrue(true);
    }

    public function testRegisterIgnoresNonFatalErrors(): void
    {
        // Arrange
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $handler = new TestableFatalHandler();
        $handler->setEventDispatcher($eventDispatcher);
        $handler->setLastError([
            'type' => E_WARNING,
            'message' => 'Warning only',
            'file' => '/tmp/test.php',
            'line' => 12,
        ]);

        // Act
        $handler->register();
        $handler->runRegisteredShutdown();

        // Assert
        $this->assertTrue(true);
    }

    public function testDispatchUsesUnknownWhenFatalTypeHasNoDisplayName(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (object $event): bool {
                if (!$event instanceof FatalErrorOccurred) {
                    return false;
                }

                return $event->type === 'UNKNOWN'
                    && $event->message === 'Synthetic fatal';
            }));

        $handler = new TestableFatalHandlerUnknownErrorLabel();
        $handler->setEventDispatcher($eventDispatcher);
        $handler->setLastError([
            'type' => TestableFatalHandlerUnknownErrorLabel::SYNTHETIC_FATAL_TYPE,
            'message' => 'Synthetic fatal',
            'file' => '/tmp/unknown-type.php',
            'line' => 7,
        ]);

        $handler->register();
        $handler->runRegisteredShutdown();

        $this->assertTrue(true);
    }

    public function testRegisterSwallowsDispatchFailures(): void
    {
        // Arrange
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new RuntimeException('dispatch failed'));

        $handler = new TestableFatalHandler();
        $handler->setEventDispatcher($eventDispatcher);
        $handler->setLastError([
            'type' => E_PARSE,
            'message' => 'Parse error',
            'file' => '/tmp/test.php',
            'line' => 8,
        ]);

        // Act
        $handler->register();
        $handler->runRegisteredShutdown();

        // Assert - No exception should escape shutdown handling
        $this->assertTrue(true);
    }

    public function testGetLastErrorReturnsCapturedError(): void
    {
        KernelFunctionOverrides::$lastError = [
            'type' => E_ERROR,
            'message' => 'Captured fatal',
            'file' => '/tmp/fatal.php',
            'line' => 19,
        ];

        $handler = new class () extends FatalHandler {
            public function exposedGetLastError(): ?array
            {
                return $this->getLastError();
            }
        };

        $this->assertSame(KernelFunctionOverrides::$lastError, $handler->exposedGetLastError());
    }
}
