<?php

namespace Herd\App;

use Attribute;

#[Attribute]
class RouteList
{

    final public const CLI = 'cli'; // app command "arg1" ["arg2", "arg3"] --opt1 val1 --opt2 val2
    final public const HTTP = 'http'; // http://base.url/app/command/arg1/arg2,arg3?opt1=val1&opt2=val1
    final public const HTTPS = 'https'; //

    private readonly string $protocol;

    private readonly ?string $path;

    public function __construct(string $protocol = self::CLI, ?string $path = null)
    {

    }

}
