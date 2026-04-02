<?php

namespace Herd\Installer;

use Herd\Version;
use function intval;
use function str_pad;
use function strval;
use const STR_PAD_LEFT;

class MariaInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'MariaDB';
    public string $dir = 'maria';
    public string $minVersion = '10.0.0';
    public string $versionFormat = 'MM.m.pp'; // sometimes MM.mm.pp
    public string $portPrefix;

    // metadata
    public string $releaseNotesRe = '~>(?P<version>\d+\.\d+\.\d+)</a></td><td>(?P<date>\d+.\d+.\d+)</td><td>(?P<type>[^<]+)</td>~i';

    // docker
    public string $image = 'mariadb';
    public string $containerPrefix = 'mariadb-';
    public string $volumePrefix = 'mariadb-data-';
    public string $volumeTarget = '/var/lib/mysql';
    /** @var array<int> */
    public array $ports = [3306];
    /** @var array<string, string> */
    public array $envVars = ['MYSQL_ROOT_PASSWORD' => 'root'];

    public function translatePort(int $port, Version $version): int
    {
        // 5.5.64  -> 15564
        // 10.9.8  -> 10908
        // 10.11.9 -> 11109 shortened

        $major = $version->major;
        $minor = $version->minor;
        $patch = $version->patch;

        if ($major === 5) {
            $major = 15;
        }
        if ($major === 10 && $minor > 9) {
            $major = 2;
        }

        return intval($major . $minor . str_pad(strval($patch), 2, '0', STR_PAD_LEFT));
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = ['all' => 'https://mariadb.org/mariadb/all-releases/'];
    }

}