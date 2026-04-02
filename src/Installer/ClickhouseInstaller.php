<?php

namespace Herd\Installer;

use Herd\Version;
use function end;
use function str_pad;
use function strval;
use const STR_PAD_LEFT;

class ClickhouseInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'ClickHouse';
    public string $dir = 'clickhouse';
    public string $minVersion = '22.1.0';
    public string $versionFormat = 'MM.m';
    public string $portPrefix;

    // metadata
    public string $releaseNotesRe;
    public string $gitTagsRe = '~refs/tags/v(?<major>\d+)\.(?<minor>\d+)\.(?<patch>\d+)\.\d+-(?<type>[a-z]+)$~'; // ignore revision
    public string $gitRepoUrl = 'https://github.com/ClickHouse/ClickHouse.git';

    // docker
    public string $image = 'clickhouse/clickhouse-server';
    public string $containerPrefix = 'clickhouse-';
    public string $volumePrefix = 'clickhouse-data-';
    public string $volumeTarget = '/var/lib/clickhouse';
    /** @var array<int> */
    public array $ports = [9000, 'http' => 8123]; // native protocol, HTTP interface
    /** @var array<string, string> */
    public array $envVars = [];

    public function translatePort(int $port, Version $version): int
    {
        if ($port === 9000) {
            // 25.1 -> 2501
            return (int) (str_pad(strval($version->major), 2, '0', STR_PAD_LEFT) . str_pad(strval($version->minor), 2, '0', STR_PAD_LEFT));
        } else {
            // 25.1 -> 62501
            return (int) ('6' . str_pad(strval($version->major), 2, '0', STR_PAD_LEFT) . str_pad(strval($version->minor), 2, '0', STR_PAD_LEFT));
        }
    }

    /** @override */
    public function dockerVersionKey(Version $version): string
    {
        // Docker Hub tags are "year.month" only, e.g. "25.1"
        return $version->format2();
    }

    /** @override */
    public function familyKey(Version $version): string
    {
        return (string) $version->major;
    }

    /** @override */
    public function versionKey(Version $version): string
    {
        return $version->format2();
    }

    /** @override */
    public function format(Version $version): string
    {
        return $version->format2();
    }

    /** @override */
    public function formatT(Version $version): string
    {
        return $version->format2();
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
