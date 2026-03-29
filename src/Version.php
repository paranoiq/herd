<?php

namespace Herd;

use Dogma\Comparable;
use Dogma\Re;
use Dogma\Time\Date;
use RuntimeException;
use function array_keys;
use function array_pad;
use function end;
use function is_int;
use function is_numeric;
use function is_string;
use function max;
use function preg_match;
use function rd;
use function str_replace;

/**
 * Version: major.minor.patch ts|nts 32|64 build date type
 *
 * Version expression:
 * n = version number
 * n-n = range of versions
 * * = any
 * ** = any including alpha, beta, RC
 * ^ = last stable
 * ^^ = last including alpha, beta, RC
 * _ = any before last stable
 * __ = any before last
 * ! = negate expression
 */
class Version implements Comparable
{

    public function __construct(
        public int|bool|null $major, // 8
        public int|bool|null $minor, // 1
        public int|string|bool|null $patch, // 0|alpha1|beta2|rc3...
        public ?bool $threadSafe = null, // ts|nts
        public ?int $bits = null, // 32|64
        public ?string $build = null, // vc15|vs16|vs17
        public ?Date $date = null,
        public ?string $type = null,
        public ?string $app = null,
    ) {}

    public static function jsonUnserialize(array $data): self
    {
        $data[6] = $data[6] !== null ? new Date($data[6]) : null;

        return new self(...$data);
    }

    public function jsonSerialize(): array
    {
        return [$this->major, $this->minor, $this->patch, $this->threadSafe, $this->bits, $this->build, $this->date?->format('Y-m-d'), $this->type, $this->app];
    }

    public static function parsePhpUrl(string $url): self
    {
        $match = Re::match($url, '~php-([0-9]+)(?:\\.([0-9]+))?(?:\\.([0-9]+(?:(?:alpha|beta|RC)[0-9]+)?))?-?(nts)?.*(x64|x86|Win32)~i');
        [, $major, $minor, $patch, $nts, $bits] = $match;

        return new self(
            self::parsePart($major),
            self::parsePart($minor),
            self::parsePart($patch),
            $nts !== 'nts',
            $bits === 'x86' || $bits === 'Win32' || $bits === 'win32' ? 32 : 64,
        );
    }

    public static function parseRelease(string $name, string $re, string $app): self
    {
        $match = Re::match($name, $re);
        $version = $match['version'];
        $date = $match['date'] ?? null;
        $type = $match['type'] ?? null;
        [$major, $minor, $patch] = explode('.', $version . '..');

        return new self(
            self::parsePart($major),
            self::parsePart($minor),
            self::parsePart($patch),
            null,
            null,
            null,
            $date ? new Date($date) : null,
            $type,
            $app,
        );
    }

    public static function parseDirectory(string $dir): self
    {
        $match = Re::match($dir, '~([0-9]+)(?:\\.([0-9]+))?(?:\\.([0-9]+(?:(?:alpha|beta|RC)[0-9]+)?))?-?(ts)?-?(32)?~');
        if (!$match) {
            throw new RuntimeException('Wrong directory');
        }
        $match = array_pad($match, 6, null);
        [, $major, $minor, $patch, $ts, $bits] = $match;

        return new self(
            self::parsePart($major),
            self::parsePart($minor),
            self::parsePart($patch),
            $ts === 'ts',
            $bits === '32' ? 32 : 64,
        );
    }

    public static function parseExpression(string|bool|float $expression, ?Version $platform = null): self
    {
        global $argv;
        rd($argv);
        if ($expression === true) {
            $expression = '*';
        }
        $expression = (string) $expression;
rd($expression);
        if (str_contains($expression, '.')) {
            [$major, $minor, $patch] = explode('.', $expression . '.');
            [$patch, $ts, $bits, $build] = explode('-', $patch . '---');
            if (Re::match($ts, '~^(pg|vc|vs)~')) {
                $build = $ts;
                $ts = null;
            }
        } else {
            $match = Re::match($expression, '~([0-9]+?|[*^_])(?:\\.?([0-9]+?|[*^_]))?(?:\\.?([0-9]+?(?:(?:alpha|beta|rc)[0-9]+)?|[*^_]))?-?(nts|ts|\\*)?-?(32|64|\\*)?-?((?:pg|vc|vs)[0-9]+)?$~i');
            if (!$match) {
                throw new RuntimeException('Invalid version expression.');
            }
            $match = array_pad($match, 7, null);
            [, $major, $minor, $patch, $ts, $bits, $build] = $match;
        }

        $ts = $platform !== null ? ($platform->threadSafe ? 'ts' : 'nts') : $ts;
        $bits = $platform !== null ? $platform->bits : $bits;

        return new self(
            self::parsePart($major),
            self::parsePart($minor),
            self::parsePart($patch),
            ($ts === '' || $ts === null) ? false : ($ts === '*' ? null : $ts === 'ts'),
            ($bits === '' || $bits === null) ? 64 : ($bits === '*' ? null : (int) $bits),
            $build,
        );
    }

    private static function parsePart(?string $value): int|string|bool|null
    {
        if ($value === null || $value === '' || $value === '*') {
            return null; // any
        }
        if ($value === '^') {
            return true; // last
        }
        if ($value === '_') {
            return false; // first
        }

        return is_numeric($value) ? (int) $value : $value;
    }

    public function setPatch(int|string $patch): self
    {
        return new self($this->major, $this->minor, $patch, $this->threadSafe, $this->bits);
    }

