<?php

declare(strict_types=1);

namespace Switon\Kernel\Tests\Unit\Event;

use ReflectionClass;
use Switon\Kernel\Event\ServiceBootstrapped;
use Switon\Kernel\Tests\TestCase;

class ServiceBootstrappedTest extends TestCase
{
    public function testServiceBootstrappedEventCanBeInstantiated(): void
    {
        $providers = ['Provider1', 'Provider2', 'Provider3'];
        $event = new ServiceBootstrapped($this->createStub(\Switon\Kernel\ServiceBootstrapper::class), $providers);

        $this->assertInstanceOf(ServiceBootstrapped::class, $event);
    }

    public function testServiceBootstrappedEventStoresProviders(): void
    {
        $providers = [
            'Switon\\Core\\ServiceProvider',
            'Switon\\Db\\ServiceProvider',
            'Switon\\Http\\ServiceProvider',
        ];

        $event = new ServiceBootstrapped($this->createStub(\Switon\Kernel\ServiceBootstrapper::class), $providers);

        $this->assertSame($providers, $event->providers);
    }

    public function testServiceBootstrappedEventWithEmptyProviders(): void
    {
        $event = new ServiceBootstrapped($this->createStub(\Switon\Kernel\ServiceBootstrapper::class), []);

        $this->assertInstanceOf(ServiceBootstrapped::class, $event);
        $this->assertSame([], $event->providers);
    }

    public function testServiceBootstrappedEventPropertyIsPublic(): void
    {
        $reflection = new ReflectionClass(ServiceBootstrapped::class);
        $properties = $reflection->getProperties();

        $this->assertCount(2, $properties);

        $senderProperty = $properties[0];
        $providersProperty = $properties[1];
        $this->assertSame('sender', $senderProperty->getName());
        $this->assertTrue($senderProperty->isPublic());
        $this->assertSame('providers', $providersProperty->getName());
        $this->assertTrue($providersProperty->isPublic());
    }
}
