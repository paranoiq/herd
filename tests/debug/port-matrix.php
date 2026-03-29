<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Dogma\Application\Console;
use Dogma\Io\Io;
use Herd\Installer\CassandraInstaller;
use Herd\Installer\ClickhouseInstaller;
use Herd\Installer\CockroachInstaller;
use Herd\Installer\DockerInstaller;
use Herd\Installer\DragonflyInstaller;
use Herd\Installer\DruidInstaller;
use Herd\Installer\DynamoInstaller;
use Herd\Installer\FirebirdInstaller;
use Herd\Installer\InfluxInstaller;
use Herd\Installer\MariaInstaller;
use Herd\Installer\MemcachedInstaller;
use Herd\Installer\MongoInstaller;
use Herd\Installer\MysqlInstaller;
use Herd\Installer\PostgreInstaller;
use Herd\Installer\PrestoInstaller;
use Herd\Installer\QuestInstaller;
use Herd\Installer\RedisInstaller;
use Herd\Installer\TimescaleInstaller;
use Herd\Installer\TrinoInstaller;
use Herd\Installer\ValkeyInstaller;

$baseDir = 'C:/tools/php';
$console = new Console();

/** @var DockerInstaller[] $installers */
$installers = [
    new MysqlInstaller($console),
    new MariaInstaller($console),
    new PostgreInstaller($console),
    new FirebirdInstaller($console),
    new CockroachInstaller($console),
    new PrestoInstaller($console),
    new TrinoInstaller($console),
    new DruidInstaller($console),
    new TimescaleInstaller($console),
    new QuestInstaller($console),
    new InfluxInstaller($console),
    //new ClickhouseInstaller($console),
    new MongoInstaller($console),
    new DynamoInstaller($console),
    new CassandraInstaller($console),
    new RedisInstaller($console),
    new ValkeyInstaller($console),
    new MemcachedInstaller($console),
    new DragonflyInstaller($console),
];

/** @var array<int, list<array{0: string, 1: string}>> $portMap */
$portMap = [];

foreach ($installers as $installer) {
    $installer->baseDir = $baseDir;
    Io::createDirectory("{$baseDir}/cache/{$installer->dir}", Io::IGNORE);
    $installer->loadRemoteVersions();

    foreach ($installer->remote as $versions) {
        foreach ($versions as $versionKey => $version) {
            foreach ($installer->ports as $port) {
                $translated = $installer->translatePort($port, $version);
                $portMap[$translated][] = [$installer->fancyName, $versionKey];
            }
        }
    }
}

// --- render HTML ---

$cols = 20;
$total = 65536;
$rowCount = (int) ceil($total / $cols);

ob_start();
echo '<!DOCTYPE html>' . "\n";
echo '<html><head><meta charset="utf-8"><title>Port Matrix</title><style>' . "\n";
echo '* { box-sizing: border-box; }' . "\n";
echo 'body { font-family: monospace; font-size: 11px; padding: 10px; background: #fff; }' . "\n";
echo 'table { border-collapse: collapse; }' . "\n";
echo 'td { border: 1px solid #ccc; padding: 3px 5px; vertical-align: top; min-width: 90px; }' . "\n";
echo 'td.skip { color: #bbb; }' . "\n";
echo '.pn { color: #999; font-size: 10px; }' . "\n";
echo '.entry { color: #222; }' . "\n";
echo '.m { background: #ff0; }' . "\n";
echo '</style></head><body>' . "\n";
echo '<table>' . "\n";

$pendingEmpty = false;

for ($row = 0; $row < $rowCount; $row++) {
    $rowStart = $row * $cols;
    $rowEnd = min($rowStart + $cols, $total);

    // check if entire row is empty
    $isEmpty = true;
    for ($port = $rowStart; $port < $rowEnd; $port++) {
        if (isset($portMap[$port])) {
            $isEmpty = false;
            break;
        }
    }

    if ($isEmpty) {
        $pendingEmpty = true;
        continue;
    }

    // flush pending empty rows as a single collapsed row
    if ($pendingEmpty) {
        echo '<tr><td class="skip" colspan="' . $cols . '">...</td></tr>' . "\n";
        $pendingEmpty = false;
    }

    echo '<tr>';
    for ($port = $rowStart; $port < $rowEnd; $port++) {
        $entries = $portMap[$port] ?? [];
        $multi = count($entries) > 1;
        echo '<td' . ($multi ? ' class="m"' : '') . '>';
        echo '<div class="pn">' . $port . '</div>';
        foreach ($entries as [$name, $ver]) {
            echo '<div class="entry">' . htmlspecialchars($name) . ' ' . htmlspecialchars($ver) . '</div>';
        }
        echo '</td>';
    }
    echo '</tr>' . "\n";
}

// trailing empty rows at the end
if ($pendingEmpty) {
    echo '<tr><td class="skip" colspan="' . $cols . '">...</td></tr>' . "\n";
}

echo '</table>' . "\n";
echo '</body></html>' . "\n";

$html = ob_get_clean();

file_put_contents(__DIR__ . '/port-matrix.html', $html);
echo "Written to port-matrix.html\n";
