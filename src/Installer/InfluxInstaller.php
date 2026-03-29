<?php

namespace Herd\Installer;

use Herd\Version;
use function explode;
use function str_pad;
use function version_compare;
use const STR_PAD_LEFT;

class InfluxInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'InfluxDB';
    public string $dir = 'influx';
    public string $minVersion = '2.0.0';

    // metadata
    public string $releaseNotesRe = '~match-nothing~';

    // docker
    public string $image = 'influxdb';
    public string $containerPrefix = 'influx-';
    public string $volumePrefix = 'influx-data-';
    public string $volumeTarget = '/var/lib/influxdb2';
    public array $ports = [8086];
    public array $envVars = [];

    public function translatePort(int $port, Version $version): int
    {
        // 2.7.1 -> 62071

        return '6' . $version->major . str_pad($version->minor, 2, '0', STR_PAD_LEFT) . $version->patch;
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = [];

        if (exec('git ls-remote --tags https://github.com/influxdata/influxdb.git', $output, $resultCode) !== false && $resultCode === 0) {
            foreach ($output as $row) {
                // take only GA release versions (e.g. v2.7.1, no rc/alpha/beta)
                if (preg_match('~refs/tags/v(\d+\.\d+\.\d+)$~', $row, $matches)) {
                    [$major, $minor, $patch] = explode('.', $matches[1]);
                    $version = new Version((int) $major, (int) $minor, (int) $patch, null, null, null, null, null, $this->fancyName);
                    if (!isset($this->minVersion) || version_compare($this->versionKey($version), $this->minVersion) >= 0) {
                        $this->remote[$this->familyKey($version)][$this->versionKey($version)] = $version;
                    }
                }
            }
        }
    }

    /** @override */
    public function dockerVersionKey(Version $version): string
    {
        return $version->format3() . '-core';
    }

}
