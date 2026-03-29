<?php

namespace Herd\Installer;

use Herd\Version;
use function explode;
use function str_pad;
use function version_compare;
use const STR_PAD_LEFT;

class QuestInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'QuestDB';
    public string $dir = 'quest';
    public string $minVersion = '6.0.0';

    // metadata
    public string $releaseNotesRe = '~match-nothing~';

    // docker
    public string $image = 'questdb/questdb';
    public string $containerPrefix = 'quest-';
    public string $volumePrefix = 'quest-data-';
    public string $volumeTarget = '/root/.questdb';
    public array $ports = [8812, 9000]; // PostgreSQL wire, HTTP REST / web console
    public array $envVars = [];

    public function translatePort(int $port, Version $version): int
    {
        if ($port === 8812) {
            // PostgreSQL wire protocol
            // 8.2.1 -> 58021
            return '1' . $version->major . str_pad($version->minor, 2, '0', STR_PAD_LEFT) . $version->patch;
        } else { // 9000
            // HTTP REST API / web console
            // 8.2.1 -> 48021
            return '4' . $version->major . str_pad($version->minor, 2, '0', STR_PAD_LEFT) . $version->patch;
        }
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = [];

        if (exec('git ls-remote --tags https://github.com/questdb/questdb.git', $output, $resultCode) !== false && $resultCode === 0) {
            foreach ($output as $row) {
                // take only GA release versions (e.g. 8.2.1, no rc/alpha/beta)
                if (preg_match('~refs/tags/(\d+\.\d+\.\d+)$~', $row, $matches)) {
                    [$major, $minor, $patch] = explode('.', $matches[1]);
                    $version = new Version((int) $major, (int) $minor, (int) $patch, null, null, null, null, null, $this->fancyName);
                    if (!isset($this->minVersion) || version_compare($this->versionKey($version), $this->minVersion) >= 0) {
                        $this->remote[$this->familyKey($version)][$this->versionKey($version)] = $version;
                    }
                }
            }
        }
    }

}
