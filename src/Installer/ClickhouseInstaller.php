<?php

namespace Herd\Installer;

use Dogma\Io\FileInfo;
use Dogma\Re;
use Dogma\Time\DateTime;
use Herd\Version;
use function end;
use function str_pad;
use const PREG_SET_ORDER;
use const STR_PAD_LEFT;

class ClickhouseInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'ClickHouse';
    public string $dir = 'clickhouse';
    public string $minVersion = '22.1.0';

    // metadata - matches e.g. "ClickHouse release 25.1, 2025-01-15"
    public string $releaseNotesRe = '~ClickHouse release (?P<version>\d+\.\d+), (?P<date>\d{4}-\d{2}-\d{2})~i';

    // docker
    public string $image = 'clickhouse/clickhouse-server';
    public string $containerPrefix = 'clickhouse-';
    public string $volumePrefix = 'clickhouse-data-';
    public string $volumeTarget = '/var/lib/clickhouse';
    public array $ports = [8123, 9000]; // HTTP interface, native protocol
    public array $envVars = [];

    public function translatePort(int $port, Version $version): int
    {
        if ($port === 9000) {
            // 25.1 -> 2501
            return (int) (str_pad($version->major, 2, '0', STR_PAD_LEFT) . str_pad($version->minor, 2, '0', STR_PAD_LEFT));
        } else {
            // 25.1 -> 62501
            return (int) ('6' . str_pad($version->major, 2, '0', STR_PAD_LEFT) . str_pad($version->minor, 2, '0', STR_PAD_LEFT));
        }
    }

    /**
     * Changelog lists versions as "year.month" with no patch component.
     * Force patch=0 so versionKey/format3 produce clean strings (e.g. "25.1.0").
     * @override
     */
    public function parseVersionsFromReleaseNotesList(string $html): array
    {
        $versions = [];
        foreach (Re::matchAll($html, $this->releaseNotesRe, PREG_SET_ORDER) as $match) {
            $v = Version::parseRelease($match[0], $this->releaseNotesRe, $this->fancyName);
            $version = new Version($v->major, $v->minor, 0, null, null, null, $v->date, $v->type, $v->app);
            $versions[$this->versionKey($version)] = $version;
        }

        return $versions;
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $baseUrl = 'https://clickhouse.com';
        $seedUrl = "{$baseUrl}/docs/category/changelog";

        // seed with current year; category page will reveal older year pages
        $this->releaseNotesListsUrls = [
            '2025' => "{$baseUrl}/docs/whats-new/changelog/2025",
        ];

        $cache = new FileInfo("{$this->baseDir}/cache/{$this->dir}/changelog-category.html");
        if ($cache->exists() && $cache->getModifiedTime()->isAfter(new DateTime('-1 day'))) {
            $html = $cache->read();
        } else {
            $this->console->writeLn("Refreshing release notes ({$seedUrl})");

            $request = $this->httpHelper->createHttpRequest($seedUrl);
            $response = $request->execute();
            rd($response);
            $html = $response->getBody();
            $cache->write($html);
        }

        foreach (Re::matchAll($html, '~whats-new/changelog/(\d{4})\b~i', PREG_SET_ORDER) as $match) {
            $year = $match[1];
            if (!isset($this->releaseNotesListsUrls[$year])) {
                $this->releaseNotesListsUrls[$year] = "{$baseUrl}/docs/whats-new/changelog/{$year}";
            }
        }
    }

    /** @override */
    public function dockerVersionKey(Version $version): string
    {
        // Docker Hub tags are "year.month" only, e.g. "25.1"
        return $version->format2();
    }

    /** @override */
    public function familyKey(Version $version): string
    {
        return (string) $version->major;
    }

    /** @override */
    public function versionKey(Version $version): string
    {
        return $version->format2();
    }

    /** @override */
    public function format(Version $version): string
    {
        return $version->format2();
    }

    /** @override */
    public function formatT(Version $version): string
    {
        return $version->format2();
    }

    public function getLatest(Version $family): int|string|null
    {
        return isset($this->remote[$this->familyKey($family)])
            ? end($this->remote[$this->familyKey($family)])->minor
            : null;
    }

    public function createLatest(Version $version, int|string $last): Version
    {
        return $version->setMinor($last);
    }

}
