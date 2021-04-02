<?php declare(strict_types = 1);

namespace Zoo;

use Dogma\Application\Colors as C;
use Dogma\Application\Configurator;
use Dogma\Application\Console;
use Dogma\Arr;
use Dogma\Check;
use Dogma\ComparisonResult;
use Dogma\Io\FileInfo;
use Dogma\Io\Io;
use Dogma\ShouldNotHappenException;
use Dogma\Str;
use Dogma\StrictBehaviorMixin;
use Dogma\Time\DateTime;
use Nette\Utils\Json;
use StreamContext;
use ZipArchive;
use function array_combine;
use function array_fill;
use function array_keys;
use function array_splice;
use function end;
use function explode;
use function file_exists;
use function implode;
use function in_array;
use function is_bool;
use function is_dir;
use function is_string;
use function iterator_to_array;
use function ksort;
use function max;
use function number_format;
use function preg_match;
use function str_contains;
use function str_pad;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function uksort;
use const PREG_SET_ORDER;
use const STR_PAD_LEFT;

class App
{
    use StrictBehaviorMixin;

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.66 Safari/537.36';

    private const FAMILIES = [
        3 => [0],
        4 => [0, 1, 2, 3, 4],
        5 => [1, 2, 3, 4, 5, 6],
        7 => [0, 1, 2, 3, 4],
        8 => [0],
    ];

    private const IGNORED_EXTENSION_FILE_TYPES = '/\\.(pdb|md|markdown|php|rst|txt|json)$/';
    private const IGNORED_EXTENSION_FILES = [
        // files
        'ChangeLog',
        'COPYING',
        'CREDITS',
        'INSTALL',
        'LICENSE',
        'LICENSE.BINYAML',
        'LICENSE.IMAGEMAGICK',
        'NOTICE',
        'README',
        'THIRD_PARTY_NOTICES',
        // dirs
        'contrib',
        'examples',
        'liblzf',
        'tests',
    ];

    private string $baseDir;

    /** @var Version[] $families */
    private array $families = [];

    /** @var Version[][] (string $family => string $version => Version) */
    private array $local = [];

    /** @var Version[][] (string $family => string $version => Version) */
    private array $remote = [];

    /** @var string[] (string $version => $url) */
    private array $urls = [];

    /** @var int[] (string $extension => int $downloads) */
    private array $extensions = [];

    /** @var Version[][][][] ($name => $phpVersion => $url => $version) */
    private array $localExtensions = [];

    /** @var Version[][][][] ($name => $phpMajorMinor => $safeBits => $url => $version) */
    private array $remoteExtensions = [];

    public function __construct(public Console $console)
    {}

    public function run(Configurator $config): void
    {
        $this->init($config);
        $this->loadLocalPhpVersions();

        if ($config->extension) {
            $this->loadPeclExtList();
            $this->loadCachedExtVersions();
            [$ext, $f] = explode(':', $config->extension . ':');
            $ext = $config->extension === true ? true : $ext;
            $extFilter = Version::parseExp($f ?: '**^');

            if (!$config->local && !$config->all && !$config->new
                && !$config->install && !$config->uninstall
                && !$config->on && !$config->off && !$config->info
            ) {
                $this->listExtensions($ext);
                return;
            }
            if ($config->local) {
                $this->listLocalExtensions($ext, self::filter($config->local));
            } elseif ($config->on) {
                $this->extensionOn($ext, self::filter($config->on));
            } elseif ($config->off) {
                $this->extensionOff($ext, self::filter($config->off));
            } elseif ($config->uninstall) {
                $this->uninstallExtension($ext, self::filter($config->uninstall));
            } else {
                $this->loadExtRemote($ext);
                if ($config->all) {
                    $this->listRemoteExtensions($ext, self::filter($config->all));
                } elseif ($config->new) {
                    $this->listNewExtensions($ext, self::filter($config->new));
                } elseif ($config->install) {
                    $this->installExtension($ext, $extFilter, self::filter($config->install), !$config->noAutoActivate);
                } elseif ($config->info) {
                    $this->infoExtension($ext, self::filter($config->info));
                }
            }
        } elseif ($config->local) {
            $this->listLocal(self::filter($config->local));
        } elseif ($config->configure) {
            $this->configure(self::filter($config->configure));
        } elseif ($config->select) {
            [$select, $level] = explode(':', $config->select . ':');
            $level = $level ?: 'global';
            Check::enum($level, 'global', 'major', 'minor');
            $this->select(self::filter($select), $level);
        } elseif ($config->uninstall) {
            $this->uninstall(self::filter($config->uninstall, '**_'));
        } elseif ($config->info) {
            $this->info(self::filter($config->info));
        } else {
            $this->loadRemote();

            if ($config->all) {
                $this->listRemote(self::filter($config->all));
            } elseif ($config->new) {
                $this->listNew(self::filter($config->new));
            } elseif ($config->install) {
                $this->install(self::filter($config->install, '**^'), !$config->noAutoSelect);
            }
        }
    }

    private function init(Configurator $config): void
    {
        $this->baseDir = (string) $config->baseDir;

        Io::createDirectory($this->baseDir . '/bin', Io::IGNORE);
        Io::createDirectory($this->baseDir . '/cache', Io::IGNORE);
        Io::createDirectory($this->baseDir . '/ext', Io::IGNORE);
        Io::createDirectory($this->baseDir . '/versions', Io::IGNORE);

        if ($config->clearCache) {
            $this->console->writeLn('Cleaning cache');
            Io::cleanDirectory($this->baseDir . '/cache');
        }
    }

