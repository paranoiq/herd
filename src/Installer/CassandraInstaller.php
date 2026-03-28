<?php

namespace Herd\Installer;

use Herd\Version;
use function str_pad;
use function version_compare;
use const STR_PAD_LEFT;

class CassandraInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'Cassandra';
    public string $dir = 'cassandra';
    public string $minVersion = '2.1.0';

    // metadata
    public string $releaseNotesRe = '~>(?P<version>\d+\.\d+\.\d+)/</a>\s+(?P<date>\d+-\d+-\d+)~';

    // docker
    public string $image = 'cassandra';
    public string $containerPrefix = 'cassandra-';
    public string $volumePrefix = 'cassandra-data-';
    public string $volumeTarget = '/var/lib/cassandra';
    public string $runCommand;
    public array $ports = [9042];
    public array $envVars;

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = [
            "all" => "https://archive.apache.org/dist/cassandra/",
        ];
    }

    public function translatePort(int $port, Version $version): int
    {
        // 4.0.19 -> 24019

        return '2' . $version->major . $version->minor . str_pad($version->patch, 2, '0', STR_PAD_LEFT);
    }

}
