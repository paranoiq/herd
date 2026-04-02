<?php declare(strict_types = 1);

namespace Dogma;

use function explode;

/**
 * TODO: temporary copy of Dogma\Parse until the package is updated
 */
class Parse
{

    /**
     * @param non-empty-string $separator
     * @return list<int>
     */
    public static function ints(string $string, string $separator): array
    {
        $parts = explode($separator, $string);

        $ints = [];
        foreach ($parts as $part) {
            Check::int($part);
            $ints[] = $part;
        }

        return $ints;
    }

    /**
     * @param non-empty-string $separator
     * @return list<int|null>
     */
    public static function intsOrNulls(string $string, string $separator): array
    {
        $parts = explode($separator, $string);

        $ints = [];
        foreach ($parts as $part) {
            Check::nullableInt($part);
            $ints[] = $part;
        }

        return $ints;
    }

}