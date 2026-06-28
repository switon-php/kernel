<?php

declare(strict_types=1);

namespace Switon\Kernel\Event;

use Switon\Kernel\FatalHandlerInterface;
use Switon\Core\SenderAwareInterface;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Fatal error occurred event.
 *
 * This event is dispatched when a PHP fatal error occurs.
 * Log category: <code>switon.kernel.fatal.error.occurred</code>
 *
 * @see \Switon\Kernel\FatalHandlerInterface
 * @see \Switon\Kernel\FatalHandlerInterface::register()
 * @see \Switon\Kernel\FatalHandler
 */
#[EventLevel(Severity::ERROR)]
class FatalErrorOccurred implements SenderAwareInterface
{
    public function __construct(
        public FatalHandlerInterface $sender,
        public string $message,
        public string $file,
        public int    $line,
        public string $type,
    ) {
    }

    public function getSender(): FatalHandlerInterface
    {
        return $this->sender;
    }
}
