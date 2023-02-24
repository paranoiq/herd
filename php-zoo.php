<?php declare(strict_types = 1);

namespace Zoo;

use Dogma\Application\Colors as C;
use Dogma\Application\Configurator;
use Dogma\Application\Console;
use Dogma\Debug\Dumper;
use Dogma\Io\Io;
use Tracy\Debugger;
use function class_exists;
use function get_class;
use function phpversion;

if (class_exists(Dumper::class)) {
    Dumper::$objectFormatters[Version::class] = static function (Version $version): string {
        return Dumper::class(get_class($version)) . Dumper::bracket('(')
            . Dumper::value($version->format())
            . Dumper::bracket(')') . ' ' . Dumper::info('// #' . Dumper::objectHash($version));
    };
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';

    $logDir = __DIR__ . '/log';
    Io::createDirectory($logDir, Io::IGNORE);

    Debugger::enable(Debugger::DEVELOPMENT, $logDir);
    Debugger::$maxDepth = 8;
    Debugger::$maxLength = 1000;
    Debugger::$showLocation = true;
} else {
    echo 'PHP-Zoo: Run `composer install` to install dependencies.';
    exit(2);
}

$arguments = [
        'Actions:',
    'local' =>          ['l', Configurator::FLAG_VALUE, 'list local versions', 'expr'],
    'all' =>            ['a', Configurator::FLAG_VALUE, 'list all (remote) versions', 'expr'],
    'new' =>            ['n', Configurator::FLAG_VALUE, 'check for new versions', 'expr'],
    'install' =>        ['i', Configurator::FLAG_VALUE, 'install versions', 'expr'],
    'uninstall' =>      ['U', Configurator::FLAG_VALUE, 'uninstall versions', 'expr'],
    'select' =>         ['s', Configurator::VALUE, 'select version as default (levels: global|major|minor)', 'expr[:level]'],
    'info' =>           ['f', Configurator::VALUE, 'info about version', 'expr'],
    'extension' =>      ['e', Configurator::FLAG_VALUE, 'list/install/uninstall extensions (combine with other actions)', 'name[:expr]'],
        'Configuration:',
    'on' =>             ['o', Configurator::FLAG_VALUE, 'extension ON on versions', 'expr'],
    'off' =>            ['O', Configurator::FLAG_VALUE, 'extension OFF on versions', 'expr'],
    'configure' =>      ['c', Configurator::FLAG_VALUE, 'update php.ini files from templates', 'expr'],
    'template' =>       ['t', Configurator::FLAG, 'apply action on configuration templates instead (on/off/jitOn/JitOff)'],
    //'jitOn' =>          ['j', Configurator::FLAG_VALUE, 'JIT compiler ON on versions (PHP 8)', 'expr'],
    //'jitOff' =>         ['J', Configurator::FLAG_VALUE, 'JIT compiler OFF on versions (PHP 8)', 'expr'],
    //'startCgi' =>       ['g', Configurator::VALUE, 'start CGI worker from version', 'version[:port]'],
    //'stopCgi' =>        ['G', Configurator::FLAG, 'stop CGI worker (kill process)'],
    //'use' =>            ['u', Configurator::VALUE, 'use version in local environment (local PATH)', 'expr'],
        'Options:',
    'config' =>         ['', Configurator::VALUES, 'configuration files', 'paths'],
    'baseDir' =>        ['b', Configurator::VALUE, 'base install directory', 'path'],
    //'cgiPort' =>        ['p', Configurator::VALUE, 'default CGI port', 'port'],
    'noAutoSelect' =>   ['S', Configurator::FLAG, 'prevent automatic selection of higher version on install'],
    'noAutoActivate' => ['A', Configurator::FLAG, 'prevent automatic activation of extensions on install'],
    'clearCache' =>     ['', Configurator::FLAG, 'clear versions cache before running new/install/uninstall'],
        'Help:',
    'help' =>           ['h', Configurator::FLAG, 'show help'],
    'license' =>        ['', Configurator::FLAG, 'show license'],
        'Output:',
    'noColors' =>       ['', Configurator::FLAG, 'without colors'],
    'noLogo' =>         ['', Configurator::FLAG, 'without logo'],
];
$defaults = [
    'baseDir' => 'C:/tools/php',
];
$config = new Configurator($arguments, $defaults);
$config->loadCliArguments();

$console = new Console();

if ($config->noColors) {
    C::$off = true;
}

if (!$config->noLogo) {
    $console->writeLn("  ┌────────┬──┐      ");
    $console->writeLn("  │     ┌┘ │ ▋│┌─┐   " . C::lgreen("HERD by @paranoiq"));
    $console->writeLn(" ┌┤     │  │  ││┌┘   " . "PHP installer/manager");
    $console->writeLn(" ││     └──┘│ └┘│    ");
    $console->writeLn(" ┘│         └┬──┘    " . "PHP " . phpversion());
    $console->writeLn("  │ ┌─┬──┐ ┌ │       ");
    $console->writeLn("▔▔└─┴─┘▔▔└─┴─┘▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔▔");
    $console->ln();
}

if ($config->help === true || (!$config->hasValues() && (!$config->config))) {
    $console->writeLn('Tool for downloading, updating and configuring many PHP versions at once (Windows only).')->ln();

    $console->writeLn('Usage: ' . C::lyellow('php-zoo --action "versions" [options]'))->ln();

    $console->write($config->renderHelp());

    $console->writeLn('Versions expression: '
        . C::lyellow('(n|*|^|_)') . C::white('.[n|*|^|_]') . C::lyellow('.[n|*|^|_]')
        . C::white('-[nts|ts|*]') . C::lyellow('-[64|32|*]'));
    $console->writeLn('                     (major)   (minor)   (patch)   (threads)  (architecture)');
    $console->writeLn(C::white('  n'), ' = version number');
    $console->writeLn(C::white('  *'), ' = any version');
    $console->writeLn(C::white('  ^'), ' = last version');
    $console->writeLn(C::white('  _'), ' = any except last version');
    $console->writeLn(C::white('  nts|ts'), ' = non thread safe (Nginx, CGI, CLI, faster) or thread safe (Apache). defaults to ', C::white('nts'), '.');
    $console->writeLn(C::white('  64|32'), ' = 64bit or 32bit version. defaults to ', C::white('64'), '.');
    $console->writeLn('  More expressions can be used with ' . C::white('","') . ' as separator.');

    $console->ln()->writeLn('Examples:');
    $console->writeLn(C::lyellow('  php-zoo -i "**^"') . ' will install all latest versions in nts-64 variant (5.5 to 8.0).');
    $console->writeLn(C::lyellow('  php-zoo -U 73_32,74_32') . ' will uninstall all obsolete versions in nts-32 variants of 7.3 and 7.4.');
    $console->writeLn(C::lyellow('  php-zoo -n 7ts') . ' will check if you have all last versions of 7.x-ts-64 installed.');

    $console->ln()->writeLn('Installing extensions:');
    $console->writeLn('  To list/install/uninstall extensions add ' . C::white('--extension')
        . ' parameter and the specified action will be performed on extension instead. Eg. '
        . C::lyellow('php-zoo -i 7 -e imagick:^')
        . ' will install latest compatible imagick extension version on all installed PHP 7 versions');
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

$application = new App($console);
$application->run($config);

/*
$x = '
    n = version number,
    n-n = range of versions,
    * = any,
    ** = any including RC,
    *** = any including patches,
    ^ = last stable,
    ^^ = last RC,
    ^^^ = last patch,
    ! = negate expression';
*/