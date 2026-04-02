<?php

namespace Herd\App;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Action
{

    /**
     * @param list<string> $aliases
     * @param list<string> $args
     */
    public function __construct(
        public string $name,
        public array $aliases,
        public string $description,
        public array $args = [],
    ) {}

}
