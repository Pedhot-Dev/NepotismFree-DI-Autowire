<?php

declare(strict_types=1);

namespace Tests\Autowire;

use PHPUnit\Framework\TestCase;
use PedhotDev\NepotismFree\Autowire\Autowire;
use PedhotDev\NepotismFree\Autowire\AutowireResolver;
use PedhotDev\NepotismFree\Contract\ContainerInterface;
use PedhotDev\NepotismFree\Autowire\Exception\ResolutionException;
use PedhotDev\NepotismFree\Autowire\Attribute\FromContainer;
use PedhotDev\NepotismFree\Autowire\Attribute\FromEnv;
use PedhotDev\NepotismFree\Autowire\Attribute\FromValue;

// Fixtures
class MinimalClass {}

class ClassWithOneDep {
    public function __construct(public MinimalClass $dep) {}
}

class ClassWithFromContainer {
    public function __construct(
        #[FromContainer('my.service')]
        public object $service
    ) {}
}

class ClassWithFromEnv {
    public function __construct(
        #[FromEnv('TEST_ENV_VAR')]
        public string $value,
        #[FromEnv('MISSING_ENV', 'default')]
        public string $defaulted
    ) {}
}

class ClassWithFromValue {
    public function __construct(
        #[FromValue(123)]
        public int $value
    ) {}
}

class ClassWithContextMismatch {
    public function __construct(public string $name) {}
}

class CircularA {
    public function __construct(public CircularB $b) {}
}
class CircularB {
    public function __construct(public CircularA $a) {}
}

class ClassWithContainerFallback {
    public function __construct(public InContainerService $service) {}
}
class InContainerService {}


class AutowireTest extends TestCase
{
    private $container;
    private AutowireResolver $resolver;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->resolver = new AutowireResolver($this->container);
    }

    public function testResolveNoDependencies(): void
    {
        $instance = $this->resolver->resolve(MinimalClass::class);
        $this->assertInstanceOf(MinimalClass::class, $instance);
    }

    public function testResolveSimpleDependencyInstantiatesNew(): void
    {
        // MinimalClass is not in container, but is instantiable.
        // Autowire should instantiate it.
        $this->container->method('has')->willReturn(false);

        $instance = $this->resolver->resolve(ClassWithOneDep::class);
        $this->assertInstanceOf(ClassWithOneDep::class, $instance);
        $this->assertInstanceOf(MinimalClass::class, $instance->dep);
    }

    public function testResolveFromContainerAttribute(): void
    {
        $dummyService = new \stdClass();
        $this->container->expects($this->once())
            ->method('has')
            ->with('my.service')
            ->willReturn(true);
        $this->container->expects($this->once())
            ->method('get')
            ->with('my.service')
            ->willReturn($dummyService);

        $instance = $this->resolver->resolve(ClassWithFromContainer::class);
        $this->assertSame($dummyService, $instance->service);
    }

    public function testResolveFromContainerAttributeThrowsIfMissing(): void
    {
        $this->container->method('has')->with('my.service')->willReturn(false);

        $this->expectException(ResolutionException::class);
        $this->expectExceptionMessage("Service 'my.service' not found");

        $this->resolver->resolve(ClassWithFromContainer::class);
    }

    public function testResolveFromEnvAttribute(): void
    {
        putenv('TEST_ENV_VAR=hello');
        try {
            $instance = $this->resolver->resolve(ClassWithFromEnv::class);
            $this->assertEquals('hello', $instance->value);
            $this->assertEquals('default', $instance->defaulted);
        } finally {
            putenv('TEST_ENV_VAR'); // unset
        }
    }

    public function testResolveFromValueAttribute(): void
    {
        $instance = $this->resolver->resolve(ClassWithFromValue::class);
        $this->assertEquals(123, $instance->value);
    }

    public function testResolveFromContext(): void
    {
        $instance = $this->resolver->resolve(ClassWithContextMismatch::class, ['name' => 'Alice']);
        $this->assertEquals('Alice', $instance->name);
    }

    public function testResolveFromContainerFallback(): void
    {
        // If the parameter type exists in container, use it.
        $service = new InContainerService();
        $this->container->expects($this->once())
            ->method('has')
            ->with(InContainerService::class)
            ->willReturn(true);
        $this->container->expects($this->once())
            ->method('get')
            ->with(InContainerService::class)
            ->willReturn($service);

        $instance = $this->resolver->resolve(ClassWithContainerFallback::class);
        $this->assertSame($service, $instance->service);
    }

    public function testCircularDependencyThrowsException(): void
    {
        $this->expectException(ResolutionException::class);
        $this->expectExceptionMessage("Circular dependency detected");
        
        $this->resolver->resolve(CircularA::class);
    }

    public function testFacadeForwardsCall(): void
    {
        $facade = new Autowire($this->resolver);
        $instance = $facade->resolve(MinimalClass::class);
        $this->assertInstanceOf(MinimalClass::class, $instance);
    }
}