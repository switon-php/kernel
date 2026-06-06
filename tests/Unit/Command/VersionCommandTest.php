<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ConsoleInterface;
use Switon\Kernel\Command\VersionCommand;
use Switon\Kernel\Tests\Support\BufferedConsole;
use Switon\Kernel\Tests\TestCase;
use Switon\Kernel\VersionInterface;

#[CoversClass(VersionCommand::class)]
class VersionCommandTest extends TestCase
{
    #[Autowired] protected VersionCommand $versionCommand;
    #[Autowired] protected VersionInterface $frameworkVersion;
    #[Autowired] protected BufferedConsole $console;

    protected function setUpContainer(): void
    {
        $console = new BufferedConsole();
        $this->console = $console;
        $this->container->set(ConsoleInterface::class, $console);
        $this->container->set(BufferedConsole::class, $console);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->console->clearOutput();
    }

    public function testShowActionDisplaysAllVersionLines(): void
    {
        $this->versionCommand->showAction();

        $output = $this->console->getOutput();

        $this->assertCount(4, $output);
        $this->assertStringStartsWith('      php: ', $output[0]);
        $this->assertStringStartsWith('   swoole: ', $output[1]);
        $this->assertStringStartsWith('      app: ', $output[2]);
        $this->assertStringStartsWith('framework: ', $output[3]);
    }

    public function testShowActionDisplaysFrameworkVersion(): void
    {
        $this->versionCommand->showAction();

        $output = $this->console->getOutput();

        $this->assertSame($this->frameworkVersion->version(), substr($output[3], strlen('framework: ')));
    }
}
