<?php declare(strict_types = 1);

namespace Zoo;

use Dogma\Comparable;
use Dogma\Equalable;
use Dogma\ShouldNotHappenException;
use Dogma\Str;
use Dogma\StrictBehaviorMixin;
use RuntimeException;
use function array_keys;
use function array_pad;
use function is_int;
use function is_numeric;
use function is_string;

class Version implements Comparable, Equalable
{
    use StrictBehaviorMixin;

    public function __construct(
        public int|bool|null $major,
        public int|bool|null $minor,
        public int|string|bool|null $patch,
        public ?bool $safe,
        public ?int $bits
    ) {}

    public static function parseUrl(string $url): self
    {
        $match = Str::match($url, '~php-([0-9]+)(?:\\.([0-9]+))?(?:\\.([0-9]+(?:RC[0-9]+)?))?-?(nts)?.*(x64|x86|Win32)~i');
        [, $major, $minor, $patch, $nts, $bits] = $match;

        return new self(
            self::parseVer($major),
            self::parseVer($minor),
            self::parseVer($patch),
            $nts !== 'nts',
            $bits === 'x86' || $bits === 'Win32' || $bits === 'win32' ? 32 : 64,
        );
    }

    public static function parseDir(string $dir): self
    {
        $match = Str::match($dir, '~([0-9]+)(?:\\.([0-9]+))?(?:\\.([0-9]+(?:RC[0-9]+)?))?-?(ts)?-?(32)?~');
        if (!$match) {
            throw new ShouldNotHappenException('Wrong directory');
        }
        $match = array_pad($match, 6, null);
        [, $major, $minor, $patch, $ts, $bits] = $match;

        return new self(
            self::parseVer($major),
            self::parseVer($minor),
            self::parseVer($patch),
            $ts === 'ts',
            $bits === '32' ? 32 : 64,
        );
    }

    public static function parseExp(string|bool|float $expression, ?Version $platform = null): self
    {
        if ($expression === true) {
            $expression = '*';
        }
        $expression = (string) $expression;

        $match = Str::match($expression, '~([0-9]+?|[*^_])(?:\\.?([0-9]+?|[*^_]))?(?:\\.?([0-9]+?(?:RC[0-9]+)?|[*^_]))?-?(nts|ts|\\*)?-?(32|64|\\*)?$~');
        if (!$match) {
            throw new RuntimeException('Invalid version expression.');
        }
        $match = array_pad($match, 6, null);
        [, $major, $minor, $patch, $ts, $bits] = $match;

        $ts = $platform !== null ? ($platform->safe ? 'ts' : 'nts') : $ts;
        $bits = $platform !== null ? $platform->bits : $bits;

        return new self(
            self::parseVer($major),
            self::parseVer($minor),
            self::parseVer($patch),
            ($ts === '' || $ts === null) ? false : ($ts === '*' ? null : $ts === 'ts'),
            ($bits === '' || $bits === null) ? 64 : ($bits === '*' ? null : (int) $bits),
        );
    }

    private static function parseVer(?string $value): int|string|bool|null
    {
        if ($value === null || $value === '' || $value === '*') {
            return null;
        }
        if ($value === '^') {
            return true;
        }
        if ($value === '_') {
            return false;
        }
        return is_numeric($value) ? (int) $value : $value;
    }

    public function setPatch(int|string $patch): self
    {
        return new self($this->major, $this->minor, $patch, $this->safe, $this->bits);
    }

    public function unsetPatch(): self
    {
        return new self($this->major, $this->minor, null, $this->safe, $this->bits);
    }

    public function unsetMinor(): self
    {
        return new self($this->major, null, null, $this->safe, $this->bits);
    }

    public function unsetMajor(): self
    {
        return new self(null, null, null, $this->safe, $this->bits);
    }

    public function getFamily(): self
    {
        return new self($this->major, $this->minor, null, $this->safe, $this->bits);
    }

