<?php

namespace Herd\App;

use Attribute;

#[Attribute]
class Choices
{

    /**
     * @param list<string> $options
     */
    public function __construct(array $options)
    {

    }

}
