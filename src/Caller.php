<?php declare(strict_types = 1);

namespace Herd;

use Closure;
use Dogma\Io\Io;
use Dogma\Re;
use Dogma\ShouldNotHappenException;
use Dogma\Str;
use ReflectionExtension;
use ReflectionFunction;
use ReflectionMethod;
use function array_merge;
use function explode;
use function get_loaded_extensions;
use function implode;
use function is_array;
use function is_string;
use function rd;
use function str_contains;

class Caller
{

    /**
     * Call given function on another PHP binary
     * Must be a system function, closure or static method without dependencies and only with constant arguments.
     *
     * @param string $binary
     * @param callable&array{class-string, string} $callback
     * @return array
     */
    public static function callOther(string $binary, callable $callback): array
    {
        if (is_array($callback)) {
            $ref = new ReflectionMethod(...$callback);
        } elseif (is_string($callback) && str_contains($callback, '::')) {
            $ref = new ReflectionMethod(...explode('::', $callback));
        } elseif (is_string($callback) || $callback instanceof Closure) {
            $ref = new ReflectionFunction($callback);
        } else {
            throw new ShouldNotHappenException('Invalid callback.');
        }

        if ($ref->isUserDefined()) {
            $file = $ref->getFileName();
            $start = $ref->getStartLine();
            $end = $ref->getEndLine();
            $code = implode("\n", Io::readLines($file, $start - 1, $end - $start + 1));
        } else {
            // todo?
        }
        rd($code);

        $match = Re::match($code, '~\\{(.*)\\}~s');
        rd(Str::between($code, '{', '}'));
        rd($match[1]);

        return [];
    }

    /**
     * @return string[]
     */
    private static function getVer(): array
    {
        $extensions = array_merge(get_loaded_extensions(), get_loaded_extensions(true));

        $versions = [];
        foreach ($extensions as $ext) {
            $ref = new ReflectionExtension($ext);
            $versions[$ref->getName()] = $ref->getVersion();
        }

        return $versions;
    }

}
