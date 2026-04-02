<?php

namespace Herd\Installer;

use Herd\Version;
use function intval;
use function str_pad;
use function strval;
use const STR_PAD_LEFT;

class DragonflyInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'Dragonfly';
    public string $dir = 'dragonfly';
    public string $minVersion = '0.2.0';
    public string $versionFormat = 'M?.mm.pp';
    public string $portPrefix = '4';

    // metadata
    public string $releaseNotesRe;
    public string $gitTagsRe = '~refs/tags/v(?<major>\d+)\.(?<minor>\d+)\.(?<patch>\d+)$~';
    public string $gitRepoUrl = 'https://github.com/dragonflydb/dragonfly.git';

    // docker
    public string $image = 'docker.dragonflydb.io/dragonflydb/dragonfly';
    public string $containerPrefix = 'dragonfly-';
    public string $volumePrefix = 'dragonfly-data-';
    public string $volumeTarget = '/data';
    public string $runCommand = '--dir /data';
    /** @var array<int> */
    public array $ports = [6379];
    /** @var array<string, string> */
    public array $envVars;

    public function translatePort(int $port, Version $version): int
    {
        // 1.24.8  -> 41348
        // 1.28.30 -> 42830 shortened

        $major = $version->major;
        if ($version->patch > 9) {
            $major = '';
        }

        return intval('4' . $major . str_pad(strval($version->minor), 2, '0', STR_PAD_LEFT) . $version->patch);
    }

    public function dockerVersionKey(Version $version): string
    {
        return 'v' . $version->format3();
    }

}
