<?php

namespace Herd\Installer;

use Herd\Version;
use function str_pad;
use function version_compare;
use const STR_PAD_LEFT;

class DragonflyInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'Dragonfly';
    public string $dir = 'dragonfly';
    public string $minVersion = '0.2.0';

    // metadata
    public string $releaseNotesRe = '~match-nothing~';

    // docker
    public string $image = 'docker.dragonflydb.io/dragonflydb/dragonfly';
    public string $containerPrefix = 'dragonfly-';
    public string $volumePrefix = 'dragonfly-data-';
    public string $volumeTarget = '/data';
    public string $runCommand = '--dir /data';
    public array $ports = [6379];
    public array $envVars;

    public function translatePort(int $port, Version $version): int
    {
        // 1.24.8 -> 41348

        return '4' . $version->major . str_pad($version->minor, 2, '0', STR_PAD_LEFT) . $version->patch;
    }

    public function dockerVersionKey(Version $version): string
    {
        return 'v' . $version->format3();
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = [];

        if (exec('git ls-remote --tags https://github.com/dragonflydb/dragonfly.git', $output, $resultCode) !== false && $resultCode === 0) {
            foreach ($output as $row) {
                // take only GA release versions
                if (preg_match('~refs/tags/v(\d+\.\d+\.\d+)$~', $row, $matches)) {
                    [$major, $minor, $patch] = explode('.', $matches[1]);
                    $version = new Version((int)$major, (int)$minor, (int)$patch, null, null, null, null, null, $this->fancyName);
                    if (!isset($this->minVersion) || version_compare($this->versionKey($version), $this->minVersion) >= 0) {
                        $this->remote[$this->familyKey($version)][$this->versionKey($version)] = $version;
                    }
                }
            }
        }
    }

}
