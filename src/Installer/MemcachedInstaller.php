<?php

namespace Herd\Installer;

use Herd\Version;
use function intval;
use function str_pad;
use function strval;
use const STR_PAD_LEFT;

class MemcachedInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'Memcached';
    public string $dir = 'memcached';
    public string $minVersion = '1.4.0';
    public string $versionFormat = 'M.m.pp';
    public string $portPrefix = '1';

    // metadata
    public string $releaseNotesRe = '~(?P<version>\d+\.\d+\.\d+) \((?P<date>[^)]+)\)~';

    // docker
    public string $image = 'memcached';
    public string $containerPrefix = 'memcached-';
    public string $volumePrefix = 'memcached-data-';
    public string $volumeTarget = '/data';
    public string $runCommand;
    /** @var array<int> */
    public array $ports = [11211];
    /** @var array<string, string> */
    public array $envVars;

    public function translatePort(int $port, Version $version): int
    {
        // 1.6.40 -> 11640

        return intval('1' . $version->major . $version->minor . str_pad(strval($version->patch), 2, '0', STR_PAD_LEFT));
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = [
            "all" => "https://github.com/memcached/memcached/wiki/ReleaseNotes",
        ];
    }

}
