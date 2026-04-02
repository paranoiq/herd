<?php

namespace Herd\Installer;

use Herd\Version;
use function intval;
use function str_pad;
use const STR_PAD_LEFT;

class CassandraInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'Cassandra';
    public string $dir = 'cassandra';
    public string $minVersion = '2.1.0';
    public string $versionFormat = 'M.mm.pp';
    public string $portPrefix = '5';

    // metadata
    public string $releaseNotesRe = '~>(?P<version>\d+\.\d+\.\d+)/</a>\s+(?P<date>\d+-\d+-\d+)~';

    // docker
    public string $image = 'cassandra';
    public string $containerPrefix = 'cassandra-';
    public string $volumePrefix = 'cassandra-data-';
    public string $volumeTarget = '/var/lib/cassandra';
    public string $runCommand;
    /** @var array<int> */
    public array $ports = [9042];
    /** @var array<string, string> */
    public array $envVars;

    public function translatePort(int $port, Version $version): int
    {
        // 4.0.19  -> 54019
        // 3.11.19 -> 53919
        $minor = $version->minor === 11 ? 9 : $version->minor;

        return intval('5' . $version->major . $minor . str_pad(strval($version->patch), 2, '0', STR_PAD_LEFT));
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = [
            "all" => "https://archive.apache.org/dist/cassandra/",
        ];
    }

}
