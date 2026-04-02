<?php

namespace Herd;

use Dogma\Comparable;
use Dogma\Re;
use Dogma\Time\Date;
use function intval;
use function is_string;
use function str_replace;

/**
 * Version: major.minor[.patch][.revision][alpha|beta|RC...][-ts|nts][-platform][-build] [date] [app]
 */
class Version implements Comparable
{

    /**
     * Call Version::newXYZ()
     */
    private function __construct(
        public int $major,
        public int $minor,
        public int|null $patch = null,
        public int|null $revision = null,
        public ?string $build = null,
        public ?string $type = null,
        public ?bool $threadSafe = null,
        public ?string $platform = null,
        public ?Date $date = null,
        public ?string $app = null,
    ) {}

    public static function new3(
        int $major,
        int|null $minor,
        int|null $patch,
        ?string $build = null,
        ?string $type = null,
        ?Date $date = null,
        ?string $app = null
    ): self
    {
        return new self($major, $minor, $patch, null, $build, $type, null, null, $date, $app);
    }

    public static function new2(
        int $major,
        int|null $minor,
        ?string $build = null,
        ?string $type = null,
        ?Date $date = null,
        ?string $app = null
    ): self
    {
        return new self($major, $minor, null, null, $build, $type, null, null, $date, $app);
    }

    public static function newPhp(
        int $major, // 8
        int|null $minor, // 1
        int|null $patch = null, // 0
        ?string $build = null, // vc15|vs16|vs17
        ?string $type = null, // alpha1, beta2, RC3...
        ?bool $threadSafe = null, // ts|nts
        ?string $platform = null, // 32|64
        ?Date $date = null
    ): self
    {
        return new self($major, $minor, $patch, null, $build, $type, $threadSafe, $platform, $date, 'PHP');
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'major' => $this->major,
            'minor' => $this->minor,
            'patch' => $this->patch,
            'revision' => $this->revision,
            'build' => $this->build,
            'type' => $this->type,
            'threadSafe' => $this->threadSafe,
            'platform' => $this->platform,
            'date' => $this->date?->format('Y-m-d'),
            'app' => $this->app,
        ];
    }

    /**
     * @param array<mixed> $data
     */
    public static function jsonUnserialize(array $data): self
    {
        $data['date'] = $data['date'] !== null ? new Date($data['date']) : null;

        return new self(
            $data['major'],
            $data['minor'],
            $data['patch'],
            $data['revision'],
            $data['build'],
            $data['type'],
            $data['threadSafe'],
            $data['platform'],
            $data['date'],
            $data['app'],
        );
    }

    /**
     * Variable $re contains expression with annotated groups (?<name>...) with keys:
     * - "major" - integer
     * - "minor" - integer
     * - "patch" - integer, optional
     * - "revision" - integer, optional
     * - "build" - text, optional (compiler, variant...)
     * - "type" - text, optional (alpha, beta, rc, lts...)
     * - "threadSafe" - text, optional (ts, nts)
     * - "platform" - text, optional (32, 64, x86, arm...)
     * - "date" - text, optional
     * - "app" - text, optional
     */
    public static function parseRelease(string $name, string $re, ?string $app = null): ?self
    {
        $m = Re::match($name, $re);
        if ($m === null) {
            return null;
        }

        return new self(
            intval($m['major']),
            intval($m['minor']),
            isset($m['patch']) ? intval($m['patch']) : null,
            isset($m['revision']) ? intval($m['revision']) : null,
            $m['build'] ?? null,
            $m['type'] ?? null,
            isset($m['threadSafe']) ? $m['threadSafe'] === 'ts' : null,
            $m['platform'] ?? null,
            isset($m['date']) ? new Date($m['date']) : null,
            $app ?? $m['app'] ?? null,
        );
    }

    /** @deprecated should solve PhpInstaller */
    public function setPatch(int|string $patch): self
    {
        return self::newPhp($this->major, $this->minor, $patch, null, null, $this->threadSafe, $this->platform);
    }

    /** @deprecated should solve PhpInstaller */
    public function unsetPatch(): self
    {
        return self::newPhp($this->major, $this->minor, null, null, null, $this->threadSafe, $this->platform);
    }

    /** @deprecated should solve PhpInstaller */
    public function setMinor(int|string $minor): self
    {
        return self::newPhp($this->major, $minor, null, null, null, $this->threadSafe, $this->platform);
    }

    /** @deprecated should solve PhpInstaller */
    public function unsetMinor(): self
    {
        return self::newPhp($this->major, null, null, null, null, $this->threadSafe, $this->platform);
    }

    /** @deprecated should solve PhpInstaller */
    public function unsetMajor(): self
    {
        return self::newPhp($this->major, null, null, null, null, $this->threadSafe, $this->platform);
    }

    /** @deprecated should solve PhpInstaller */
    public function getFamily(): self
    {
        return self::newPhp($this->major, $this->minor, null, null, null, $this->threadSafe, $this->platform);
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
    public function compare(Comparable $other): int // @phpstan-ignore method.childParameterType
    {
        return $this->major <=> $other->major
            ?: $this->minor <=> $other->minor
            ?: $other->patch <=> $this->patch
            ?: $other->revision <=> $this->revision
            ?: $this->type <=> $other->type
            ?: $this->threadSafe <=> $other->threadSafe
            ?: $other->platform <=> $this->platform
            ?: $other->build <=> $this->build;
    }

    public function formatFamily(): string
    {
        return $this->major . '.' . $this->minor . '.*'
            . ($this->threadSafe === null ? '-*' : ($this->threadSafe ? '-ts' : ''))
            . ($this->platform === null ? '-*' : ($this->platform === '32' ? '-32' : ''));
    }

    public function formatFamilyPath(): string
    {
        return $this->major . '.' . $this->minor
            . ($this->threadSafe === null ? '' : ($this->threadSafe ? '-ts' : ''))
            . ($this->platform === null ? '' : ($this->platform === '32' ? '-32' : ''));
    }

    public function format6(): string
    {
        return $this->major . '.' . $this->minor . '.' . $this->patch
            . ($this->threadSafe === null ? '-*' : ($this->threadSafe ? '-ts' : ''))
            . ($this->platform === null ? '-*' : ($this->platform === '32' ? '-32' : ''))
            . ($this->build === null ? '-*' : '-' . $this->build);
    }

    public function format3(): string
    {
        return $this->major . '.' . $this->minor . '.' . $this->patch;
    }

    public function format2(): string
    {
        return $this->major . '.' . $this->minor;
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

        return $this->major . '.' . $this->minor . '.' . $this->patch . ($type ? ' ' . $type : '');
    }

    public function format3x(): string
    {
        return str_replace('*', '✔', strval($this->major))
            . ($this->minor !== null ? '.' . $this->minor : '')
            . ($this->patch !== null ? '.' . $this->patch : '');
    }

}