<?php

namespace Herd\Installer;

use Dogma\Parse;
use Dogma\Re;
use Dogma\Time\Date;
use Herd\Version;
use function explode;
use function intval;
use function str_pad;
use function strlen;
use function strval;
use const PREG_SET_ORDER;
use const STR_PAD_LEFT;

class ValkeyInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'Valkey';
    public string $dir = 'valkey';
    //public string $minVersion = '6.0.0';
    public string $versionFormat = 'M.m.pp';
    public string $portPrefix = '5';

    // metadata
    public string $releaseNotesRe = '~> (?P<version>\d+\.\d+(?:\.\d+)?(?:-(?:beta|rc\d))?)</a>[\n\s]+<small>\(Released (?P<date>\d+-\d+-\d+)\)~i';

    // docker
    public string $image = 'valkey/valkey';
    public string $containerPrefix = 'valkey-';
    public string $volumePrefix = 'valkey-data-';
    public string $volumeTarget = '/data';
    /** @var array<int> */
    public array $ports = [6379];
    /** @var array<string, string> */
    public array $envVars = [];

    public function translatePort(int $port, Version $version): int
    {
        // 7.2.12 -> 47212
        // 8.4.1  -> 48401

        return intval('5' . $version->major . $version->minor . str_pad(strval($version->patch), 2, '0', STR_PAD_LEFT));
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = ['all' => 'https://valkey.io/download/releases/'];
    }

    /**
     * @override
     * @return list<Version>
     */
    public function getAvailableVersions(string $html): array
    {
        $versions = [];
        foreach (Re::matchAll($html, $this->releaseNotesRe, PREG_SET_ORDER) as $match) {
            [$ver, $type] = explode('-', $match['version'] . '-');
            [$major, $minor, $patch] = Parse::intsOrNulls($ver . '.', '.');

            if ($major === 0 || (strlen(strval($minor)) === 2 && strval($minor)[0] === '0')) {
                continue; // some bullshit numbering; old versions - don't care
            }
            if ($patch === null) {
                $patch = 0;
            }
            if ($type !== '') {
                continue; // nah. just get rid of all the idiotically numbered rcs and betas
            }

            $version = Version::new3($major, $minor, $patch, null, null, new Date($match['date']), $this->fancyName);
            $versions[] = $version;
        }

        return $versions;
    }

    /** @override */
    public function versionKey(Version $version): string
    {
        $t = $version->type !== null ? '-' . $version->type : '';

        return $version->format3() . $t;
    }

    /** @override */
    public function formatT(Version $version): string
    {
        $t = $version->type !== null ? ' ' . $version->type : '';

        return $version->format3() . $t;
    }

}
