<?php

namespace Herd\Installer;

use Herd\Version;
use function intval;
use function str_pad;
use function strval;
use const STR_PAD_LEFT;

class PrestoInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'Presto';
    public string $dir = 'presto';
    //public string $minVersion = '3.0.0';
    public string $versionFormat = 'M!.mmm.p';
    public string $portPrefix = '6';

    // metadata
    public string $releaseNotesRe = '~release-(?P<version>\d+\.\d+(?:\.\d+)?)\.html~i';

    // docker
    public string $image = 'prestodb/presto';
    public string $containerPrefix = 'presto-';
    public string $volumePrefix = 'presto-data-';
    public string $volumeTarget = '/opt/presto-server/etc/catalog';
    /** @var array<int> */
    public array $ports = [8080];
    /** @var array<string, string> */
    public array $envVars = [];

    public function translatePort(int $port, Version $version): int
    {
        // 0.279.2 -> 2792

        return intval(($version->major ?: '') . str_pad(strval($version->minor), 3, '0', STR_PAD_LEFT) . ($version->patch ?: '0'));
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = [
            "all" => "https://prestodb.io/docs/current/release.html",
        ];
    }

    /** @override */
    public function familyKey(Version $version): string
    {
        return strval($version->major);
    }

    /** @override */
    public function versionKey(Version $version): string
    {
        return $version->patch !== null ? $version->format3() : $version->format2();
    }

    /** @override */
    public function dockerVersionKey(Version $version): string
    {
        return $version->patch !== null ? $version->format3() : $version->format2();
    }

    /** @override */
    public function format(Version $version): string
    {
        return $version->patch !== null ? $version->format3() : $version->format2();
    }

    /** @override */
    public function formatT(Version $version): string
    {
        return $version->patch !== null ? $version->format3() : $version->format2();
    }

}
