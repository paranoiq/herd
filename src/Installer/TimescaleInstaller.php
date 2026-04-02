<?php

namespace Herd\Installer;

use Dogma\Parse;
use Herd\Version;
use function intval;
use function str_pad;
use function strval;
use function version_compare;
use const STR_PAD_LEFT;

class TimescaleInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'TimescaleDB';
    public string $dir = 'timescale';
    public string $minVersion = '2.0.0';
    public string $versionFormat = 'M.mm.p-AANN';
    public string $portPrefix;

    // metadata
    public string $releaseNotesRe = '~match-nothing~';

    // docker
    public string $image = 'timescale/timescaledb';
    public string $containerPrefix = 'timescale-';
    public string $volumePrefix = 'timescale-data-';
    public string $volumeTarget = '/var/lib/postgresql/data';
    /** @var array<int> */
    public array $ports = [5432];
    /** @var array<string, string> */
    public array $envVars = ['POSTGRES_PASSWORD' => 'root'];
    /** @var array<string, string> */
    public array $pgVersions = ['pg15' => '1', 'pg16' => '2', 'pg17' => '3', 'pg18' => '4']; // (version => portPrefix)

    public function translatePort(int $port, Version $version): int
    {
        // 2.17.0-pg18 -> 42170

        $prefix = $this->pgVersions[$version->build];

        return intval($prefix . $version->major . str_pad(strval($version->minor), 2, '0', STR_PAD_LEFT) . $version->patch);
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = [];

        if (exec('git ls-remote --tags https://github.com/timescale/timescaledb.git', $output, $resultCode) !== false && $resultCode === 0) {
            foreach ($output as $row) {
                // take only GA release versions (e.g. 2.17.0, no rc/alpha/beta)
                if (preg_match('~refs/tags/(\d+\.\d+\.\d+)$~', $row, $matches)) {
                    [$major, $minor, $patch] = Parse::ints($matches[1], '.');
                    foreach ($this->pgVersions as $pgVersion => $portPrefix) {
                        $version = Version::new3($major, $minor, $patch, $pgVersion, null, null, $this->fancyName);
                        if (!isset($this->minVersion) || version_compare($this->versionKey($version), $this->minVersion) >= 0) {
                            $this->remote[$this->familyKey($version)][$this->versionKey($version)] = $version;
                        }
                    }
                }
            }
        }
    }

    /** @override */
    public function familyKey(Version $version): string
    {
        return $version->format2() . '-' . $version->build;
    }

    /** @override */
    public function versionKey(Version $version): string
    {
        return $version->format3() . '-' . $version->build;
    }

    /** @override */
    public function dockerVersionKey(Version $version): string
    {
        return $version->format3() . '-' . $version->build;
    }

    /** @override */
    public function format(Version $version): string
    {
        return $version->format3() . '-' . $version->build;
    }

    /** @override */
    public function formatT(Version $version): string
    {
        return $version->format3t() . '-' . $version->build;
    }

}