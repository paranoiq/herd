<?php

namespace Herd;

use Dogma\Application\Colors as C;
use Dogma\Application\Configurator as Cf;
use Dogma\Application\Console;
use Dogma\Debug\Dumper;
use Dogma\Debug\System;
use Herd\Installer\CassandraInstaller;
use Herd\Installer\ClickhouseInstaller;
use Herd\Installer\CockroachInstaller;
use Herd\Installer\DragonflyInstaller;
use Herd\Installer\DruidInstaller;
use Herd\Installer\TimescaleInstaller;
use Herd\Installer\DynamoInstaller;
use Herd\Installer\FirebirdInstaller;
use Herd\Installer\InfluxInstaller;
use Herd\Installer\MariaInstaller;
use Herd\Installer\MemcachedInstaller;
use Herd\Installer\MongoInstaller;
use Herd\Installer\MysqlInstaller;
use Herd\Installer\PhpInstaller;
use Herd\Installer\PostgreInstaller;
use Herd\Installer\PrestoInstaller;
use Herd\Installer\QuestInstaller;
use Herd\Installer\RedisInstaller;
use Herd\Installer\TrinoInstaller;
use Herd\Installer\ValkeyInstaller;
use function class_exists;
use function get_class;
use function strtolower;
use const PHP_VERSION;

if (class_exists(Dumper::class)) {
    Dumper::$objectFormatters[Version::class] = static function (Version $version): string {
        if ($version->app === null) { // PHP
            return Dumper::class(get_class($version)) . Dumper::bracket('(')
                . Dumper::value($version->format6())
                . Dumper::bracket(')') . ' ' . Dumper::info('// #' . Dumper::objectHash($version));
        } else {
            $date = $version->date ? $version->date->format('Y-m-d') : '';
            return Dumper::class(get_class($version)) . Dumper::bracket('(')
                . Dumper::value($version->format3())
                . ($date ? ' ' . Dumper::value2($date) : '')
                . ($version->type ? ' ' . Dumper::value2($version->type) : '')
                . Dumper::bracket(')') . ' ' . Dumper::info('// #' . Dumper::objectHash($version));
        }
    };
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    echo 'Herd: Run `composer install` to install dependencies.';
    exit(2);
}

$ch = static function(string $text, string $hex): string
{
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    return "\e[1m\e[38;2;{$r};{$g};{$b}m{$text}\e[0m";
};
$y = static function (string $s): string {
    return C::lyellow($s);
};
$w = static function (string $s): string {
    return C::white($s);
};

// others:

// MSSQL, Db2 (IBM), Apache HBase
// Couchbase, CouchDB ??

// nope (cloud): BigQuery, Firestore, Athena (Amazon), Redshift (Amazon), Snowflake
// embedded: H2, SQLite, DuckDB

