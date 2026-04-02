<?php

namespace Dogma;

use Dogma\Time\Date;
use Herd\Version;
use RuntimeException;
use function array_keys;
use function array_pad;
use function end;
use function explode;
use function intval;
use function is_int;
use function is_numeric;
use function max;
use function strval;
use function trim;

/**
 * Version: major.minor[.patch][.revision][-ts|nts][-platform][-build] [type] [date] [app]
 *
 * Version expression:
 * n = version number
 * n-n = range of versions
 * * = any
 * ? = any including alpha, beta, RC
 * ^ = last stable
 * + = last including alpha, beta, RC
 * _ = any before last stable
 * ~ = any before last
 * ! = negate expression
 */
class VersionFilter
{

    public const ANY_STABLE = '*';
    public const ANY = '?';
    public const LAST_STABLE = '^';
    public const LAST = '+';
    public const OLD_STABLE = '_';
    public const OLD = '~';

    private function __construct(
        public int|string $major,
        public int|string|null $minor,
        public int|string|null $patch,
        public int|string|null $revision,
        public ?string $build,
        public ?string $type,
        public ?bool $threadSafe,
        public ?string $platform,
    ) {}

    /**
     * @return list<self>
     */
    public static function parse(string|bool|float $expr, ?string $default = null): array
    {
        if ($expr === true || $expr === '') {
            $expr = $default ?? '*';
        }
        $parts = explode(',', strval($expr));
        $versions = [];
        foreach ($parts as $part) {
            $versions[] = self::parseExpression(trim($part));
        }

        return $versions;
    }

    public static function parseExpression(string|bool|float $expr, ?Version $platform = null): self
    {
        if ($expr === true) {
            $expr = '*';
        }
        $expr = (string) $expr;

        if (str_contains($expr, '.')) {
            [$major, $minor, $patch] = explode('.', $expr . '.');
            [$patch, $ts, $bits, $build] = explode('-', $patch . '---');
            if (Re::hasMatch($ts, '~^(pg|vc|vs)~')) {
                $build = $ts;
                $ts = null;
            }
        } else {
            $match = Re::match($expr, '~([0-9]+?|[*^_])(?:\\.?([0-9]+?|[*^_]))?(?:\\.?([0-9]+?(?:(?:alpha|beta|rc)[0-9]+)?|[*^_]))?-?(nts|ts|\\*)?-?(32|64|\\*)?-?((?:pg|vc|vs)[0-9]+)?$~i');
            if ($match === null) {
                throw new RuntimeException('Invalid version expression.');
            }
            $match = array_pad($match, 7, null);
            [, $major, $minor, $patch, $ts, $bits, $build] = $match;
        }

        $ts = $platform !== null ? ($platform->threadSafe ? 'ts' : 'nts') : $ts;
        $bits = $platform !== null ? $platform->platform : $bits;

        return new self(
            is_numeric($major) ? intval($major) : $major,
            is_numeric($minor) ? intval($minor) : $minor,
            is_numeric($patch) ? intval($patch) : $patch,
            is_numeric($revision) ? intval($revision) : $revision,
            $build,
            null,
            ($ts === '' || $ts === null) ? false : ($ts === '*' ? null : $ts === 'ts'),
            ($bits === '' || $bits === null) ? '64' : ($bits === '*' ? null : (int) $bits),
        );
    }

    /**
     * @param int[][] $familyTree
     * @param Version[][] $available
     */
    public function match(Version $that, array $familyTree = [], array $available = []): bool
    {
        if ($this->threadSafe !== null && $that->threadSafe !== null && $this->threadSafe !== $that->threadSafe) {
            return false;
        }

        if ($this->platform !== null && $that->platform !== null && $this->platform !== $that->platform) {
            return false;
        }

        if ($this->build !== null && $that->build !== null && $this->build !== $that->build) {
            return false;
        }

        $major = is_int($this->major) ? $this->major : $that->major;
        $other = is_int($this->major) ? $that->major : $this->major;
        $last = $familyTree !== [] ? max(array_keys($familyTree)) : null;
        if (!self::matchVersion($major, $other, $last)) {
            return false;
        }

        $minor = is_int($this->minor) ? $this->minor : $that->minor;
        $other = is_int($this->minor) ? $that->minor : $this->minor;
        $last = isset($familyTree[$major]) ? max(array_keys($familyTree[$major])) : null;
        if (!self::matchVersion($minor, $other, $last)) {
            return false;
        }

        $patch = is_int($this->patch) ? $this->patch : $that->patch;
        $other = is_int($this->patch) ? $that->patch : $this->patch;
        // search for last patch number in available versions
        $last = null;
        $familyKeys = ["{$major}.{$minor}", "{$major}", "{$major}.{$minor}.*", "{$major}.{$minor}.*-32", "{$major}.{$minor}.*-ts", "{$major}.{$minor}.*-ts-32"];
        foreach ($familyKeys as $key) {
            if (isset($available[$key])) {
                $versions = $available[$key];
                $last = end($versions)->patch;
            }
        }
        if (!self::matchVersion($patch, $other, $last)) {
            return false;
        }

        return true;
    }

    private static function matchVersion(int|string|bool|null $a, int|string|bool|null $b, int|null $latest): bool
    {
        if ($a === null || $b === null || $a === $b) {
            return true;
        } elseif ($a === true && $b === $latest) {
            return true;
        } elseif ($b === true && $a === $latest) {
            return true;
        } elseif ($a === false && $b !== $latest) {
            return true;
        } elseif ($b === false && $a !== $latest) {
            return true;
        } else {
            return false;
        }
    }

    public function format(): string
    {
        $result = strval($this->major);

        if ($this->minor !== null) {
            $result .= '.' . $this->minor;
        }
        if ($this->patch !== null) {
            $result .= '.' . $this->patch;
        }
        if ($this->revision !== null) {
            $result .= '.' . $this->revision;
        }
        if ($this->build !== null) {
            $result .= '-' . $this->build;
        }
        if ($this->threadSafe !== null) {
            $result .= '-' . ($this->threadSafe ? 'ts' : 'nts');
        }
        if ($this->platform !== null) {
            $result .= '-' . $this->platform;
        }

        return $result;
    }

}