<?php

namespace Herd\Installer;

use Herd\Version;
use function intval;
use function str_pad;
use function strval;
use const STR_PAD_LEFT;

class InfluxInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'InfluxDB';
    public string $dir = 'influx';
    public string $minVersion = '2.0.0';
    public string $versionFormat = 'M.m.pp';
    public string $portPrefix = '6';

    // metadata
    public string $releaseNotesRe;
    public string $gitTagsRe = '~refs/tags/v(?<major>\d+)\.(?<minor>\d+)\.(?<patch>\d+)$~';
    public string $gitRepoUrl = 'https://github.com/influxdata/influxdb.git';

    // docker
    public string $image = 'influxdb';
    public string $containerPrefix = 'influx-';
    public string $volumePrefix = 'influx-data-';
    public string $volumeTarget = '/var/lib/influxdb2';
    /** @var array<int> */
    public array $ports = [8086];
    /** @var array<string, string> */
    public array $envVars = [];

    public function translatePort(int $port, Version $version): int
    {
        // 2.7.12 -> 62712

        return intval('6' . $version->major . $version->minor . str_pad(strval($version->patch), 2, '0', STR_PAD_LEFT));
    }

    /** @override */
    public function dockerVersionKey(Version $version): string
    {
        return $version->format3() . '-core';
    }

}
