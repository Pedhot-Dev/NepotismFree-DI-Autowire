<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Autowire;

/**
 * A thin facade for AutowireResolver.
 * 
 * exact definition:
 * - A REFLECTION-BASED OBJECT CONSTRUCTOR
 * - NOT a dependency injection container
 * - NOT a service locator
 * - NOT a lifecycle manager
 * - NOT a cache of instances
 */
final class Autowire
{
    public function __construct(
        private readonly AutowireResolver $resolver
    ) {}

    /**
     * Resolves a class instance using Autowire rules.
     * 
     * @template T of object
     * @param string|class-string<T> $className
     * @param array<string, mixed> $context runtime arguments
     * @return T|object
     */
    public function resolve(string $className, array $context = []): object
    {
        return $this->resolver->resolve($className, $context);
    }
}