    // -----------------------------------------------------------------------------------------------------------------

    /**
     * @param self $other
     * @return bool
     */
    public function equals(Equalable $other): bool
    {
        return $this->format() === $other->format();
    }

    /**
     * @param self $other
     * @return bool
     */
    public function equalsWithoutVariant(Equalable $other): bool
    {
        return $this->major === $other->major && $this->minor === $other->minor && $this->patch === $other->patch;
    }

    /**
     * @param self $other
     * @return int
     */
    public function compare(Comparable $other): int
    {
        return $this->major <=> $other->major
            ?: $this->minor <=> $other->minor
            ?: is_string($other->patch) <=> is_string($this->patch) // "0RC5" < 0
            ?: $this->patch <=> $other->patch
            ?: $this->safe <=> $other->safe
            ?: $other->bits <=> $this->bits;
    }

    public function format(): string
    {
        return self::formatVer($this->major)
            . '.' . self::formatVer($this->minor)
            . '.' . self::formatVer($this->patch)
            . ($this->safe === null ? '-*' : ($this->safe ? '-ts' : ''))
            . ($this->bits === null ? '-*' : ($this->bits === 32 ? '-32' : ''));
    }

    public function formatShort(): string
    {
        return str_replace('*', '✔', self::formatVer($this->major))
            . ($this->minor ? '.' . self::formatVer($this->minor) : '')
            . ($this->patch ? '.' . self::formatVer($this->patch) : '');
    }

    public function family(): string
    {
        return self::formatVer($this->major)
            . '.' . self::formatVer($this->minor)
            . '.*'
            . ($this->safe === null ? '-*' : ($this->safe ? '-ts' : ''))
            . ($this->bits === null ? '-*' : ($this->bits === 32 ? '-32' : ''));
    }

    public function familySafe(): string
    {
        return self::formatVer($this->major)
            . '.' . self::formatVer($this->minor)
            . ($this->safe === null ? '' : ($this->safe ? '-ts' : ''))
            . ($this->bits === null ? '' : ($this->bits === 32 ? '-32' : ''));
    }

    /**
     * @param int|bool|null $value
     * @return string
     */
    private static function formatVer(int|string|bool|null $value): string
    {
        return $value === null ? '*' : ($value === true ? '^' : ($value === false ? '_' : (string) $value));
    }

    /**
     * @param Version $that
     * @param int[][] $families
     * @param Version[][][] $remote
     * @return bool
     */
    public function match(self $that, array $families = [], array $remote = []): bool
    {
        if ($this->safe !== null && $that->safe !== null && $this->safe !== $that->safe) {
            return false;
        }

        if ($this->bits !== null && $that->bits !== null && $this->bits !== $that->bits) {
            return false;
        }

        $major = is_int($this->major) ? $this->major : $that->major;
        $other = is_int($this->major) ? $that->major : $this->major;
        $last = $families ? max(array_keys($families)) : null;
        if (!self::matchVer($major, $other, $last)) {
            return false;
        }

        $minor = is_int($this->minor) ? $this->minor : $that->minor;
        $other = is_int($this->minor) ? $that->minor : $this->minor;
        $last = isset($families[$major]) ? max(array_keys($families[$major])) : null;
        if (!self::matchVer($minor, $other, $last)) {
            return false;
        }

        $patch = is_int($this->patch) ? $this->patch : $that->patch;
        $other = is_int($this->patch) ? $that->patch : $this->patch;
        $family = "$major.$minor.*-ts-32"; // -ts-32 was available in all versions
        if (isset($remote[$family])) {
            $versions = $remote[$family];
            $last = end($versions)->patch;
        } else {
            $last = null;
        }
        if (!self::matchVer($patch, $other, $last)) {
            return false;
        }

        return true;
    }

    private static function matchVer(int|string|bool|null $a, int|string|bool|null $b, ?int $latest): bool
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

}