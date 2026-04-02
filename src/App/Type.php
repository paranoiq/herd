<?php

namespace Herd\App;

use Attribute;

#[Attribute]
class Type
{

    final public const BOOL = 'bool';

    final public const INT = 'int';
    final public const UINT = 'uint';

    final public const FLOAT = 'float';
    final public const UFLOAT = 'ufloat';

    final public const NUMERIC = 'num';
    final public const UNUMERIC = 'unum';

    final public const STRING = 'string';

}
