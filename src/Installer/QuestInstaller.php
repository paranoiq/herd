<?php

namespace Herd\Installer;

use Herd\Version;
use function intval;
use function str_pad;
use function strval;
use const STR_PAD_LEFT;

class QuestInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'QuestDB';
    public string $dir = 'quest';
    public string $minVersion = '6.0.0';
    public string $versionFormat = 'M.m.pp';
    public string $portPrefix;

    // metadata
    public string $releaseNotesRe;
    public string $gitTagsRe = '~refs/tags/(?<major>\d+)\.(?<minor>\d+)\.(?<patch>\d+)$~';
    public string $gitRepoUrl = 'https://github.com/questdb/questdb.git';

    // docker
    public string $image = 'questdb/questdb';
    public string $containerPrefix = 'quest-';
    public string $volumePrefix = 'quest-data-';
    public string $volumeTarget = '/root/.questdb';
    /** @var array<int> */
    public array $ports = [8812, 'http' => 9000]; // PostgreSQL wire, HTTP REST / web console
    /** @var array<string, string> */
    public array $envVars = [];

    public function translatePort(int $port, Version $version): int
    {
        if ($port === 8812) {
            // PostgreSQL wire protocol
            // 8.2.1 -> 18201
            return intval('1' . $version->major . $version->minor . str_pad(strval($version->patch), 2, '0', STR_PAD_LEFT));
        } else { // 9000
            // HTTP REST API / web console
            // 8.2.1 -> 38201
            return intval('3' . $version->major . $version->minor . str_pad(strval($version->patch), 2, '0', STR_PAD_LEFT));
        }
    }

}
