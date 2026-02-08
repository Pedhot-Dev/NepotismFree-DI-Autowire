<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Autowire\Exception;

use Exception;

final class ResolutionException extends Exception implements AutowireException
{
    public static function fromParameter(string $class, string $parameter, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf("Failed to resolve parameter '$%s' in class '%s': %s", $parameter, $class, $reason),
            0,
            $previous
            );
    }

    public static function circularDependency(string $class, array $stack): self
    {
        return new self(sprintf(
            "Circular dependency detected while autowiring '%s'. Stack: %s",
            $class,
            implode(' -> ', $stack)
        ));
    }
}