    // list ------------------------------------------------------------------------------------------------------------

    private function listLocal(array $filters): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Local versions ({$filter->format()}):"));

            foreach ($this->families as $name => $family) {
                if ($filter->match($family)) {
                    $this->console->writeLn(C::white("  $name:"));
                }
                if (!isset($this->local[$name])) {
                    continue;
                }
                $ver = [];
                foreach ($this->local[$name] as $version) {
                    if ($filter->match($version)) {
                        $ver[] = $version->format();
                    }
                }
                if ($ver !== []) {
                    $this->console->writeLn('    ' . implode(', ', $ver));
                }
            }
        }
    }

    private function listRemote(array $filters): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Remote versions ({$filter->format()}):"));

            foreach ($this->families as $family => $familyVersion) {
                if ($filter->match($familyVersion)) {
                    $this->console->writeLn(C::white("  $family:"));
                }
                if (!isset($this->remote[$family])) {
                    continue;
                }
                $versions = [];
                foreach ($this->remote[$family] as $version) {
                    if ($filter->match($version)) {
                        $versions[] = $this->isInstalled($version) ? C::lyellow($version->format()) : $version->format();
                    }
                }
                if ($versions !== []) {
                    $this->console->writeLn('    ' . implode(', ', $versions));
                }
            }
        }
    }

    private function listNew(array $filters): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("New versions ({$filter->format()}):"));

            $allInstalled = true;
            $allUpToDate = true;
            foreach ($this->families as $family => $familyVersion) {
                if (!$filter->match($familyVersion)) {
                    continue;
                }
                $this->console->writeLn(C::white('  ' . $family . ':'));
                $latest = $this->getLatest($familyVersion);

                $versions = [];
                $versionUpToDate = false;
                foreach ($this->local[$family] ?? [] as $version) {
                    if ($version->patch === $latest) {
                        $versions[] = C::lgreen($version->format() . ' ✔ ');
                        $versionUpToDate = true;
                    } else {
                        $versions[] = C::lred($version->format());
                    }
                }
                if ($versions === []) {
                    $this->console->writeLn('    installed: ' . c::lyellow('-none-'));
                } else {
                    $this->console->writeLn('    installed: ', implode(', ', $versions));
                }

                if ($latest === null) {
                    $this->console->writeLn('    latest:    ' . C::lyellow('-unknown-'));
                } elseif ($versions === [] || $version->patch !== $latest) {
                    $this->console->writeLn('    latest:    ' . C::white($familyVersion->setPatch($latest)->format()));
                }
                $allInstalled = $allInstalled && $versions !== [];
                $allUpToDate = $allUpToDate && $versionUpToDate;
            }
            if (!$allInstalled) {
                $this->console->ln()->writeLn('Some versions are not installed.');
                exit(2);
            } elseif (!$allUpToDate) {
                $this->console->ln()->writeLn('Some versions are not up to date.');
                exit(1);
            }
        }
    }

    private function listExtensions(bool|string $extension): void {
        $this->console->writeLn(C::white('Available extensions:'));

        $this->console->writeLn('  Extension             Downloads  First      Last');
        foreach ($this->extensions as $name => [$url, $downloads]) {
            if (is_string($extension) && !str_contains($name, $extension)) {
                continue;
            }
            if (isset($this->remoteExtensions[$name])) {
                $maxKey = max(array_keys($this->remoteExtensions[$name]));
                $lastVer = end($this->remoteExtensions[$name][$maxKey]);
                $last = C::white(Str::before($maxKey, '.*')) . '-' . $lastVer->format();
                $minKey = min(array_keys($this->remoteExtensions[$name]));
                $firstVer = reset($this->remoteExtensions[$name][$minKey]);
                $first = C::white(Str::before($minKey, '.*')) . '-' . $firstVer->format();
            } else {
                $last = '?';
                $first = '?';
            }
            $name = str_pad($name, 20);
            $downloads = str_pad(number_format($downloads), 10, ' ', STR_PAD_LEFT);
            $this->console->writeLn(C::white('  ' . $name), ' ', $downloads, '  ', $first, '  ', $last);
        }
    }

    private function listLocalExtensions(string $ext, array $filters): void
    {
        foreach ($filters as $version) {
            $ext = Extensions::dllName($version, $ext);

            // todo
        }
    }

    private function listRemoteExtensions(string $extension, array $filters): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Remote versions of ") . C::lyellow($extension) . C::white(" extension for ({$filter->format()}):"));

            foreach ($this->remoteExtensions[$extension] as $family => $versions) {
                $familyVersion = Version::parseExp($family);
                if (!$filter->match($familyVersion)) {
                    continue;
                }
                $this->console->writeLn('  ', C::white($family), ':');
                $ver = [];
                /** @var Version $extVersion */
                foreach ($versions as $url => $extVersion) {
                    $ver[] = $extVersion->format();
                }
                $this->console->writeLn('    ' . implode(', ', $ver));
            }
        }
    }

    private function listNewExtensions(string $extension, array $filters): void
    {
        foreach ($filters as $filter) {
            // todo
        }
    }

    private function info(array $filters): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Info ({$filter->format()}):"));
            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version)) {
                        continue;
                    }
                    $this->console->writeLn('  ' . C::white($version->format()));

                    $key = $version->major . "." . $version->minor;
                    $extVersions = $this->probeExtensionVersions($version);
                    $extVersions += array_combine(Extensions::BUNDLED[$key], array_fill(0, count(Extensions::BUNDLED[$key]), false));

                    uksort($extVersions, static fn($a, $b) =>
                        in_array($a, Extensions::CORE[$key], true) <=> in_array($b, Extensions::CORE[$key], true)
                        ?: in_array($a, Extensions::BUNDLED[$key], true) <=> in_array($b, Extensions::BUNDLED[$key], true)
                        ?: strtolower($a) <=> strtolower($b));

                    foreach ($extVersions as $extension => $extVersion) {
                        $this->printExtension($version, $extVersion, $extension, $key);
                    }
                }
            }
        }
    }

    private function infoExtension(string $extension, array $filters): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Extension info ({$filter->format()}):"));
            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version)) {
                        continue;
                    }
                    $this->console->writeLn('  ' . C::white($version->format()));

                    $key = $version->major . "." . $version->minor;
                    $extVersions = $this->probeExtensionVersions($version);
                    $extVersions += array_combine(Extensions::BUNDLED[$key], array_fill(0, count(Extensions::BUNDLED[$key]), false));

                    $installed = false;
                    foreach ($extVersions as $ext => $extVersion) {
                        if ($ext !== $extension) {
                            continue;
                        }
                        $this->printExtension($version, $extVersion, $extension, $key);
                        $installed = true;
                    }
                    if (!$installed) {
                        $this->printExtension($version, null, $extension, $key);
                    }
                }
            }
        }
    }

    /**
     * @param Version $version
     * @param Version|bool|null $extVersion
     * @param string $extension
     * @param string $key
     */
    private function printExtension(Version $version, $extVersion, string $extension, string $key): void
    {
        if (!is_bool($extVersion) && $extVersion !== null && $extVersion->equalsWithoutVariant($version)) {
            $extVersion = Version::parseExp('*');
        }
        $extension = Extensions::internalName($version, $extension);
        if (in_array($extension, Extensions::CORE[$key], true)) {
            $from = C::lgreen('    core    ');
        } elseif (in_array($extension, Extensions::BUNDLED[$key], true)) {
            $from = C::lyellow('    bundled ');
        } else {
            $from = C::lcyan('    pecl    ');
        }
        $this->console->write($from);

        if ($extVersion) {
            $output = C::white($extension) . ': ' . $extVersion->formatShort();
        } elseif ($extVersion === false) {
            $output = $extension . ': ' . C::red('disabled');
        } else {
            $output = $extension . ': ' . C::red('not installed');
        }
        $this->console->write($output);

        $available = $this->remoteExtensions[$extension][$version->major . '.' . $version->minor . '.*'] ?? [];
        if ($available) {
            $this->console->write(C::lgray(' - available: '));
            foreach ($available as $av) {
                $this->console->write($av->format() . ', ');
            }
        } else {
            $this->console->write(C::red(' - not available'));
        }

        $this->console->ln();
    }

    // install/uninstall -----------------------------------------------------------------------------------------------

    private function install(array $filters, bool $autoSelect): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Installing ({$filter->format()}):"));

            foreach ($this->remote as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, self::FAMILIES, $this->remote)) {
                        continue;
                    }
                    if ($this->isInstalled($version)) {
                        $this->console->writeLn(C::white('  ' . $version->format()) . ' already installed');
                        continue 2;
                    }

                    $this->console->writeLn(C::white('  ' . $version->format()));

                    $this->installVersion($version);

                    if ($autoSelect && !$this->higherInstalled($version, $version->unsetPatch())) {
                        $this->selectVersion($version, $version->unsetPatch(), 'minor');
                    }
                    if ($autoSelect && !$this->higherInstalled($version, $version->unsetMinor())) {
                        $this->selectVersion($version, $version->unsetMinor(), 'major');
                    }
                    if ($autoSelect && !$this->higherInstalled($version, $version->unsetMajor())) {
                        $this->selectVersion($version, $version->unsetMajor(), 'global');
                    }
                }
            }
        }
    }

    private function installVersion(Version $version): void
    {
        $downloadUrl = $this->urls[$version->format()];

        $zipFile = "{$this->baseDir}/tmp.zip";
        $zip = $this->downloadZip($downloadUrl, $zipFile);
        $targetDir = $this->getVersionDir($version);
        Io::createDirectory($targetDir, Io::IGNORE);
        Io::cleanDirectory($targetDir, Io::RECURSIVE);

        $this->console->ln()->writeLn("    Extracting to /php{$version->format()}");
        $zip->extractTo($targetDir);
        $zip->close();
        Io::delete($zipFile);

        // fix php4 archive directory
        if (!file_exists("$targetDir/php.exe")) {
            $dirs = iterator_to_array(Io::scanDirectory($targetDir));
            if (count($dirs) === 1) {
                /** @var FileInfo $dir */
                $dir = end($dirs);
                if (file_exists("{$dir->getPath()}/php.exe")) {
                    Io::rename($dir->getPath(), "$this->baseDir/tmp");
                    Io::deleteDirectory($targetDir);
                    Io::rename("$this->baseDir/tmp", $targetDir);
                }
            }
        }

        $this->configureVersion($version);
    }

    private function uninstall(array $filters): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Uninstalling ({$filter->format()}):"));

            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, self::FAMILIES, $this->remote)) {
                        continue;
                    }
                    $this->console->writeLn('  Removing ' . C::white($version->format()));

                    Io::deleteDirectory($this->getVersionDir($version), Io::RECURSIVE);
                    unset($this->local[$version->family()][$version->format()]);

                    $patch = $this->getLatestInstalled($version->getFamily());
                    if ($patch !== null) {
                        // todo: unselect
                        time();
                    }
                }
            }
        }
    }

    private function installExtension(string $extension, Version $extensionFilter, array $filters, bool $autoActivate): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Installing extension $extension {$extensionFilter->format()} on {$filter->format()}:"));

            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, self::FAMILIES, $this->remote)) {
                        continue;
                    }

                    $this->console->writeLn("  " . C::white($version->format()));

                    $extension = Extensions::dllName($version, $extension);

                    if (Extensions::isCore($extension, $version)) {
                        $this->console->writeLn("    skipping (core)");
                        continue;
                    }
                    if (Extensions::isBundled($extension, $version)) {
                        $this->console->writeLn("    skipping (bundled)");
                        continue;
                    }

                    $available = [];
                    $selected = null;
                    $downloadUrl = null;
                    foreach ($this->remoteExtensions[$extension] as $for => $extVersions) {
                        $for = Version::parseExp($for);
                        if (!$for->match($version)) {
                            continue;
                        }
                        /** @var Version $extensionVersion */
                        foreach ($extVersions as $url => $extensionVersion) {
                            $available[] = $extensionVersion->format();
                            if ($extensionFilter->match($extensionVersion, [], ["$extensionVersion->major.$extensionVersion->minor.*-ts-32" => $extVersions])) {
                                $selected = $extensionVersion;
                                $downloadUrl = $url;
                            }
                        }
                    }
                    if ($available === []) {
                        $this->console->writeLn(C::lred("    not available"));
                    } elseif ($selected === null) {
                        $this->console->writeLn(C::lred("    no version matched. available: " . implode(', ', $available)));
                    } else {
                        $this->console->writeLn(C::white("    installing {$selected->format()}"));
                        $this->installExtensionVersion($version, $extension, $extensionVersion, $downloadUrl, $autoActivate);
                    }
                }
            }
        }
    }

    private function installExtensionVersion(Version $version, string $extension, Version $extensionVersion, string $downloadUrl, bool $autoActivate): void
    {
        $extDir = "$this->baseDir/ext/php_{$extension}_{$version->familySafe()}_{$extensionVersion->format()}";
        if (!is_dir($extDir)) {
            $zipFile = "$this->baseDir/tmp.zip";
            $zip = $this->downloadZip($downloadUrl, $zipFile);

            $this->console->ln()->writeLn("    Extracting to /ext/php_{$extension}_{$version->familySafe()}_{$extensionVersion->format()}");
            $zip->extractTo($extDir);
            $zip->close();
        }

        $this->linkExtension($extDir, $this->getVersionExtDir($version));

        if ($autoActivate) {
            $this->switchExtension($extension, $version, true);
        }
    }

    private function linkExtension(string $extensionDir, string $targetDir): void
    {
        foreach (Io::scanDirectory($extensionDir, Io::RECURSIVE) as $file) {
            $name = $file->getName();
            if (in_array($name, self::IGNORED_EXTENSION_FILES) || Str::match($name, self::IGNORED_EXTENSION_FILE_TYPES)) {
                continue;
            }
            Io::unlink($targetDir . '/' . $name, Io::IGNORE);
            Io::link($file, $targetDir . '/' . $name);
        }
    }

    private function uninstallExtension(string $extension, Version $extensionFilter, array $filters): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Installing extension $extension on {$filter->format()}:"));

            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, self::FAMILIES, $this->remote)) {
                        continue;
                    }

                    $this->console->writeLn("  " . C::white($version->format()));

                    $extension = Extensions::dllName($version, $extension);

                    $this->switchExtension($extension, $version, false);

                    if (Extensions::isCore($extension, $version)) {
                        $this->console->writeLn("    skipping (core)");
                        continue;
                    }
                    if (Extensions::isBundled($extension, $version)) {
                        $this->console->writeLn("    skipping (bundled)");
                        continue;
                    }

                    $available = [];
                    $selected = null;
                    $downloadUrl = null;
                    foreach ($this->remoteExtensions[$extension] as $for => $extVersions) {
                        $for = Version::parseExp($for);
                        if (!$for->match($version)) {
                            continue;
                        }
                        /** @var Version $extensionVersion */
                        foreach ($extVersions as $url => $extensionVersion) {
                            $available[] = $extensionVersion->format();
                            if ($extensionFilter->match($extensionVersion, [], ["$extensionVersion->major.$extensionVersion->minor.*-ts-32" => $extVersions])) {
                                $selected = $extensionVersion;
                                $downloadUrl = $url;
                            }
                        }
                    }
                    if ($available === []) {
                        $this->console->writeLn(C::lred("    not available"));
                    } elseif ($selected === null) {
                        $this->console->writeLn(C::lred("    no version matched. available: " . implode(', ', $available)));
                    } else {
                        $this->console->writeLn(C::white("    installing {$selected->format()}"));
                        $this->uninstallExtensionVersion($version, $extension, $extensionVersion, $downloadUrl);
                    }
                }
            }
        }
    }

    private function uninstallExtensionVersion(Version $version, string $extension, Version $extensionVersion, string $downloadUrl): void
    {
        $this->switchExtension($extension, $version, false);

        $extDir = "$this->baseDir/ext/php_{$extension}_{$version->familySafe()}_{$extensionVersion->format()}";
        if (!is_dir($extDir)) {
            $zipFile = "$this->baseDir/tmp.zip";
            $zip = $this->downloadZip($downloadUrl, $zipFile);

            $this->console->ln()->writeLn("    Extracting to /ext/php_{$extension}_{$version->familySafe()}_{$extensionVersion->format()}");
            $zip->extractTo($extDir);
            $zip->close();
        }

        $this->unlinkExtension($extDir, $this->getVersionExtDir($version));
    }

    private function unlinkExtension(string $extensionDir, string $targetDir): void
    {
        foreach (Io::scanDirectory($extensionDir, Io::RECURSIVE) as $file) {
            $name = $file->getName();
            if (in_array($name, self::IGNORED_EXTENSION_FILES) || Str::match($name, self::IGNORED_EXTENSION_FILE_TYPES)) {
                continue;
            }
            Io::unlink( $targetDir . '/' . $name, Io::IGNORE);
        }
    }

    // configuring -----------------------------------------------------------------------------------------------------

    private function configure(array $filters): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Configuring ({$filter->format()}):"));

            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, self::FAMILIES, $this->remote)) {
                        continue;
                    }
                    $this->console->writeLn("  Configuring " . C::white($version->format()));

                    $this->configureVersion($version);
                }
            }
        }
    }

    private function configureVersion(Version $version): void
    {
        $versionDir = $this->getVersionDir($version);

        // common config for all versions matching x.y.*
        $iniPath = $this->getConfigIniPath($version);
        if (!file_exists($iniPath)) {
            Io::copy(Io::existing(
                $versionDir . "/php.ini-production",
                $versionDir . "/php.ini-recommended",
                $versionDir . "/php.ini-optimized",
                $versionDir . "/php.ini",
                $versionDir . "/php.ini-development",
                $versionDir . "/php.ini-dist",
                $versionDir . "/php3.ini",
            ), $iniPath);
        }

        // hardlink php.ini
        $versionIniPath = $this->getVersionIniPath($version);
        Io::unlink($versionIniPath, Io::IGNORE);
        Io::link($iniPath, $versionIniPath);

        // setup .sh and .bat files
        $this->writeBinFiles($version, 3);
    }

    private function extensionOn(string $ext, array $filters): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Turn extension $ext ON ({$filter->format()}):"));

            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, self::FAMILIES, $this->local)) {
                        continue;
                    }
                    $this->switchExtension($ext, $version, true);
                }
            }
        }
    }

    private function extensionOff(string $ext, array $filters): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Turn extension $ext OFF ({$filter->format()}):"));

            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, self::FAMILIES, $this->local)) {
                        continue;
                    }
                    $this->console->writeLn(C::white("  {$version->format()}:"));
                    $this->switchExtension($ext, $version, false);
                }
            }
        }
    }

    private function switchExtension(string $ext, Version $version, bool $on): void
    {
        $prefix = Extensions::getIniPrefix($version);
        $suffix = Extensions::getIniSuffix($version);

        $ini = $this->getVersionIniPath($version);
        if (!file_exists($ini)) {
            $this->console->writeLn(C::red("    no php.ini"));
            return;
        }
        $extension = $this->getVersionExtDir($version) . "/" . Extensions::getFilePrefix($version) . $ext . '.dll';
        if (!file_exists($extension)) {
            $this->console->writeLn(C::yellow("    not installed"));
            return;
        }

        $lines = Io::readLines($ini);
        $inserted = false;
        // switch in place
        foreach ($lines as $i => $line) {
            $match = Str::match($line, "~^\\s*;?\\s*extension\\s*=\\s*(?:$prefix)?$ext(.*?)(?:$suffix)?~");
            if ($match !== null) {
                $ver = $match[1];
                $lines[$i] = $on
                    ? "extension=$prefix$ext$ver$suffix"
                    : ";extension=$prefix$ext$ver$suffix";

                if ($on && !$this->extensionExists($ext . $ver, $version)) {
                    $this->console->writeLn(C::red("Extension $ext$ver does not seem to be installed on {$version->format()}."));
                }

                $inserted = true;
            }
        }

        $insert = $on
            ? "extension=$prefix$ext$suffix"
            : ";extension=$prefix$ext$suffix";

        // insert at the end of extensions list
        if (!$inserted) {
            foreach ($lines as $i => $line) {
                if ($line === "; Module Settings ;") {
                    array_splice($lines, $i - 2, 0, [$insert]);
                    $inserted = true;
                }
            }
        }

        // insert at the end of file
        if (!$inserted) {
            $lines[] = $insert;
        }

        if ($on && !$this->extensionExists($ext, $version)) {
            $this->console->writeLn(C::red("Extension $ext does not seem to be installed on {$version->format()}."));
        }

        Io::write($ini, implode("\n", $lines));

        $this->console->writeLn(C::lgreen($on ? "    turned on" : "    turned off"));
    }

    private function extensionExists(string $extension, Version $version): bool
    {
        $info = Io::getInfo($this->getVersionExtDir($version) . '/' . Extensions::getFilePrefix($version) . $extension . '.dll');

        return $info->exists();
    }

    private function writeBinFiles(Version $version, int $level): void
    {
        $binPath = $this->getBinPath($version, $level);

        // cli
        Io::write($binPath, "#!/usr/bin/env sh\n"
            . "\"{$this->getVersionBinaryPath($version)}\" \"$@\"\n"
        );
        Io::write($binPath . ".bat", "@echo OFF\n"
            . "setlocal DISABLEDELAYEDEXPANSION\n"
            . "{$this->getVersionBinaryPath($version)} %*\n"
        );

        // cgi
        if ($version->major >= 5) {
            Io::write($binPath . '-cgi', "#!/usr/bin/env sh\n"
                . "\"{$this->getVersionCgiBinaryPath($version)}\" \"$@\"\n"
            );
            Io::write($binPath . "-cgi.bat", "@echo OFF\n"
                . "setlocal DISABLEDELAYEDEXPANSION\n"
                . "{$this->getVersionCgiBinaryPath($version)} %*\n"
            );
        }
    }

    // switching versions ----------------------------------------------------------------------------------------------

    private function select(array $filters, string $level): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Selecting as default ({$filter->format()}):"));

            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, self::FAMILIES, $this->local)) {
                        continue;
                    }
                    $this->selectVersion($version, $filter, $level);
                }
            }
        }
    }

    private function selectVersion(Version $version, Version $filter, string $level): void
    {
        // phpXY
        if (!$this->higherInstalled($version, $filter, $version->unsetPatch())) {
            $this->console->writeLn('  Selected ' . C::white($version->format()) . " as default for " . C::white("php{$version->major}{$version->minor}"));
            $this->writeBinFiles($version, 2);
        }

        // phpX
        if (!$this->higherInstalled($version, $filter, $version->unsetMinor())) {
            if ($level === 'major' || $level === 'global') {
                $this->console->writeLn('  Selected ' . C::white($version->format()) . " as default for " . C::white("php{$version->major}"));
                $this->writeBinFiles($version, 1);
            }
        }

        // php
        if (!$this->higherInstalled($version, $filter)) {
            if ($level === 'global') {
                $this->console->writeLn('  Selected ' . C::white($version->format()) . " as default for " . C::white("php"));
                $this->writeBinFiles($version, 0);
            }
        }
    }

    // helpers ---------------------------------------------------------------------------------------------------------

    private function getVersionDir(Version $version): string
    {
        return $this->baseDir . '/versions/php' . $version->format();
    }

    private function getVersionExtDir(Version $version): string
    {
        return $this->baseDir . '/versions/php' . $version->format() . Extensions::getDir($version);
    }

    private function getVersionBinaryPath(Version $version): string
    {
        return $this->baseDir . '/versions/php' . $version->format() . '/php.exe';
    }

    private function getVersionCgiBinaryPath(Version $version): string
    {
        return $this->baseDir . '/versions/php' . $version->format() . '/php-cgi.exe';
    }

    private function getVersionIniPath(Version $version): string
    {
        return $this->baseDir . '/versions/php' . $version->format() . '/php.ini';
    }

    private function getConfigIniPath(Version $version): string
    {
        return $this->baseDir . "/config/php{$version->major}{$version->minor}.ini";
    }

    private function getBinPath(Version $version, int $level): string
    {
        if ($level === 0) {
            return $this->baseDir . "/bin/php";
        } elseif ($level === 1) {
            return $this->baseDir . "/bin/php{$version->major}";
        } elseif ($level === 2) {
            return $this->baseDir . "/bin/php{$version->major}{$version->minor}";
        } elseif ($level === 3) {
            return $this->baseDir . "/bin/php{$version->major}{$version->minor}{$version->patch}";
        } elseif ($level === 4) {
            return $this->baseDir . "/bin/php{$version->major}{$version->minor}{$version->patch}" . ($version->safe ? "ts" : "");
        } elseif ($level === 5) {
            return $this->baseDir . "/bin/php{$version->major}{$version->minor}{$version->patch}" . ($version->safe ? "ts" : "") . $version->bits;
        } else {
            throw new ShouldNotHappenException("Max level is 5.");
        }
    }

    private function getLatest(Version $family): ?int
    {
        return isset($this->remote[$family->family()])
            ? end($this->remote[$family->family()])->patch
            : null;
    }

    private function isAvailable(Version $version): bool
    {
        return isset($this->remote[$version->family()][$version->format()]);
    }

    private function higherAvailable(Version $version, ?Version $filter = null): bool
    {
        $family = $version->getFamily();
        if (!isset($this->remote[$family->format()])) {
            return false;
        }

        foreach ($this->remote[$family->format()] as $remote) {
            if ($filter !== null && !$filter->match($remote)) {
                continue;
            }
            if ($remote->compare($version) === ComparisonResult::GREATER) {
                return true;
            }
        }

        return false;
    }

    private function isInstalled(Version $version): bool
    {
        return isset($this->local[$version->family()][$version->format()]);
    }

    private function getLatestInstalled(Version $family): ?int
    {
        if (!isset($this->local[$family->family()])) {
            return null;
        }

        $last = end($this->local[$family->family()]);

        return $last !== false ? $last->patch : null;
    }

    private function higherInstalled(Version $version, Version ...$filters): bool
    {
        foreach ($this->local as $versions) {
            foreach ($versions as $local) {
                foreach ($filters as $filter) {
                    if (!$filter->match($local)) {
                        continue 2;
                    }
                }
                if ($local->compare($version) === ComparisonResult::GREATER) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param Version $version
     * @return Version[]
     */
    private function probeExtensionVersions(Version $version): array
    {
        // todo: does not work on 3.x and 4.x - no -r parameter
        $output = [];
        $command = $version->major >= 6 || ($version->major === 5 && $version->minor >= 2)
            ? ' -r "$extensions = array_merge(get_loaded_extensions(), get_loaded_extensions(true)); foreach ($extensions as $ext) { $ref = new ReflectionExtension($ext); echo $ref->getName() . \'|\' . $ref->getVersion() . PHP_EOL; };" 2> nul'
            : ' -r "$extensions = get_loaded_extensions(); foreach ($extensions as $ext) { $ref = new ReflectionExtension($ext); echo $ref->getName() . \'|\' . $ref->getVersion() . PHP_EOL; };" 2> nul';
        exec($this->getVersionBinaryPath($version) . $command, $output);

        // installed and working
        $versions = [];
        foreach ($output as $line) {
            $match = Str::match($line, '~([a-zA-Z0-9_]+)\\|([0-9.]*)~');
            if ($match !== null) {
                $versions[strtolower($match[1])] = Version::parseExp($match[2] ?: '*', $version);
            }
        }

        // installed (disabled or not working)
        $dir = $this->getVersionExtDir($version);
        foreach (Io::scanDirectory($dir) as $file) {
            $match = Str::match($file->getName(), '/^php_([a-z]+).dll$/');
            if (!$match) {
                continue;
            }
            $extension = Extensions::internalName($version, $match[1]);
            if (!isset($versions[$extension])) {
                $versions[$extension] = false;
            }
        }

        // todo: scan php.ini for not working

        ksort($versions);

        return $versions;
    }

    /**
     * @param string|bool|float $expr
     * @param string|null $default
     * @return Version[]
     */
    private static function filter(string|bool|float $expr, ?string $default = null): array
    {
        if ($expr === true || $expr === '') {
            $expr = $default ?? '*';
        }
        $parts = explode(',', (string) $expr);
        $versions = [];
        foreach ($parts as $part) {
            $versions[] = Version::parseExp(trim($part));
        }

        return $versions;
    }

    // loaders ---------------------------------------------------------------------------------------------------------

    private function loadLocalPhpVersions(): void
    {
        // create list of families
        foreach (self::FAMILIES as $major => $minors) {
            foreach ($minors as $minor) {
                // no nts versions before 5.2 and no 64bit versions before 5.5
                if ($major > 5 || ($major === 5 && $minor >= 5)) {
                    $family = new Version($major, $minor, null, false, 64);
                    $this->families[$family->format()] = $family;
                }
                // no 64bit versions before 5.5
                if ($major > 5 || ($major === 5 && $minor >= 5)) {
                    $family = new Version($major, $minor, null, true, 64);
                    $this->families[$family->format()] = $family;
                }
                // no nts versions before 5.2
                if ($major > 5 || ($major === 5 && $minor >= 2)) {
                    $family = new Version($major, $minor, null, false, 32);
                    $this->families[$family->format()] = $family;
                }
                // always available
                $family = new Version($major, $minor, null, true, 32);
                $this->families[$family->format()] = $family;
            }
        }

        $versions = [];
        foreach (Io::scanDirectory($this->baseDir . '/versions') as $directory) {
            if (!$directory->isDirectory() || !str_starts_with($directory->getName(), 'php')) {
                continue;
            }
            $versions[] = Version::parseDir($directory->getName());
        }

        /** @var Version $version */
        foreach (Arr::sortComparable($versions) as $version) {
            $this->local[$version->family()][$version->format()] = $version;
        }
    }

    private function loadRemote(): void
    {
        $winNetBaseUrl = 'https://windows.php.net';
        $downloadsUrl = 'https://windows.php.net/download/';
        $qaUrl = 'https://windows.php.net/qa/';
        $archivesUrl = 'https://windows.php.net/downloads/releases/archives/';
        $museumWin32Url = 'https://museum.php.net/win32/';
        $museumPhp5Url = 'https://museum.php.net/php5/';

        $downloadsLinkRe = '~/downloads/(?:releases/|qa/)(?:archives/)?php-[0-9]+\\.[0-9]+\\.[0-9]+(?:RC[0-9]+)?(?:-nts)?-Win32[^.]+\\.zip~i';
        $museumLinkRe = '~php-[0-9]+\\.[0-9]+\\.[0-9]+(RC[0-9])?(-nts)?-Win32\\.zip~i';

        $urls = [];

        // downloads (latest versions)
        $cache = new FileInfo($this->baseDir . '/cache/php-downloads.html');
        if ($cache->exists() && $cache->getModifiedTime()->isAfter(new DateTime('-1 day'))) {
            $html = $cache->read();
        } else {
            $this->console->writeLn('Refreshing downloads');

            $html = Io::read($downloadsUrl, context: $this->createContext());
            $cache->write($html);
        }
        foreach (Str::matchAll($html, $downloadsLinkRe)[0] as $url) {
            $urls[] = $winNetBaseUrl . $url;
        }

        // qa (RC builds)
        $cache = new FileInfo($this->baseDir . '/cache/php-qa.html');
        if ($cache->exists() && $cache->getModifiedTime()->isAfter(new DateTime('-1 day'))) {
            $html = $cache->read();
        } else {
            $this->console->writeLn('Refreshing qa');

            $html = Io::read($qaUrl, context: $this->createContext());
            $cache->write($html);
        }
        foreach (Str::matchAll($html, $downloadsLinkRe)[0] as $url) {
            $urls[] = $winNetBaseUrl . $url;
        }

        // archives
        $cache = new FileInfo($this->baseDir . '/cache/php-archives.html');
        if ($cache->exists() && $cache->getModifiedTime()->isAfter(new DateTime('-1 week'))) {
            $html = $cache->read();
        } else {
            $this->console->writeLn('Refreshing archives');

            $html = Io::read($archivesUrl, context: $this->createContext());
            $cache->write($html);
        }
        foreach (Str::matchAll($html, $downloadsLinkRe)[0] as $url) {
            $urls[] = $winNetBaseUrl . $url;
        }

        // museum win32
        $cache = new FileInfo($this->baseDir . '/cache/php-museum-win32.html');
        if ($cache->exists() && $cache->getModifiedTime()->isAfter(new DateTime('-1 month'))) {
            $html = $cache->read();
        } else {
            $this->console->writeLn('Refreshing museum win32');

            $html = Io::read($museumWin32Url, context: $this->createContext());
            $cache->write($html);
        }
        foreach (Str::matchAll($html, $museumLinkRe)[0] as $url) {
            $urls[] = $museumWin32Url . $url;
        }

        // museum php5
        $cache = new FileInfo($this->baseDir . '/cache/php-museum-php5.html');
        if ($cache->exists() && $cache->getModifiedTime()->isAfter(new DateTime('-1 month'))) {
            $html = $cache->read();
        } else {
            $this->console->writeLn('Refreshing museum php5');

            $html = Io::read($museumPhp5Url, context: $this->createContext());
            $cache->write($html);
        }
        foreach (Str::matchAll($html, $museumLinkRe)[0] as $url) {
            $urls[] = $museumPhp5Url . $url;
        }

        // todo: snapshots?

        $versions = Arr::sortComparable(Arr::remap($urls, static fn (int $k, string $url) => [$url => Version::parseUrl($url)]));

        /** @var Version $version */
        foreach ($versions as $version) {
            $this->remote[$version->family()][$version->format()] = $version;
        }

        $this->urls = Arr::remap($versions, static fn (string $url, Version $version) => [$version->format() => $url]);
    }

    private function loadPeclExtList(): void
    {
        $extListBaseUrl = 'https://pecl.php.net/package/';
        $extListUrl = 'https://pecl.php.net/package-stats.php';
        $extListRe = '~"/package/([a-zA-Z0-9_]+)">[a-zA-Z0-9_]+</a></td>\\s*<td>([0-9,]+)</td>~';

        $cache = new FileInfo($this->baseDir . '/cache/php-pecl-extensions.html');
        if ($cache->exists() && $cache->getModifiedTime()->isAfter(new DateTime('-1 week'))) {
            $html = $cache->read();
        } else {
            $this->console->writeLn('Refreshing extension list');

            $html = Io::read($extListUrl, context: $this->createContext());
            $cache->write($html);
        }
        $exts = [];
        foreach (Str::matchAll($html, $extListRe, PREG_SET_ORDER) as $row) {
            [, $name, $downloads] = $row;
            $exts[$name] = [$extListBaseUrl . $name, (int) str_replace(',', '', $downloads)];
        }

        $this->extensions = $exts;
    }

    private function loadExtRemote(string $name): void
    {
        $cache = new FileInfo($this->baseDir . '/cache/pecl-' . $name . '.json');
        if ($cache->exists() && $cache->getModifiedTime()->isAfter(new DateTime('-1 week'))) {
            $json = $cache->read();
            $versions = Json::decode($json, Json::FORCE_ARRAY);
        } else {
            $this->console->writeLn("Refreshing $name extension versions");

            $url = "https://pecl.php.net/package/$name";
            $html = Io::read($url, context: $this->createContext());

            $versions = [];
            $verRe = '~/package/[a-zA-Z0-9_]+/([0-9]+\\.[0-9]+\\.[0-9]+)/windows~';
            foreach (Str::matchAll($html, $verRe)[1] as $extVer) {
                $url = "https://pecl.php.net/package/$name/$extVer/windows";
                $html = Io::read($url, context: $this->createContext());

                $versionRe = "~https://windows.php.net/downloads/pecl/releases/$name/$extVer/php_imagick-$extVer-([0-9]+\\.[0-9]+(?:-nts|-ts)?(?:-vc[0-9]+)?-(?:x64|x86))\\.zip~";
                foreach (Str::matchAll($html, $versionRe, PREG_SET_ORDER) as [$url, $ver]) {
                    $version = Version::parseUrl('php-'. $ver);
                    $versions[$version->family()][$url] = $extVer;
                }
            }

            ksort($versions);
            foreach ($versions as $i => $v) {
                ksort($v);
                $versions[$i] = $v;
            }

            $cache->write(Json::encode($versions));
        }

        foreach ($versions as $i => $v) {
            foreach ($v as $j => $version) {
                $versions[$i][$j] = Version::parseExp($version);
            }
        }

        $this->remoteExtensions[$name] = $versions;
    }

    private function loadCachedExtVersions(): void
    {
        foreach (Io::scanDirectory($this->baseDir . '/cache') as $file) {
            if (!str_starts_with($file->getName(), 'pecl-')) {
                continue;
            }

            $name = Str::between($file->getName(), 'pecl-', '.json');
            $json = $file->read();
            $versions = Json::decode($json, Json::FORCE_ARRAY);

            foreach ($versions as $i => $v) {
                foreach ($v as $j => $version) {
                    $versions[$i][$j] = Version::parseExp($version);
                }
            }

            $this->remoteExtensions[$name] = $versions;
        }
    }

    private function downloadZip(string $downloadUrl, string $zipFile): ZipArchive
    {
        $last = 0;
        $context = $this->createContext();
        $context->onProgress(function (StreamContext $context, int $progress, int $size) use (&$last): void {
            if ($last === 0 || $progress - $last > 100000) {
                $this->console->write('.');
                $last = $progress;
            }
        });
        $this->console->writeLn("    Downloading $downloadUrl");
        $data = Io::read($downloadUrl, 0, null, $context);

        Io::write($zipFile, $data);

        $zip = new ZipArchive();
        $zip->open($zipFile);

        return $zip;
    }

    private function createContext(): StreamContext
    {
        $context = StreamContext::createHttp();
        $context->setUserAgent(self::USER_AGENT);

        return $context;
    }

}
