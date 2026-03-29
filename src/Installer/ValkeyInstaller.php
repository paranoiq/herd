<?php

namespace Herd\Installer;

use Dogma\Re;
use Dogma\Time\Date;
use Herd\Version;
use function explode;
use function str_pad;
use function strlen;
use const PREG_SET_ORDER;
use const STR_PAD_LEFT;

class ValkeyInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'Valkey';
    public string $dir = 'valkey';

    // metadata
    public string $releaseNotesRe = '~> (?P<version>\d+\.\d+(?:\.\d+)?(?:-(?:beta|rc\d))?)</a>[\n\s]+<small>\(Released (?P<date>\d+-\d+-\d+)\)~i';

    // docker
    public string $image = 'valkey/valkey';
    public string $containerPrefix = 'valkey-';
    public string $volumePrefix = 'valkey-data-';
    public string $volumeTarget = '/data';
    public array $ports = [6379];
    public array $envVars = [];

    public function translatePort(int $port, Version $version): int
    {
        // 7.2.12 -> 47212
        // 8.4.1  -> 48401

        return '4' . $version->major . $version->minor . str_pad($version->patch, 2, '0', STR_PAD_LEFT);
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = ['all' => 'https://valkey.io/download/releases/'];
    }

    /**
     * @override
     * @return list<Version>
     */
    public function parseVersionsFromReleaseNotesList(string $html): array
    {
        $versions = [];
        foreach (Re::matchAll($html, $this->releaseNotesRe, PREG_SET_ORDER) as $match) {
            [$ver, $type] = explode('-', $match['version'] . '-');
            [$major, $minor, $patch] = explode('.', $ver . '.');

            if ($major === '0' || (strlen($minor) === 2 && $minor[0] === '0')) {
                continue; // some bullshit numbering; old versions - don't care
            }
            if ($patch === '') {
                $patch = 0;
            }
            if ($type !== '') {
                continue; // nah. just get rid of all the idiotically numbered rcs and betas
            }

            $version = new Version((int) $major, (int) $minor, (int) $patch, null, null, null, new Date($match['date']), null, $this->fancyName);
            $versions[] = $version;
        }

        return $versions;
    }

    /** @override */
    public function versionKey(Version $version): string
    {
        $t = $version->type ? '-' . $version->type : '';

        return $version->format3() . $t;
    }

    /** @override */
    public function formatT(Version $version): string
    {
        $t = $version->type ? ' ' . $version->type : '';

        return $version->format3() . $t;
    }

}