    public function unsetPatch(): self
    {
        return new self($this->major, $this->minor, null, $this->threadSafe, $this->bits);
    }

    public function setMinor(int|string $minor): self
    {
        return new self($this->major, $minor, null, $this->threadSafe, $this->bits);
    }

    public function unsetMinor(): self
    {
        return new self($this->major, null, null, $this->threadSafe, $this->bits);
    }

    public function unsetMajor(): self
    {
        return new self($this->major, null, null, $this->threadSafe, $this->bits);
    }

    public function getFamily(): self
    {
        return new self($this->major, $this->minor, null, $this->threadSafe, $this->bits);
    }

    // -----------------------------------------------------------------------------------------------------------------

    public function equals(self $other): bool
    {
        return $this->format6() === $other->format6();
    }

    public function equals3(self $other): bool
    {
        return $this->major === $other->major && $this->minor === $other->minor && $this->patch === $other->patch;
    }

    /**
     * @param self $other
     */
    public function compare(Comparable $other): int
    {
        return $this->major <=> $other->major
            ?: $this->minor <=> $other->minor
            ?: is_string($other->patch) <=> is_string($this->patch) // "0RC5" < 0
            ?: $this->patch <=> $other->patch
            ?: $this->threadSafe <=> $other->threadSafe
            ?: $other->bits <=> $this->bits
            ?: $other->build <=> $this->build;
    }

    public function formatFamily(): string
    {
        return self::part($this->major)
            . '.' . self::part($this->minor)
            . '.*'
            . ($this->threadSafe === null ? '-*' : ($this->threadSafe ? '-ts' : ''))
            . ($this->bits === null ? '-*' : ($this->bits === 32 ? '-32' : ''));
    }

    public function formatFamilyPath(): string
    {
        return self::part($this->major)
            . '.' . self::part($this->minor)
            . ($this->threadSafe === null ? '' : ($this->threadSafe ? '-ts' : ''))
            . ($this->bits === null ? '' : ($this->bits === 32 ? '-32' : ''));
    }

    public function format6(): string
    {
        return self::part($this->major)
            . '.' . self::part($this->minor)
            . '.' . self::part($this->patch)
            . ($this->threadSafe === null ? '-*' : ($this->threadSafe ? '-ts' : ''))
            . ($this->bits === null ? '-*' : ($this->bits === 32 ? '-32' : ''))
            . ($this->build === null ? '-*' : '-' . $this->build);
    }

    public function format3(): string
    {
        return self::part($this->major)
            . '.' . self::part($this->minor)
            . '.' . self::part($this->patch);
    }

    public function format2(): string
    {
        return self::part($this->major)
            . '.' . self::part($this->minor);
    }

    public function format3t(): string
    {
        static $types = [
            // MySQL
            'LTS Release' => '',
            'Innovation Release' => 'IR',
            'General Availability' => '',
            'Release Candidate' => 'RC',
            'Development Milestone' => 'M',
            'Milestone 16' => 'M',
            'Milestone 15' => 'M',
            'Milestone 14' => 'M',
            'Milestone 13' => 'M',
            'Milestone 12' => 'M',
            'Milestone 11' => 'M',

            // MariaDB
            'Alpha' => 'A',
            'Beta' => 'B',
            'Gamma' => 'C',
            'Preview' => 'PR',
            'RC' => 'RC',
            'Stable' => '',

            // Redis
            'beta' => 'B',
            'rc1' => 'RC',
            'rc2' => 'RC',
            'rc3' => 'RC',
            'rc4' => 'RC',
            'rc5' => 'RC',
            'rc6' => 'RC',
            'rc7' => 'RC',
            'rc8' => 'RC',

            '' => '',
        ];
        $type = $types[$this->type];

        return self::part($this->major)
            . '.' . self::part($this->minor)
            . '.' . self::part($this->patch)
            . ($type ? ' ' . $type : '');
    }

    public function format3x(): string
    {
        return str_replace('*', '✔', self::part($this->major))
            . ($this->minor ? '.' . self::part($this->minor) : '')
            . ($this->patch ? '.' . self::part($this->patch) : '');
    }

    private static function part(int|string|bool|null $value): string
    {
        return $value === null ? '*' : ($value === true ? '^' : ($value === false ? '_' : (string) $value));
    }

    /**
     * @param int[][] $familyTree
     * @param Version[][] $available
     */
    public function match(self $that, array $familyTree = [], array $available = []): bool
    {
        if ($this->threadSafe !== null && $that->threadSafe !== null && $this->threadSafe !== $that->threadSafe) {
            return false;
        }

        if ($this->bits !== null && $that->bits !== null && $this->bits !== $that->bits) {
            return false;
        }

        if ($this->build !== null && $that->build !== null && $this->build !== $that->build) {
            return false;
        }

        $major = is_int($this->major) ? $this->major : $that->major;
        $other = is_int($this->major) ? $that->major : $this->major;
        $last = $familyTree ? max(array_keys($familyTree)) : null;
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

    private static function matchVersion(int|string|bool|null $a, int|string|bool|null $b, $latest): bool
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

    /**
     * @return Version[]
     */
    public static function filter(string|bool|float $expr, ?string $default = null): array
    {
        if ($expr === true || $expr === '') {
            $expr = $default ?? '*';
        }
        $parts = explode(',', (string) $expr);
        $versions = [];
        foreach ($parts as $part) {
            $versions[] = self::parseExpression(trim($part));
        }

        return $versions;
    }

}