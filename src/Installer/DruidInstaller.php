<?php

namespace Herd\Installer;

use Herd\Version;
use function explode;
use function str_pad;
use function version_compare;
use const STR_PAD_LEFT;

class DruidInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'Druid';
    public string $dir = 'druid';
    public string $minVersion = '24.0.0';

    // metadata
    public string $releaseNotesRe = '~match-nothing~';

    // docker
    public string $image = 'apache/druid';
    public string $containerPrefix = 'druid-';
    public string $volumePrefix = 'druid-data-';
    public string $volumeTarget = '/opt/druid/var';
    public array $ports = [8888];
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

        return (int) ($version->major . $minor . $version->patch);
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = [];

        if (exec('git ls-remote --tags https://github.com/apache/druid.git', $output, $resultCode) !== false && $resultCode === 0) {
            foreach ($output as $row) {
                // tags are like "refs/tags/druid-36.0.0"
                if (preg_match('~refs/tags/druid-(\d+\.\d+\.\d+)$~', $row, $matches)) {
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
