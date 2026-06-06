<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Fixtures;

use Switon\Core\App;
use Switon\Core\AppInterface;
use Switon\Core\Attribute\Scene;
use Switon\Core\SceneManagerInterface;

#[Scene('fixture-scene')]
class SceneBootstrapKernel extends TestableKernel
{
    public function getBootstrappedScene(): string
    {
        /** @var SceneManagerInterface $sceneManager */
        $sceneManager = App::getContainer()?->get(SceneManagerInterface::class);

        return $sceneManager->getScene();
    }

    public function getBootstrappedTimezone(): string
    {
        /** @var AppInterface $app */
        $app = App::getContainer()?->get(AppInterface::class);

        return $app->timezone();
    }
}
