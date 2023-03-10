<?php declare(strict_types = 1);

namespace Herd;

use Attribute;

#[Attribute]
class Action
{

    public readonly string $action;

    public readonly string $short;

    public readonly string $description;

    public readonly string $param;

    public function __construct(string $action, string $short, string $description, string $param)
    {
        $this->action = $action;
        $this->short = $short;
        $this->description = $description;
        $this->param = $param;
    }

}
