<?php

namespace Herd\Installer;

use Herd\Version;
use function explode;
use function str_pad;
use function version_compare;
use const STR_PAD_LEFT;

class TimescaleInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'TimescaleDB';
    public string $dir = 'timescale';
    public string $minVersion = '2.0.0';

    // metadata
    public string $releaseNotesRe = '~match-nothing~';

    // docker
    public string $image = 'timescale/timescaledb';
    public string $containerPrefix = 'timescale-';
    public string $volumePrefix = 'timescale-data-';
    public string $volumeTarget = '/var/lib/postgresql/data';
    public array $ports = [5432];
    public array $envVars = ['POSTGRES_PASSWORD' => 'root'];
    public array $pgVersions = ['pg15', 'pg16', 'pg17', 'pg18'];

    public function translatePort(int $port, Version $version): int
    {
        // 2.17.0-pg18 -> 42170

        $prefixes = ['pg15' => '1', 'pg16' => '2', 'pg17' => '3', 'pg18' => '4'];
        $prefix = $prefixes[$version->build];

        return $prefix . $version->major . str_pad($version->minor, 2, '0', STR_PAD_LEFT) . $version->patch;
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = [];

        if (exec('git ls-remote --tags https://github.com/timescale/timescaledb.git', $output, $resultCode) !== false && $resultCode === 0) {
            foreach ($output as $row) {
                // take only GA release versions (e.g. 2.17.0, no rc/alpha/beta)
                if (preg_match('~refs/tags/(\d+\.\d+\.\d+)$~', $row, $matches)) {
                    [$major, $minor, $patch] = explode('.', $matches[1]);
                    foreach ($this->pgVersions as $pgVersion) {
                        $version = new Version((int) $major, (int) $minor, (int) $patch, null, null, $pgVersion, null, null, $this->fancyName);
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