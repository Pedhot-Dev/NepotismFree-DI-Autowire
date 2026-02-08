<?php

declare(strict_types=1);

namespace PedhotDev\NepotismFree\Autowire\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class FromContainer
{
    public function __construct(
        public string $id
    ) {}
}
