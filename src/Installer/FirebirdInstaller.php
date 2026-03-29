<?php

namespace Herd\Installer;

use Dogma\Io\FileInfo;
use Dogma\Re;
use Dogma\Time\DateTime;
use Herd\Version;
use function str_pad;
use function str_replace;
use const PREG_SET_ORDER;
use const STR_PAD_LEFT;

class FirebirdInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'Firebird';
    public string $dir = 'firebird';
    public string $minVersion = '3.0.0';

    // metadata
    public string $releaseNotesRe = '~(?:Firebird|Sub-release) (?:V\.)?(?P<version>\d+.\d+.\d+)~i';

    // docker
    public string $image = 'firebirdsql/firebird';
    public string $containerPrefix = 'firebird-';
    public string $volumePrefix = 'firebird-data-';
    public string $volumeTarget = '/var/lib/firebird/data';
    public array $ports = [3050];
    public array $envVars = ['FIREBIRD_PASSWORD' => 'root']; // admin user "SYSDBA", default password is "masterkey"

    public function translatePort(int $port, Version $version): int
    {
        // 5.0.3  -> 35003
        // 3.0.13 -> 33013

        return '3' . $version->major . $version->minor . str_pad($version->patch, 2, '0', STR_PAD_LEFT);
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $baseUrl = 'https://www.firebirdsql.org';

        $this->releaseNotesListsUrls = [
            "old" => "https://www.firebirdsql.org/en/release-notes/", // seed
        ];

        $releaseNotesListRe = '~/file/documentation/release_notes/html/[^.]+\.html~i';
        foreach ($this->releaseNotesListsUrls as $fam => $releasesUrl) {
            $fam = str_replace('.', '-', $fam);
            $cache = new FileInfo("{$this->baseDir}/cache/{$this->dir}/release-notes-{$fam}.html");
            if ($cache->exists() && $cache->getModifiedTime()->isAfter(new DateTime('-1 day'))) {
                $html = $cache->read();
            } else {
                $this->console->writeLn("Refreshing release notes ({$releasesUrl})");

                $request = $this->httpHelper->createHttpRequest($releasesUrl);
                $response = $request->execute();
                $html = $response->getBody();
                $cache->write($html);
            }

            foreach (Re::matchAll($html, $releaseNotesListRe, PREG_SET_ORDER) as $match) {
                $this->releaseNotesListsUrls[] = $baseUrl . $match[0];
            }
        }
    }

    public array $releaseDates = [
        '5.0.3'  => '2025-07-11',
        '5.0.2'  => '2025-02-12',
        '5.0.1'  => '2024-08-01',
        '5.0.0'  => '2024-01-11',

        '4.0.6'  => '2025-07-15',
        '4.0.5'  => '2024-08-01',
        '4.0.4'  => '2023-12-19',
        '4.0.3'  => '2023-05-10',
        '4.0.2'  => '2022-07-28',
        '4.0.1'  => '2021-12-23',
        '4.0.0'  => '2021-06-01',

        '3.0.13' => '2025-07-09',
        '3.0.12' => '2024-08-01',
        '3.0.11' => '2023-12-19',
        '3.0.10' => '2022-06-24',
        '3.0.9'  => '2022-02-14',
        '3.0.8'  => '2021-12-23',
        '3.0.7'  => '2020-11-26',
        '3.0.6'  => '2020-07-02',
        '3.0.5'  => '2019-10-18',
        '3.0.4'  => '2018-09-27',
        '3.0.3'  => '2018-02-09',
        '3.0.2'  => '2017-03-24',
        '3.0.1'  => '2016-09-28',
        '3.0.0'  => '2016-04-19',

        '2.5.9'  => '2019-06-24',
        '2.5.8'  => '2018-01-19',
        '2.5.7'  => '2017-07-13',
        '2.5.6'  => '2016-07-11',
        '2.5.5'  => '2015-10-22',
        '2.5.4'  => '2015-03-31',
        '2.5.3'  => '2014-07-15',
        '2.5.2'  => '2012-11-13',
        '2.5.1'  => '2011-10-04',
        '2.5.0'  => '2010-10-04',

        '2.1.7'  => '2014-12-05',
        '2.1.6'  => '2014-03-26',
        '2.1.5'  => '2012-08-23',
        '2.1.4'  => '2011-03-14',
        '2.1.3'  => '2010-02-08',
        '2.1.2'  => '2009-04-03',
        '2.1.1'  => '2008-08-11',
        '2.1.0'  => '2008-04-18',

        '2.0.7'  => '2012-04-13',
        '2.0.6'  => '2010-06-15',
        '2.0.5'  => '2009-04-16',
        '2.0.4'  => '2008-03-27',
        '2.0.3'  => '2007-09-27',
        '2.0.1'  => '2007-04-13',
        '2.0.0'  => '2006-11-12',

        '1.5.6'  => '2009-10-13',
        '1.5.5'  => '2007-11-20',
        '1.5.4'  => '2007-02-14',
        '1.5.3'  => '2005-12-21',
        '1.5.2'  => '2004-12-30',
        '1.5.1'  => '2004-07-14',
        '1.5.0'  => '2004-02-09',

        '1.0.3'  => '2003-06-17',
        '1.0.2'  => '2003-01-14',
        '1.0.0'  => '2002-03-11',
    ];

}
