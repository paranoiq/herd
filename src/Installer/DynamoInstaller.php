<?php

namespace Herd\Installer;

use Herd\Version;
use function intval;
use function str_pad;
use function strval;
use const STR_PAD_LEFT;

class DynamoInstaller extends DockerInstaller
{

    // driver
    public string $fancyName = 'DynamoDB';
    public string $dir = 'dynamo';
    public string $minVersion;
    public string $versionFormat = 'M.mm.p';
    public string $portPrefix = '2';

    // metadata
    public string $releaseNotesRe = '~>(?P<version>\d+\.\d+\.\d+).*?<p>(?P<date>[^0-9]+\d+, \d{4})<~is';

    // docker
    public string $image = 'amazon/dynamodb-local';
    public string $containerPrefix = 'dynamo-';
    public string $volumePrefix = 'dynamo-data-';
    public string $volumeTarget = '/data/db';
    public string $runCommand;
    /** @var array<int> */
    public array $ports = [8000];
    /** @var array<string, string> */
    public array $envVars;

    public function translatePort(int $port, Version $version): int
    {
        // 1.25.1 -> 21251

        return intval('2' . $version->major . str_pad(strval($version->minor), 2, '0', STR_PAD_LEFT) . $version->patch);
    }

    public function loadReleaseNotesListsUrls(): void
    {
        $this->releaseNotesListsUrls = [
            "all" => "https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/DynamoDBLocalHistory.html",
        ];
    }

}
