<?php

namespace Herd\Installer;

use Herd\Version;
use function str_pad;
use function version_compare;
use const STR_PAD_LEFT;

class MemcachedInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'Memcached';
    public string $dir = 'memcached';
    public string $minVersion = '1.4.0';

    // metadata
    public string $releaseNotesRe = '~(?P<version>\d+\.\d+\.\d+) \((?P<date>[^)]+)\)~';

    // docker
    public string $image = 'memcached';
    public string $containerPrefix = 'memcached-';
    public string $volumePrefix = 'memcached-data-';
    public string $volumeTarget = '/data';
    public string $runCommand;
    public array $ports = [11211];
    public array $envVars;

    public function translatePort(int $port, Version $version): int
    {
        // 1.6.40 -> 11640

        return '1' . $version->major . $version->minor . str_pad($version->patch, 2, '0', STR_PAD_LEFT);
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = [
            "all" => "https://github.com/memcached/memcached/wiki/ReleaseNotes",
        ];
    }

}
