<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Autowire;

use PedhotDev\NepotismFree\Autowire\Attribute\FromContainer;
use PedhotDev\NepotismFree\Autowire\Attribute\FromEnv;
use PedhotDev\NepotismFree\Autowire\Attribute\FromValue;
use PedhotDev\NepotismFree\Autowire\Exception\ResolutionException;
use PedhotDev\NepotismFree\Contract\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionAttribute;

final class AutowireResolver
{
    private array $building = [];

    public function __construct(
        private readonly ContainerInterface $container
    ) {}

    public function resolve(string $className, array $context = []): object
    {
        if (isset($this->building[$className])) {
            throw ResolutionException::circularDependency($className, array_keys($this->building));
        }

        $this->building[$className] = true;

        try {
            // 1. Reflect
            try {
                $reflector = new ReflectionClass($className);
            } catch (\ReflectionException $e) {
                throw new ResolutionException("Class '$className' does not exist.", 0, $e);
            }

            if (!$reflector->isInstantiable()) {
                throw new ResolutionException("Class '$className' is not instantiable.");
            }

            $constructor = $reflector->getConstructor();

            if ($constructor === null) {
                return new $className();
            }

            // 2. Resolve Parameters
            $args = [];
            foreach ($constructor->getParameters() as $parameter) {
                $args[] = $this->resolveParameter($className, $parameter, $context);
            }

            return $reflector->newInstanceArgs($args);

        } finally {
            unset($this->building[$className]);
        }
    }

    private function resolveParameter(string $className, ReflectionParameter $parameter, array $context): mixed
    {
        $paramName = $parameter->getName();
        $type = $parameter->getType();
        $typeName = ($type instanceof ReflectionNamedType) ? $type->getName() : null;

        // 1. Attributes (Highest Priority)
        $attributes = $parameter->getAttributes();
        foreach ($attributes as $attribute) {
            $inst = $attribute->newInstance();

            if ($inst instanceof FromContainer) {
                if ($this->container->has($inst->id)) {
                    return $this->container->get($inst->id);
                }
                throw ResolutionException::fromParameter($className, $paramName, "Service '{$inst->id}' not found in container (requested via #[FromContainer]).");
            }

            if ($inst instanceof FromEnv) {
                $val = getenv($inst->variableName);
                if ($val !== false) {
                    return $val;
                }
                if ($inst->default !== null) {
                    return $inst->default;
                }
                throw ResolutionException::fromParameter($className, $paramName, "Environment variable '{$inst->variableName}' not set.");
            }

            if ($inst instanceof FromValue) {
                return $inst->value;
            }
        }

        // 2. Context (Explicit Runtime Arguments)
        if (array_key_exists($paramName, $context)) {
            return $context[$paramName];
        }
        if ($typeName && array_key_exists($typeName, $context)) {
             return $context[$typeName];
        }


        // 3. Existing Container & Recursive Autowiring
        if ($typeName && !$type->isBuiltin()) {
            // Priority 3a: Explicit Container Binding
            // We want to prefer the Container ONLY if it has an explicit instruction.
            // If it just "has" the class because it exists, we want Autowire to handle it
            // so we can support Attributes in the dependency graph.
            $shouldDelegate = false;
            if ($this->container instanceof \PedhotDev\NepotismFree\Contract\IntrospectableContainerInterface) {
                if (array_key_exists($typeName, $this->container->getDefinitions())) {
                    $shouldDelegate = true;
                }
            } elseif ($this->container->has($typeName)) {
                // Fallback for non-introspectable containers
                $shouldDelegate = true;
            }

            if ($shouldDelegate) {
                try {
                    return $this->container->get($typeName);
                } catch (\Throwable $e) {
                     throw $e;
                }
            }
            
            // Priority 3b: Recursive Autowiring
            // If it's a concrete class (and not explicitly in container), try to autowire it.
            if (class_exists($typeName)) {
                return $this->resolve($typeName, $context);
            }
        }


        // 4. Default Value
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        // 5. Nullable
        if ($type && $type->allowsNull()) {
            return null;
        }

        throw ResolutionException::fromParameter($className, $paramName, "No binding, context, or default value found.");
    }
}