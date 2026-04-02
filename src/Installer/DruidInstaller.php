<?php

namespace Herd\Installer;

use Herd\Version;
use function intval;

class DruidInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'Druid';
    public string $dir = 'druid';
    public string $minVersion = '24.0.0';
    public string $versionFormat = 'MM.m!.p';
    public string $portPrefix = '';

    // metadata
    public string $releaseNotesRe;
    public string $gitTagsRe = '~refs/tags/druid-(?<major>\d+)\.(?<minor>\d+)\.(?<patch>\d+)$~';
    public string $gitRepoUrl = 'https://github.com/apache/druid.git';

    // docker
    public string $image = 'apache/druid';
    public string $containerPrefix = 'druid-';
    public string $volumePrefix = 'druid-data-';
    public string $volumeTarget = '/opt/druid/var';
    /** @var array<int> */
    public array $ports = [8888];
    /** @var array<string, string> */
    public array $envVars = [];

    public function translatePort(int $port, Version $version): int
    {
        // router / web console
        // 36.0.0 -> 360
        // 35.0.1 -> 351

        $minor = $version->minor;
        if ($minor === 0) {
            $minor = '';
        }

        return intval($version->major . $minor . $version->patch);
    }

}
