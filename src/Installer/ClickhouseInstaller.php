<?php

namespace Herd\Installer;

use Herd\Version;
use function end;
use function str_pad;
use function version_compare;
use const STR_PAD_LEFT;

class ClickhouseInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'ClickHouse';
    public string $dir = 'clickhouse';
    public string $minVersion = '22.1.0';

    // metadata - matches e.g. "ClickHouse release 25.1, 2025-01-15"
    public string $releaseNotesRe = '~ClickHouse release (?P<version>\d+\.\d+), (?P<date>\d{4}-\d{2}-\d{2})~i';

    // docker
    public string $image = 'clickhouse/clickhouse-server';
    public string $containerPrefix = 'clickhouse-';
    public string $volumePrefix = 'clickhouse-data-';
    public string $volumeTarget = '/var/lib/clickhouse';
    public array $ports = [8123, 9000]; // HTTP interface, native protocol
    public array $envVars = [];

    public function translatePort(int $port, Version $version): int
    {
        if ($port === 9000) {
            // 25.1 -> 2501
            return (int) (str_pad($version->major, 2, '0', STR_PAD_LEFT) . str_pad($version->minor, 2, '0', STR_PAD_LEFT));
        } else {
            // 25.1 -> 62501
            return (int) ('6' . str_pad($version->major, 2, '0', STR_PAD_LEFT) . str_pad($version->minor, 2, '0', STR_PAD_LEFT));
        }
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = [];

        if (exec('git ls-remote --tags https://github.com/ClickHouse/ClickHouse.git', $output, $resultCode) !== false && $resultCode === 0) {
            foreach ($output as $row) {
                // take only stable GA releases, e.g. refs/tags/v25.1.2.1-stable
                if (preg_match('~refs/tags/v(\d+)\.(\d+)\.\d+\.\d+-stable$~', $row, $matches)) {
                    $version = new Version((int) $matches[1], (int) $matches[2], 0, null, null, null, null, null, $this->fancyName);
                    $vk = $this->versionKey($version);
                    if (!isset($this->minVersion) || version_compare($vk, $this->minVersion) >= 0) {
                        $this->remote[$this->familyKey($version)][$vk] = $version;
                    }
                }
            }
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
