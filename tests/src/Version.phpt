<?php declare(strict_types = 1);

namespace Dogma\Tests\Debug;

use Dogma\Tester\Assert;
use Herd\Version;

require_once __DIR__ . '/../bootstrap.php';

$v800a1 = new Version(8, 0, 'alpha1', false, 64);
$v800a2 = new Version(8, 0, 'alpha2', false, 64);
$v800b1 = new Version(8, 0, 'beta1', false, 64);
$v800b2 = new Version(8, 0, 'beta2', false, 64);
$v800rc1 = new Version(8, 0, 'rc1', false, 64);
$v800rc2 = new Version(8, 0, 'rc2', false, 64);
$v800 = new Version(8, 0, 0, false, 64);
$v801 = new Version(8, 0, 1, false, 64);

$v810a1 = new Version(8, 1, 'alpha1', false, 64);
$v810a2 = new Version(8, 1, 'alpha2', false, 64);
$v810b1 = new Version(8, 1, 'beta1', false, 64);
$v810b2 = new Version(8, 1, 'beta2', false, 64);
$v810rc1 = new Version(8, 1, 'rc1', false, 64);
$v810rc2 = new Version(8, 1, 'rc2', false, 64);
$v810 = new Version(8, 1, 0, false, 64);
$v811 = new Version(8, 1, 1, false, 64);

$v80any = new Version(8, 0, null, false, 64);
$v80last = new Version(8, 0, true, false, 64);
$v80old = new Version(8, 0, false, false, 64);
$v81any = new Version(8, 1, null, false, 64);
$v81last = new Version(8, 1, true, false, 64);
$v81old = new Version(8, 1, false, false, 64);
$v8any = new Version(8, null, null, false, 64);
$v8last = new Version(8, null, true, false, 64);
$v8old = new Version(8, null, false, false, 64);


// any match
Assert::true($v80any->match($v800a1));
Assert::true($v80any->match($v800a2));
Assert::true($v80any->match($v800b1));
Assert::true($v80any->match($v800b2));
Assert::true($v80any->match($v800rc1));
Assert::true($v80any->match($v800rc2));
Assert::true($v80any->match($v800));
Assert::true($v80any->match($v801));
Assert::false($v80any->match($v810a1));
Assert::false($v80any->match($v810a2));
Assert::false($v80any->match($v810b1));
Assert::false($v80any->match($v810b2));
Assert::false($v80any->match($v810rc1));
Assert::false($v80any->match($v810rc2));
Assert::false($v80any->match($v810));
Assert::false($v80any->match($v811));