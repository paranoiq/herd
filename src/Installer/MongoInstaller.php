<?php

namespace Herd\Installer;

use Herd\Version;
use function intval;
use function str_pad;
use function strval;
use const STR_PAD_LEFT;

class MongoInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'MongoDB';
    public string $dir = 'mongo';
    public string $minVersion = '3.0.0';
    public string $versionFormat = 'M.m.pp';
    public string $portPrefix = '2';

    // metadata
    public string $releaseNotesRe;
    public string $gitTagsRe = '~refs/tags/r(?<major>\d+)\.(?<minor>\d+)\.(?<patch>\d+)$~';
    public string $gitRepoUrl = 'https://github.com/mongodb/mongo.git';

    // docker
    public string $image = 'mongo';
    public string $containerPrefix = 'mongo-';
    public string $volumePrefix = 'mongo-data-';
    public string $volumeTarget = '/data/db';
    public string $runCommand;
    /** @var array<int> */
    public array $ports = [27017];
    /** @var array<string, string> */
    public array $envVars;

    public function translatePort(int $port, Version $version): int
    {
        // 8.0.19 -> 28019

        return intval('2' . $version->major . $version->minor . str_pad(strval($version->patch), 2, '0', STR_PAD_LEFT));
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = [
            "new" => "https://www.mongodb.com/docs/manual/release-notes/",
        ];
    }

}
