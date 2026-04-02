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
use Dogma\System\Port;

$baseDir = 'C:/tools/php';

/** @var array<int, string> $portNames */
$portNames = array_flip(Port::getAllowedValues());
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
    new ClickhouseInstaller($console),
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

/** @var list<array{0: string, 1: string, 2: int}> $invalidPorts */
$invalidPorts = [];

foreach ($installers as $installer) {
    $installer->baseDir = $baseDir;
    Io::createDirectory("{$baseDir}/cache/{$installer->dir}", Io::IGNORE);
    $installer->loadRemoteVersions();

    foreach ($installer->remote as $versions) {
        foreach ($versions as $versionKey => $version) {
            foreach ($installer->ports as $portKey => $port) {
                $displayName = is_string($portKey) ? "{$installer->fancyName} ({$portKey})" : $installer->fancyName;
                $translated = $installer->translatePort($port, $version);
                $portMap[$translated][] = [$displayName, $versionKey];
                if ($translated > 65535) {
                    $invalidPorts[] = [$displayName, $versionKey, $translated];
                }
            }
        }
    }
}



// --- collect statistics ---

/** @var array<string, array{total: int, colliding: int, minVersion: string, maxVersion: string, minPort: int, maxPort: int}> $stats */
$stats = [];

foreach ($installers as $installer) {
    foreach ($installer->ports as $portKey => $port) {
        $name = is_string($portKey) ? "{$installer->fancyName} ({$portKey})" : $installer->fancyName;
        $stats[$name] = ['total' => 0, 'colliding' => 0, 'minVersion' => '', 'maxVersion' => '', 'minPort' => PHP_INT_MAX, 'maxPort' => 0];

        foreach ($installer->remote as $versions) {
            foreach ($versions as $versionKey => $version) {
                $stats[$name]['total']++;
                if ($stats[$name]['minVersion'] === '' || version_compare($versionKey, $stats[$name]['minVersion']) < 0) {
                    $stats[$name]['minVersion'] = $versionKey;
                }
                if (version_compare($versionKey, $stats[$name]['maxVersion']) > 0) {
                    $stats[$name]['maxVersion'] = $versionKey;
                }
                $translated = $installer->translatePort($port, $version);
                if ($translated < $stats[$name]['minPort']) {
                    $stats[$name]['minPort'] = $translated;
                }
                if ($translated <= 65535 && $translated > $stats[$name]['maxPort']) {
                    $stats[$name]['maxPort'] = $translated;
                }
                if (count($portMap[$translated]) > 1) {
                    $stats[$name]['colliding']++;
                }
            }
        }
    }
}

// --- render HTML ---

$cols = 10;
$total = 65536;
$rowCount = (int) ceil($total / $cols);

