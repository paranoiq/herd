<?php

namespace Herd\App;

use Attribute;

/**
 * Resource
 * Action(Argument)
 * Option(Resource, Action, Argument)
 * Argument(Type, Choices)
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Resource
{

    /**
     * @param list<string> $aliases
     */
    public function __construct(
        public string $name,
        public array $aliases,
    ) {}

}
