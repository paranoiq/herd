<?php

namespace Herd\Installer;

use Herd\Version;
use function str_pad;
use function version_compare;
use const STR_PAD_LEFT;

class MongoInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'MongoDB';
    public string $dir = 'mongo';
    public string $minVersion = '3.0.0';

    // metadata
    public string $releaseNotesRe = '~match-nothing~';

    // docker
    public string $image = 'mongo';
    public string $containerPrefix = 'mongo-';
    public string $volumePrefix = 'mongo-data-';
    public string $volumeTarget = '/data/db';
    public string $runCommand;
    public array $ports = [27017];
    public array $envVars;

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = [
            "new" => "https://www.mongodb.com/docs/manual/release-notes/",
        ];

        // no release notes for older versions, because they are archived
        if (exec('git ls-remote --tags https://github.com/mongodb/mongo.git', $output, $resultCode) !== false && $resultCode === 0) {
            foreach ($output as $row) {
                // take only GA release versions
                if (preg_match('~refs/tags/r(\d+\.\d+\.\d+)$~', $row, $matches)) {
                    [$major, $minor, $patch] = explode('.', $matches[1]);
                    $version = new Version((int)$major, (int)$minor, (int)$patch, null, null, null, null, null, $this->fancyName);
                    if (!isset($this->minVersion) || version_compare($this->versionKey($version), $this->minVersion) >= 0) {
                        $this->remote[$this->familyKey($version)][$this->versionKey($version)] = $version;
                    }
                }
            }
        }
    }

    public function translatePort(int $port, Version $version): int
    {
        // 8.0.19 -> 28019

        return '2' . $version->major . $version->minor . str_pad($version->patch, 2, '0', STR_PAD_LEFT);
    }

}
