<?php

namespace Herd\Installer;

use Dogma\Re;
use Dogma\Time\Date;
use Herd\Version;
use function end;
use function intval;
use function str_pad;
use function strval;
use const PREG_SET_ORDER;
use const STR_PAD_LEFT;

class TrinoInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'Trino';
    public string $dir = 'trino';
    public string $minVersion = '0.300';
    public string $versionFormat = 'M!.mmm.p';
    public string $portPrefix = '';

    // metadata
    public string $releaseNotesRe = '~release-(?P<version>\d+(?:\.\d+(?:\.\d+)?)?)\.html~i';

    // docker
    public string $image = 'trinodb/trino';
    public string $containerPrefix = 'trino-';
    public string $volumePrefix = 'trino-data-';
    public string $volumeTarget = '/opt/presto-server/etc/catalog';
    /** @var array<int> */
    public array $ports = [8080];
    /** @var array<string, string> */
    public array $envVars = [];

    public function translatePort(int $port, Version $version): int
    {
        // 479 -> 4790

        return intval(str_pad(strval($version->minor), 3, '0', STR_PAD_LEFT) . ($version->patch ?: 0));
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = [
            "all" => "https://trino.io/docs/current/release.html",
        ];
    }

    /**
     * @override
     * @return array<string, Version>
     */
    public function getAvailableVersions(string $html): array
    {
        $versions = [];
        foreach (Re::matchAll($html, $this->releaseNotesRe, PREG_SET_ORDER) as $match) {
            $version = Version::parseRelease($match[0], $this->releaseNotesRe, $this->fancyName);
            if ($version === null) {
                continue;
            }
            if ($version->major > 100) {
                $version->minor = $version->major;
                $version->major = 0;
            }
            if ($version->date === null && isset($this->releaseDates[$this->versionKey($version)])) {
                $version->date = new Date($this->releaseDates[$this->versionKey($version)]);
            }
            $versions[$this->versionKey($version)] = $version;
        }

        return $versions;
    }

    /** @override */
    public function familyKey(Version $version): string
    {
        return strval($version->major);
    }

    /** @override */
    public function versionKey(Version $version): string
    {
        return $version->patch !== null ? $version->format3() : $version->format2();
    }

    /** @override */
    public function dockerVersionKey(Version $version): string
    {
        if ($version->minor > 300) {
            return (string) $version->minor;
        }
        return $version->patch !== null ? $version->format3() : $version->format2();
    }

    /** @override */
    public function format(Version $version): string
    {
        if ($version->minor >= 300) {
            return (string) $version->minor;
        }
        return $version->patch !== null ? $version->format3() : $version->format2();
    }

    /** @override */
    public function formatT(Version $version): string
    {
        if ($version->minor >= 300) {
            return (string) $version->minor;
        }
        return $version->patch !== null ? $version->format3() : $version->format2();
    }

    public function getLatest(Version $family): int|string|null
    {
        return isset($this->remote[$this->familyKey($family)])
            ? end($this->remote[$this->familyKey($family)])->minor
            : null;
    }

    public function createLatest(Version $version, int|string $last): Version
    {
        return $version->setMinor($last);
    }

}