$arguments = [
        'Resources:',
    'php' =>            ['',  Cf::FLAG, $ch("PHP", "777bb4") . "         - the dead programming language (default, local)"],

    'mysql' =>          ['M', Cf::FLAG, $ch("MySQL", "00758f") . "       - OLTP SQL database"], // 3 (8.0.11 -> 38011)
    'maria' =>          ['A', Cf::FLAG, $ch("MariaDB", "1f305f") . "     - OLTP SQL database, MySQL fork"], // 1 (10.9.8 -> 10908)
    'postgre' =>        ['P', Cf::FLAG, $ch("PostgreSQL", "336791") . "  - OLTP SQL database"], // 5 (10.23 -> 51023)
    'firebird' =>       ['F', Cf::FLAG, $ch("Firebird", "f41b0a") . "    - OLTP SQL database"], // 3 (5.0.3 -> 35003)
    'cockroach' =>      ['C', Cf::FLAG, $ch("CockroachDB", "6933ff") . " - OLTP SQL distributed database"], // 2, 4 (main 25.2.13 -> 25213, admin 5.2.13 -> 45213)

    'clickhouse' =>     ['H', Cf::FLAG, $ch("ClickHouse", "fafe69") . "  - OLAP SQL column-oriented database"], // _, 6 (native 25.1 -> 2501, http 25.1 -> 62501)
    'druid' =>          ['D', Cf::FLAG, $ch("Druid", "3bb9c3") . "       - OLAP SQL distributed database, Apache"], // 3 (36.0.0 -> 36000)
    'presto' =>         ['',  Cf::FLAG, $ch("Presto", "f41b0a") . "      - OLAP SQL distributed database"], // _ (0.279.2 -> 2792)
    'trino' =>          ['',  Cf::FLAG, $ch("Trino", "dd00a1") . "       - OLAP SQL distributed database, Presto fork"], // _ (479 -> 4790)

    'timescale' =>      ['T', Cf::FLAG, $ch("TimescaleDB", "f5ff80") . " - time-series SQL database, PostgreSQL extension"], // 6 (2.17.0 -> 62170)
    'quest' =>          ['Q', Cf::FLAG, $ch("QuestDB", "a33153") . "     - time-series SQL database"], // 1, 4 (main 8.2.1 -> 58021, admin 8.2.1 -> 49021)
    'influx' =>         ['I', Cf::FLAG, $ch("InfluxDB", "b619b6") . "    - time-series SQL/InfluxQL database"], // 6 (2.7.1 -> 62071)

    // Prometheus ? (metrics db)
    // VictoriaMetrics ? (metrics db)

    'mongo' =>          ['G', Cf::FLAG, $ch("MongoDB", "00ed64") . "     - document database"], // 2 (8.0.19 -> 28019)
    'dynamo' =>         ['',  Cf::FLAG, $ch("DynamoDB", "1c5e9d") . "    - document database, Amazon"], // 2 (1.25.1 -> 21251)
    'cassandra' =>      ['',  Cf::FLAG, $ch("Cassandra", "1287b1") . "   - column database, Apache"], // 2 (4.0.19 -> 24019)
    // FerretDB ? (MongoDB-like API on Postgre backend, needs docker-compose)

    'redis' =>          ['R', Cf::FLAG, $ch("Redis", "ff4438") . "       - key-value storage"], // 4 (8.4.1 -> 48401)
    'valkey' =>         ['V', Cf::FLAG, $ch("Valkey", "6983ff") . "      - key-value storage, Redis fork"], // 4 (8.4.1 -> 48401)
    'memcached' =>      ['',  Cf::FLAG, $ch("Memcached", "298d83") . "   - key-value storage"], // 1 (1.6.40 -> 11640)
    'dragonfly' =>      ['',  Cf::FLAG, $ch("Dragonfly", "9143e0") . "   - key-value storage"], // 4 (1.24.8 -> 41348)

    // ElasticSearch E
    // OpenSearch O
    // Meilisearch ?
    // Neo4j N

    // RabbitMQ ?
    // ActiveMQ ?
    // Kafka K
    // Pulsar ?
    // NATS ?

        'Actions:',
    'all' =>            ['a', Cf::FLAG_VALUE, 'list available versions', 'expr'],
    'local' =>          ['l', Cf::FLAG_VALUE, 'list local versions', 'expr'],
    'new' =>            ['n', Cf::FLAG_VALUE, 'check for new versions', 'expr'],
    'install' =>        ['i', Cf::FLAG_VALUE, 'install versions', 'expr'],
    'uninstall' =>      ['u', Cf::FLAG_VALUE, 'uninstall versions', 'expr'],
    'run' =>            ['r', Cf::VALUE, 'run service (cgi, container)', 'expr'],
    'stop' =>           ['s', Cf::VALUE, 'stop service (cgi, container)', 'expr'],
    'help' =>           ['h', Cf::FLAG, 'show this help'],
    'license' =>        ['',  Cf::FLAG, 'show license'],
        'PHP specific actions:',
    'default' =>        ['d', Cf::VALUE, 'set version as default (levels: global|major|minor)', 'expr[:level]'],
    'info' =>           ['',  Cf::VALUE, 'info about version', 'expr'],
    'extension' =>      ['e', Cf::FLAG_VALUE, 'list/install/uninstall extensions (combine with other actions)', 'name[:expr]'],
    'on' =>             ['x', Cf::FLAG_VALUE, 'extension ON for versions', 'expr'],
    'off' =>            ['X', Cf::FLAG_VALUE, 'extension OFF for versions', 'expr'],
    'configure' =>      ['c', Cf::FLAG_VALUE, 'update config files from templates', 'expr'],
        'Options:',
//    'template' =>       ['t', Cf::FLAG, 'apply action on configuration templates instead (on/off/jitOn/JitOff)'],
//    'port' =>           ['p', Cf::VALUE, 'service port', 'port'],
//    'password' =>       ['w', Cf::VALUE, 'service root password', 'port'],
    'baseDir' =>        ['b', Cf::VALUE, 'base install directory', 'path'],
    'test' =>           ['t', Cf::VALUES, 'make no changes, only test which versions are selected'],
    'config' =>         ['',  Cf::VALUES, 'load configuration files', 'paths'],
    'refresh' =>        ['',  Cf::FLAG, 'refresh metadata before running new/install/uninstall'],
    'noDefault' =>      ['',  Cf::FLAG, 'do not set higher version as default on install'],
    'noActivate' =>     ['',  Cf::FLAG, 'do not activate extensions on install'],
    'noColors' =>       ['',  Cf::FLAG, 'without colors'],
    'noLogo' =>         ['',  Cf::FLAG, 'without logo'],
];
$defaults = [
    'baseDir' => System::isWindows() ? 'C:/tools/php' : '~/tools/php',
];
$config = new Cf($arguments, $defaults);
$config->loadCliArguments();

