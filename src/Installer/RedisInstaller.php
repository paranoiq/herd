<?php

namespace Herd\Installer;

use Dogma\Application\Colors as C;
use Dogma\Application\Configurator;
use Dogma\Parse;
use Dogma\Re;
use Herd\Version;
use function explode;
use function intval;
use function str_pad;
use function strlen;
use function strval;
use const PREG_SET_ORDER;
use const STR_PAD_LEFT;

class RedisInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'Redis';
    public string $dir = 'redis';
    public string $minVersion = '3.0.0';
    public string $versionFormat = 'M.m.pp';
    public string $portPrefix = '4';

    // metadata
    public string $releaseNotesRe = '~>redis-(?P<version>\d+\.\d+(?:\.\d+)?(?:-(?:beta|rc\d))?)~i';

    // docker
    public string $image = 'redis';
    public string $containerPrefix = 'redis-';
    public string $volumePrefix = 'redis-data-';
    public string $volumeTarget = '/data';
    /** @var array<int> */
    public array $ports = [6379];
    /** @var array<string, string> */
    public array $envVars = [];

    public function run(Configurator $config): void
    {
        $this->console->writeLn(C::lyellow("Check out Valkey - the truly open-source Redis fork!"));

        parent::run($config);
    }

    public function translatePort(int $port, Version $version): int
    {
        // 7.2.12 -> 47212
        // 8.4.1  -> 48401

        return intval('4' . $version->major . $version->minor . str_pad(strval($version->patch), 2, '0', STR_PAD_LEFT));
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = ['all' => 'https://download.redis.io/releases/'];
    }

    /**
     * @override
     * @return list<Version>
     */
    public function getAvailableVersions(string $html): array
    {
        $versions = [];
        foreach (Re::matchAll($html, $this->releaseNotesRe, PREG_SET_ORDER) as $match) {
            [$ver, $type] = explode('-', $match[1] . '-');
            [$major, $minor, $patch] = Parse::intsOrNulls($ver . '.', '.');

            if ($major === 0 || strlen(strval($patch)) === 3 || (strlen(strval($minor)) === 2 && strval($minor)[0] === '0')) {
                continue; // some bullshit numbering; old versions - don't care
            }
            if ($patch === null) {
                $patch = 0;
            }
            if ($type !== '') {
                continue; // nah. just get rid of all the idiotically numbered rcs and betas
            }

            $version = Version::new3($major, $minor, $patch,  null, null, null, $this->fancyName);
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
