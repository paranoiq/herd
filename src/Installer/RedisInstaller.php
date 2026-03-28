<?php

namespace Herd\Installer;

use Dogma\Application\Colors as C;
use Dogma\Application\Configurator;
use Dogma\Re;
use Herd\Version;
use function explode;
use function str_pad;
use function strlen;
use const PREG_SET_ORDER;
use const STR_PAD_LEFT;

class RedisInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'Redis';
    public string $dir = 'redis';
    public string $minVersion = '3.0.0';

    // metadata
    public string $releaseNotesRe = '~>redis-(?P<version>\d+\.\d+(?:\.\d+)?(?:-(?:beta|rc\d))?)~i';

    // docker
    public string $image = 'redis';
    public string $containerPrefix = 'redis-';
    public string $volumePrefix = 'redis-data-';
    public string $volumeTarget = '/data';
    public array $ports = [6379];
    public array $envVars = [];

    public function run(Configurator $config): void
    {
        $this->console->writeLn(C::lyellow("Check out Valkey - the truly open-source Redis fork!"));

        parent::run($config);
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = ['all' => 'https://download.redis.io/releases/'];
    }

    /**
     * @override
     * @return list<Version>
     */
    public function parseVersionsFromReleaseNotesList(string $html): array
    {
        $versions = [];
        foreach (Re::matchAll($html, $this->releaseNotesRe, PREG_SET_ORDER) as $match) {
            [$ver, $type] = explode('-', $match[1] . '-');
            [$major, $minor, $patch] = explode('.', $ver . '.');

            if ($major === '0' || (strlen($minor) === 2 && $minor[0] === '0') || strlen($patch) === 3) {
                continue; // some bullshit numbering; old versions - don't care
            }
            if ($patch === '') {
                $patch = 0;
            }
            if ($type !== '') {
                continue; // nah. just get rid of all the idiotically numbered rcs and betas
            }

            $version = new Version((int) $major, (int) $minor, (int) $patch, null, null, null, null, null, $this->fancyName);
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

    public function translatePort(int $port, Version $version): int
    {
        // 7.2.12 -> 47212
        // 8.4.1  -> 48401

        return '4' . $version->major . $version->minor . str_pad($version->patch, '0', STR_PAD_LEFT);
    }

}
