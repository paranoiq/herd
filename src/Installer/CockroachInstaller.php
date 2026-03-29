<?php

namespace Herd\Installer;

use Herd\Version;
use function str_pad;
use function version_compare;
use const STR_PAD_LEFT;

class CockroachInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'CockroachDB';
    public string $dir = 'cockroach';
    public string $minVersion = '21.2.0';

    // metadata
    public string $releaseNotesRe = '~match-nothing~';

    // docker
    public string $image = 'cockroachdb/cockroach';
    public string $containerPrefix = 'cockroach-';
    public string $volumePrefix = 'cockroach-data-';
    public string $volumeTarget = '/cockroach/cockroach-data';
    public string $runCommand = 'start-single-node --insecure';
    public array $ports = [26257, 8080]; // SQL, admin
    public array $envVars;

    public function translatePort(int $port, Version $version): int
    {
        if ($port === 26257) {
            // SQL
            // 26.1.0  -> 26100
            // 25.2.13 -> 25213

            return $version->major . $version->minor . str_pad($version->patch, 2, '0', STR_PAD_LEFT);
        } else {
            // Admin UI
            // 26.1.0  -> 46100
            // 25.2.13 -> 45213

            return ($version->major + 2) . $version->minor . str_pad($version->patch, 2, '0', STR_PAD_LEFT);
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

        // no release notes for older versions, because they are archived
        if (exec('git ls-remote --tags https://github.com/cockroachdb/cockroach.git', $output, $resultCode) !== false && $resultCode === 0) {
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
