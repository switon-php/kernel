<?php

declare(strict_types=1);

namespace Switon\Kernel\Command;

use Switon\Command\Attribute\Hidden;
use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ConsoleInterface;
use Switon\Kernel\VersionInterface;

use function defined;

/**
 * Show runtime, application, and framework version information.
 *
 * Guidance: use this for quick bootstrap diagnostics when confirming the active PHP, Swoole, app, and framework versions.
 *
 * @see \Switon\Core\AppInterface
 * @see \Switon\Kernel\VersionInterface
 * @see \Switon\Core\ConsoleInterface Output boundary
 */
#[Hidden]
class VersionCommand
{
    #[Autowired] protected AppInterface $app;
    #[Autowired] protected VersionInterface $frameworkVersion;
    #[Autowired] protected ConsoleInterface $console;

    /**
     * Prints PHP, Swoole, app, and framework version values.
     */
    public function showAction(): void
    {
        $this->console->writeLn('      php: ' . PHP_VERSION);
        $this->console->writeLn('   swoole: ' . (defined('SWOOLE_VERSION') ? SWOOLE_VERSION : 'n/a'));
        $this->console->writeLn('      app: ' . $this->app->version());
        $this->console->writeLn('framework: ' . $this->frameworkVersion->version());
    }
}