ob_start();
echo "<!DOCTYPE html>\n";
echo "<html lang='en'><head><meta charset='utf-8'><title>Assigned Ports</title><style>\n";
echo "* { box-sizing: border-box; }\n";
echo "body { font-family: Calibri, sans-serif; font-size: 12px; padding: 10px; background: #fff; }\n";
echo "table { border-collapse: collapse; }\n";
echo "td { font-family: monospace; border: 1px solid #ccc; padding: 3px 5px; vertical-align: top; min-width: 90px; }\n";
echo "td.skip { color: #bbb; }\n";
echo ".pn { color: #999; font-size: 10px; }\n";
echo ".entry { color: #222; }\n";
echo ".m { background: #ff0; }\n";
echo ".stats td, .stats th { padding: 3px 8px; border: 1px solid #ccc; font-family: Calibri, sans-serif; min-width: 0; vertical-align: middle; }\n";
echo ".stats th { text-align: center; }\n";
echo ".stats th:first-child { text-align: left; }\n";
echo ".stats td { text-align: right; }\n";
echo ".stats td:first-child { text-align: left; }\n";
echo ".stats tr.total { font-weight: bold; border-top: 2px solid #999; }\n";
echo "</style></head><body>\n";
echo "<h1>Mapping tool versions to ports</h1>\n";
echo "<h2>Statistics</h2>\n";
echo "<table class='stats'>\n";
echo "<tr><th>Tool</th><th>Total</th><th>Colliding</th><th>%</th><th>Min version</th><th>Max version</th><th>Min port</th><th>Max port</th></tr>\n";
foreach ($stats as $name => $s) {
    $pct = $s['total'] > 0 ? round($s['colliding'] / $s['total'] * 100) : 0;
    $hl = $s['colliding'] > 0 ? " class='m'" : "";
    echo "<tr>";
    echo "<td>" . htmlspecialchars($name) . "</td>";
    echo "<td>{$s['total']}</td>";
    echo "<td{$hl}>{$s['colliding']}</td>";
    echo "<td{$hl}>{$pct}%</td>";
    echo "<td>{$s['minVersion']}</td>";
    echo "<td>{$s['maxVersion']}</td>";
    echo "<td>{$s['minPort']}</td>";
    echo "<td>{$s['maxPort']}</td>";
    echo "</tr>\n";
}
$totalAll = array_sum(array_column($stats, 'total'));
$collidingAll = array_sum(array_column($stats, 'colliding'));
$pctAll = $totalAll > 0 ? round($collidingAll / $totalAll * 100, 1) : 0;
$hlAll = $collidingAll > 0 ? " class='m'" : "";
echo "<tr class='total'>\n";
echo "<td>Total</td>";
echo "<td>{$totalAll}</td>";
echo "<td{$hlAll}>{$collidingAll}</td>";
echo "<td{$hlAll}>{$pctAll}%</td>";
echo "<td colspan='4'></td>";
echo "</tr>\n";
echo "</table>\n";

echo "<h2>Invalid ports</h2>\n";
if ($invalidPorts === []) {
    echo "<p>No invalid ports found.</p>\n";
} else {
    echo "<table class='stats'>\n";
    echo "<tr><th>Tool</th><th>Version</th><th>Port</th></tr>\n";
    foreach ($invalidPorts as [$name, $ver, $port]) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($name) . "</td>";
        echo "<td>" . htmlspecialchars($ver) . "</td>";
        echo "<td class='m'>{$port}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

echo "<h2>Assigned ports</h2>\n";
echo "<table>\n";

$pendingEmpty = false;

for ($row = 0; $row < $rowCount; $row++) {
    $rowStart = $row * $cols;
    $rowEnd = min($rowStart + $cols, $total);

    // check if entire row is empty
    $rowEmpty = true;
    for ($port = $rowStart; $port < $rowEnd; $port++) {
        if (isset($portMap[$port])) {
            $rowEmpty = false;
            break;
        }
    }

    if ($rowEmpty) {
        $pendingEmpty = true;
        continue;
    }

    // flush pending empty rows as a single collapsed row
    if ($pendingEmpty) {
        echo "<tr><td class='skip' colspan='{$cols}'>...</td></tr>\n";
        $pendingEmpty = false;
    }

    echo "<tr>";
    for ($port = $rowStart; $port < $rowEnd; $port++) {
        $entries = $portMap[$port] ?? [];
        $multi = count($entries) > 1;
        echo "<td" . ($multi ? " class='m'" : "") . ">";
        $portLabel = isset($portNames[$port]) ? "{$port} " . $portNames[$port] : (string) $port;
        echo "<div class='pn'>{$portLabel}</div>";
        foreach ($entries as [$name, $ver]) {
            echo "<div class='entry'>" . htmlspecialchars($name) . " " . htmlspecialchars($ver) . "</div>";
        }
        echo "</td>";
    }
    echo "</tr>\n";
}

// trailing empty rows at the end
if ($pendingEmpty) {
    echo "<tr><td class='skip' colspan='{$cols}'>...</td></tr>\n";
}

echo "</table>\n";


echo "</body></html>\n";

$html = ob_get_clean();

file_put_contents(__DIR__ . '/port-matrix.html', $html);
echo "Written to port-matrix.html\n";
