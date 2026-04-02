<?php

namespace Herd\Installer;

use Herd\Version;
use function intval;
use function str_pad;
use function strval;
use const STR_PAD_LEFT;

class CockroachInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'CockroachDB';
    public string $dir = 'cockroach';
    public string $minVersion = '21.2.0';
    public string $versionFormat = 'MM.m.pp';
    public string $portPrefix;

    // metadata
    public string $releaseNotesRe;
    public string $gitTagsRe = '~refs/tags/v(?<major>\d+)\.(?<minor>\d+)\.(?<patch>\d+)$~';
    public string $gitRepoUrl = 'https://github.com/cockroachdb/cockroach.git';

    // docker
    public string $image = 'cockroachdb/cockroach';
    public string $containerPrefix = 'cockroach-';
    public string $volumePrefix = 'cockroach-data-';
    public string $volumeTarget = '/cockroach/cockroach-data';
    public string $runCommand = 'start-single-node --insecure';
    /** @var array<int> */
    public array $ports = [26257, 'admin' => 8080]; // SQL, admin
    /** @var array<string, string> */
    public array $envVars;

    public function translatePort(int $port, Version $version): int
    {
        if ($port === 26257) {
            // SQL
            // 26.1.0  -> 26100
            // 25.2.13 -> 25213

            return intval($version->major . $version->minor . str_pad(strval($version->patch), 2, '0', STR_PAD_LEFT));
        } else {
            // Admin UI
            // 26.1.0  -> 36100
            // 25.2.13 -> 35213

            return intval(($version->major + 10) . $version->minor . str_pad(strval($version->patch), 2, '0', STR_PAD_LEFT));
        }
    }

    public function dockerVersionKey(Version $version): string
    {
        return 'v' . $version->format3();
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = [
            "new" => "https://www.cockroachlabs.com/docs/releases/",
        ];
    }

}
