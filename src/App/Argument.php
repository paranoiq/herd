<?php

namespace Herd\App;

use Attribute;

#[Attribute()]
class Argument
{

    public function __construct(
        public string $placeholder,
        public string|null $description = null
    ) {}

}