$console = new Console();

if ($config->noColors) {
    C::$off = true;
}

if (!$config->noLogo) {
    $console->writeLn("  ┌────────┬──┐      ");
    $console->writeLn("  │     ┌┘ │ ▋│┌─┐   ", $y("HERD by @paranoiq"));
    $console->writeLn(" ┌┤     │  │  ││┌┘   ", "Backend tools installer/manager");
    $console->writeLn(" ││     └──┘│ └┘│    ");
    $console->writeLn(" ┘│         └┬──┘    ", "PHP " . PHP_VERSION);
    $console->writeLn("  │ ┌─┬──┐ ┌ │       ");
    $console->writeLn("▔▔└─┴─┘▔▔└─┴─┘▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔");
    $console->ln();
}

if ($config->help === true || (!$config->hasValues() && (!$config->config))) {
    $console->writeLn('Tool for downloading, updating and configuring:');
    $console->writeLn('   - many ', $y('PHP'), ' versions at once (Windows only, local)');
    $console->writeLn('   - many ', $y('database'), '/', $y('cache'), '/', $y('queue'), '... versions at once (dockerized)')->ln();

    $console->writeLn('Usage: ' . $y('herd --resource [--options] --action versions'))->ln();

    $console->write($config->renderHelp());

    $console->writeLn('Versions expression: '
        . $y('(n|*|^|_)') . $w('.[n|*|^|_]') . $y('.[n|*|^|_]')
        . $w('-[nts|ts|*]') . $y('-[64|32|*]'));
    $console->writeLn('                     (major)   (minor)   (patch)   (threads)  (arch.)');
    $console->writeLn($w('  n'), ' = version number');
    $console->writeLn($w('  *'), ' = any version');
    $console->writeLn($w('  ^'), ' = last version');
    $console->writeLn($w('  _'), ' = any except last version');
    $console->writeLn($w('  nts|ts'), ' = non thread safe (Nginx, CGI, CLI, faster) or thread safe (Apache). defaults to ', $w('nts'), '.');
    $console->writeLn($w('  64|32'), ' = 64bit or 32bit version. defaults to ', $w('64'), '.');
    $console->writeLn('  More expressions can be used with ', $w('","'), ' as separator.');

    $console->ln()->writeLn('Examples:');
    $console->writeLn($y('  herd -i "**^"'), ' will install all latest versions in nts-64 variant (5.5 to 8.0).');
    $console->writeLn($y('  herd -u 73_32,74_32'), ' will uninstall all obsolete versions in nts-32 variants of 7.3 and 7.4.');
    $console->writeLn($y('  herd -n 8ts'), ' will check if you have all last versions of 8.x-ts-64 installed.');

    $console->ln()->writeLn('Installing PHP extensions:');
    $console->writeLn('  To list/install/uninstall extensions add ' . $w('--extension')
        . ' parameter and the specified action will be performed on extension instead.'
        . "\n  E.g. " . $y('herd -i 8 -e imagick:^')
        . ' will install latest compatible imagick extension version on all installed PHP 8 versions');
    exit;
} elseif ($config->license || $config->help === 'license') {
    $console->writeFile(__DIR__ . '/license.md');
    exit;
}

if ($config->config) {
    foreach ($config->config as $path) {
        $config->loadConfig($path);
    }
}

$installers = [
    new PhpInstaller($console),
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

$ran = false;
foreach ($installers as $installer) {
    $res = strtolower(substr(explode('\\', $installer::class)[2], 0, -9));
    if (isset($config->$res) && $config->$res) {
        $ran = true;
        $installer->run($config);
    }
}
if (!$ran) {
    $installers[0]->run($config); // default PHP
}
