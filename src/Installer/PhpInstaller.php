<?php

namespace Herd\Installer;

use Dogma\Application\Colors as C;
use Dogma\Application\Configurator;
use Dogma\Application\Console;
use Dogma\Arr;
use Dogma\Check;
use Dogma\ComparisonResult;
use Dogma\Io\FileInfo;
use Dogma\Io\Io;
use Dogma\Re;
use Dogma\Str;
use Dogma\Time\DateTime;
use Dogma\VersionFilter;
use Herd\HttpHelper;
use Herd\Info\PhpInfo;
use Herd\Version;
use Nette\Utils\Json;
use RuntimeException;
use function array_combine;
use function array_fill;
use function end;
use function explode;
use function file_exists;
use function implode;
use function in_array;
use function intval;
use function iterator_to_array;
use function preg_match;
use function strtolower;
use function uksort;

class PhpInstaller
{

    public Console $console;

    public HttpHelper $httpHelper;

    private string $baseDir;

    /** @var array<string, Version> $families */
    public array $families = [];

    /** @var array<string, array<string, Version>> ($family => $versionString => $versionObject) */
    public array $local = [];

    /** @var array<string, array<string, Version>> ($family => $versionString => $versionObject) */
    public array $remote = [];

    /** @var array<string, string> ($version => $url) */
    public array $downloadUrls = [];

    /** @var array<string, Version> ($alias => $version) */
    public array $selected = [];

    /** @var array<string, array{string, int}> ($extension => [$url, $downloads]) */
    public array $extensions = [];

    /** @var array<string, array<string, array<string, Version>>> ($name => $majorMinor => $url => $version) */
    public array $localExtensions = [];

    // todo: bits?
    /** @var array<string, array<string, array<string, Version>>> ($name => $majorMinor => $url => $version) */
    public array $remoteExtensions = [];

    public function __construct(Console $console,)
    {
        $this->console = $console;
        $this->httpHelper = new HttpHelper($console);
    }

    public function run(Configurator $config): void
    {
        $this->init($config);
        $this->loadLocalVersions();

        $this->console->writeLn(C::lcyan("PHP installs"));

        if ($config->extension) {
            $this->loadPeclExtensionsList();
            $this->loadCachedExtensionVersions();
            [$ext, $f] = explode(':', $config->extension . ':');
            // default: install last version of all branches / uninstall all old versions of all branches
            $extFilter = VersionFilter::parseExpression($f ?: ($config->uninstall ? '**_' : '**^'));

            if (!$config->local && !$config->all && !$config->new
                && !$config->install && !$config->uninstall
                && !$config->on && !$config->off && !$config->info
            ) {
                $this->listExtensions($ext);
                return;
            }
            if ($config->local) {
                $this->listLocalExtensions($ext, VersionFilter::parse($config->local));
            } elseif ($config->on) {
                $this->extensionOn($ext, VersionFilter::parse($config->on));
            } elseif ($config->off) {
                $this->extensionOff($ext, VersionFilter::parse($config->off));
            } elseif ($config->uninstall) {
                $this->uninstallExtension($ext, $extFilter, VersionFilter::parse($config->uninstall), $config->test);
            } else {
                $this->loadRemoteExtensions([$ext]); // @phpstan-ignore argument.type (TODO: WTF?)
                if ($config->all) {
                    $this->listRemoteExtensions($ext, VersionFilter::parse($config->all));
                } elseif ($config->new) {
                    $this->listNewExtensions($ext, VersionFilter::parse($config->new));
                } elseif ($config->install) {
                    $this->installExtension($ext, $extFilter, VersionFilter::parse($config->install), !$config->noAutoActivate, $config->test);
                } elseif ($config->info) {
                    $this->infoExtension($ext, VersionFilter::parse($config->info));
                }
            }
        } elseif ($config->local) {
            $this->listLocal(VersionFilter::parse($config->local));
        } elseif ($config->configure) {
            $this->configure(VersionFilter::parse($config->configure), $config->test);
        } elseif ($config->default) {
            [$select, $level] = explode(':', $config->default . ':');
            $level = $level ?: 'global';
            Check::enum($level, 'global', 'major', 'minor');
            $this->select(VersionFilter::parse($select), $level);
        } elseif ($config->uninstall) {
            $this->uninstall(VersionFilter::parse($config->uninstall, '**_'), $config->test);
        } elseif ($config->info) {
            $this->info(VersionFilter::parse($config->info));
        } else {
            $this->loadRemoteVersions();

            if ($config->all) {
                $this->listRemote(VersionFilter::parse($config->all));
            } elseif ($config->new) {
                $this->listNew(VersionFilter::parse($config->new));
            } elseif ($config->install) {
                $this->install(VersionFilter::parse($config->install, '**^'), !$config->noAutoSelect, $config->test);
            }
        }
    }

    public function init(Configurator $config): void
    {
        $this->baseDir = (string) $config->baseDir;

        Io::createDirectory($this->baseDir . '/bin', Io::IGNORE);
        Io::createDirectory($this->baseDir . '/cache/php', Io::IGNORE);
        Io::createDirectory($this->baseDir . '/ext', Io::IGNORE);
        Io::createDirectory($this->baseDir . '/versions', Io::IGNORE);

        $self = dirname(__DIR__, 2) . '/herd.php';
        Io::write($this->baseDir . '/bin/herd', "#!/usr/bin/env sh\nphp82 \"{$self}\" \"$@\"");
        Io::write($this->baseDir . '/bin/herd.bat', "@echo OFF\nsetlocal DISABLEDELAYEDEXPANSION\nphp82 \"{$self}\" %*");

        if ($config->refresh) {
            $this->console->writeLn('Cleaning cache');
            Io::cleanDirectory($this->baseDir . '/cache/php');
        }
    }

    // list ------------------------------------------------------------------------------------------------------------

    /**
     * @param array<VersionFilter> $filters
     */
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
                        $ver[] = $version->format6() . $this->getAliases($version);
                    }
                }
                if ($ver !== []) {
                    $this->console->writeLn('    ' . implode(', ', $ver));
                }
            }
        }
    }

    private function getAliases(Version $version): string
    {
        $aliases = [];
        foreach ($this->selected as $alias => $sVersion) {
            if (!$version->equals($sVersion)) {
                continue;
            }
            if (preg_match('~php(\d{3})~', $alias)) {
                // ignore
                continue;
            } elseif (preg_match('~php\d{2}~', $alias)) {
                $aliases[] = C::blue($alias);
            } elseif (preg_match('~php\d~', $alias)) {
                $aliases[] = C::lblue($alias);
            } else {
                $aliases[] = C::white($alias, C::BLUE);
            }
        }

        return $aliases !== [] ? " as " . implode(', ', $aliases) : '';
    }

    /**
     * @param array<VersionFilter> $filters
     */
    private function listRemote(array $filters): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Remote versions ({$filter->format()}):"));

            foreach ($this->families as $family => $familyVersion) {
                if ($filter->match($familyVersion)) {
                    $this->console->writeLn(C::white("  {$family}:"));
                }
                if (!isset($this->remote[$family])) {
                    continue;
                }
                $versions = [];
                foreach ($this->remote[$family] as $version) {
                    if ($filter->match($version)) {
                        $versions[] = $this->isInstalled($version) ? C::lyellow($version->format6()) : $version->format6();
                    }
                }
                if ($versions !== []) {
                    $this->console->writeLn('    ' . implode(', ', $versions));
                }
            }
        }
    }

    /**
     * @param array<VersionFilter> $filters
     */
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
                        $versions[] = C::lgreen($version->format6()) . $this->getAliases($version);
                        $versionUpToDate = true;
                    } else {
                        $versions[] = C::lred($version->format6()) . $this->getAliases($version);
                    }
                }
                if ($versions === []) {
                    $this->console->writeLn('    installed: ' . c::lyellow('-none-'));
                } else {
                    $this->console->writeLn('    installed: ', implode(', ', $versions));
                }

                if ($latest === null) {
                    $this->console->writeLn('    latest:    ' . C::lyellow('-unknown-'));
                } elseif ($versions === [] || (isset($version) && $version->patch !== $latest)) {
                    $this->console->writeLn('    latest:    ' . C::white($familyVersion->setPatch($latest)->format6()));
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

    /**
     * @param array<VersionFilter> $filters
     */
    private function info(array $filters): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Info ({$filter->format()}):"));
            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version)) {
                        continue;
                    }
                    $this->console->writeLn('  ' . C::white($version->format6()));

                    $key = $version->major . "." . $version->minor;
                    $extVersions = $this->probeExtensions($version);
                    $extVersions += array_combine(PhpInfo::BUNDLED_EXTENSIONS[$key], array_fill(0, count(PhpInfo::BUNDLED_EXTENSIONS[$key]), false));

                    uksort($extVersions, static fn($a, $b) =>
                        in_array($a, PhpInfo::CORE_EXTENSIONS[$key], true) <=> in_array($b, PhpInfo::CORE_EXTENSIONS[$key], true)
                        ?: in_array($a, PhpInfo::BUNDLED_EXTENSIONS[$key], true) <=> in_array($b, PhpInfo::BUNDLED_EXTENSIONS[$key], true)
                        ?: strtolower($a) <=> strtolower($b));

                    foreach ($extVersions as $extension => $extVersion) {
                        $this->printExtension($version, $extVersion, $extension, $key);
                    }
                }
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
                $last = C::white(Str::before($maxKey, '.*')) . '-' . $lastVer->format6();
                $minKey = min(array_keys($this->remoteExtensions[$name]));
                $firstVer = reset($this->remoteExtensions[$name][$minKey]);
                $first = C::white(Str::before($minKey, '.*')) . '-' . $firstVer->format6();
            } else {
                $last = '?';
                $first = '?';
            }
            $name = str_pad($name, 20);
            $downloads = str_pad(number_format($downloads), 10, ' ', STR_PAD_LEFT);
            $this->console->writeLn(C::white('  ' . $name), ' ', $downloads, '  ', $first, '  ', $last);
        }
    }

    /**
     * @param array<VersionFilter> $filters
     */
    private function listLocalExtensions(string $ext, array $filters): void
    {
        foreach ($filters as $version) {
            $ext = PhpInfo::extensionDllName($version, $ext);

            // todo
        }
    }

    /**
     * @param array<VersionFilter> $filters
     */
    private function listRemoteExtensions(string $extension, array $filters): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Remote versions of ") . C::lyellow($extension) . C::white(" extension for ({$filter->format()}):"));

            foreach ($this->remoteExtensions[$extension] as $family => $versions) {
                $familyVersion = Version::parseExpression($family);
                if (!$filter->match($familyVersion)) {
                    continue;
                }
                $this->console->writeLn('  ', C::white($family), ':');
                $ver = [];
                foreach ($versions as $extVersion) {
                    $ver[] = $extVersion->format6();
                }
                $this->console->writeLn('    ' . implode(', ', $ver));
            }
        }
    }

    /**
     * @param array<VersionFilter> $filters
     */
    private function listNewExtensions(string $extension, array $filters): void // @phpstan-ignore void.pure (todo)
    {
        foreach ($filters as $filter) {
            // todo
        }
    }

    /**
     * @param array<VersionFilter> $filters
     */
    private function infoExtension(string $extension, array $filters): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Extension info ({$filter->format()}):"));
            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version)) {
                        continue;
                    }
                    $this->console->writeLn('  ' . C::white($version->format6()));

                    $key = $version->major . "." . $version->minor;
                    $extVersions = $this->probeExtensions($version);
                    $extVersions += array_combine(PhpInfo::BUNDLED_EXTENSIONS[$key], array_fill(0, count(PhpInfo::BUNDLED_EXTENSIONS[$key]), false));

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

    private function printExtension(Version $version, Version|bool|null $extVersion, string $extension, string $key): void
    {
        if (!is_bool($extVersion) && $extVersion !== null && $extVersion->equals3($version)) {
            $extVersion = Version::parseExpression('*');
        }
        $extension = PhpInfo::extensionInternalName($version, $extension);
        if (in_array($extension, PhpInfo::CORE_EXTENSIONS[$key], true)) {
            $from = C::lgreen('    core    ');
        } elseif (in_array($extension, PhpInfo::BUNDLED_EXTENSIONS[$key], true)) {
            $from = C::lyellow('    bundled ');
        } else {
            $from = C::lcyan('    pecl    ');
        }
        $this->console->write($from);

        if ($extVersion) {
            $output = C::white($extension) . ': ' . $extVersion->format3x();
        } elseif ($extVersion === false) {
            $output = $extension . ': ' . C::red('disabled');
        } else {
            $output = $extension . ': ' . C::red('not installed');
        }
        $this->console->write($output);

        $available = $this->remoteExtensions[$extension][$version->major . '.' . $version->minor . '.*'] ?? [];
        if ($available !== []) {
            $this->console->write(C::lgray(' - available: '));
            foreach ($available as $av) {
                $this->console->write($av->format6() . ', ');
            }
        } else {
            $this->console->write(C::red(' - not available'));
        }

        $this->console->ln();
    }

    // install/uninstall -----------------------------------------------------------------------------------------------

    /**
     * @param array<VersionFilter> $filters
     */
    public function install(array $filters, bool $autoSelect, bool $test): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Installing ({$filter->format()}):"));

            foreach ($this->remote as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, PhpInfo::FAMILIES, $this->remote)) {
                        continue;
                    }
                    if ($this->isInstalled($version)) {
                        $this->console->writeLn(C::white('  ' . $version->format6()) . ' already installed');
                        continue 2;
                    }
                    $this->console->writeLn(C::white('  ' . $version->format6()));

                    if ($test) {
                        continue;
                    }

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

    public function installVersion(Version $version): void
    {
        $downloadUrl = $this->downloadUrls[$version->format6()];

        $zipFile = "{$this->baseDir}/tmp.zip";
        $zip = $this->httpHelper->downloadZip($downloadUrl, $zipFile);
        $targetDir = $this->getVersionDirectory($version);
        Io::createDirectory($targetDir, Io::IGNORE);
        Io::cleanDirectory($targetDir, Io::RECURSIVE);

        $this->console->ln()->writeLn("    Extracting to /php{$version->format6()}");
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

    /**
     * @param array<VersionFilter> $filters
     */
    public function uninstall(array $filters, bool $test): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Uninstalling ({$filter->format()}):"));

            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, PhpInfo::FAMILIES, $this->local)) {
                        continue;
                    }
                    $this->console->writeLn('  Removing ' . C::white($version->format6()));

                    if ($test) {
                        continue;
                    }

                    Io::deleteDirectory($this->getVersionDirectory($version), Io::RECURSIVE);
                    unset($this->local[$version->formatFamily()][$version->format6()]);

                    $patch = $this->getLatestInstalled($version->getFamily());
                    if ($patch !== null) {
                        // todo: unselect
                        time();
                    }

                    $this->removeBinFiles($version, 3);
                }
            }
        }
    }

    /**
     * @param array<VersionFilter> $filters
     */
    public function installExtension(string $extension, VersionFilter $extensionFilter, array $filters, bool $autoActivate, bool $test): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Installing extension $extension {$extensionFilter->format()} on {$filter->format()}:"));

            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, PhpInfo::FAMILIES, $this->remote)) {
                        continue;
                    }

                    $this->console->writeLn("  " . C::white($version->format6()));

                    if ($test) {
                        continue;
                    }

                    $extension = PhpInfo::extensionDllName($version, $extension);

                    if (!isset($this->remoteExtensions[$extension])) {
                        continue;
                    }
                    if (PhpInfo::isCoreExtension($extension, $version)) {
                        $this->console->writeLn("    skipping (core)");
                        continue;
                    }
                    if (PhpInfo::isBundledExtension($extension, $version)) {
                        $this->console->writeLn("    skipping (bundled)");
                        continue;
                    }

                    $available = [];
                    $selected = null;
                    $downloadUrl = null;
                    foreach ($this->remoteExtensions[$extension] as $for => $extVersions) {
                        $for = Version::parseExpression($for);
                        if (!$for->match($version)) {
                            continue;
                        }

                        foreach ($extVersions as $url => $extensionVersion) {
                            $available[] = $extensionVersion->format6();
                            if ($extensionFilter->match($extensionVersion, [], ["{$extensionVersion->major}.{$extensionVersion->minor}.*-ts-32" => $extVersions])) {
                                $selected = $extensionVersion;
                                $downloadUrl = $url;
                            }
                        }
                    }
                    if ($available === []) {
                        $this->console->writeLn(C::lred("    not available"));
                    } elseif ($selected === null) {
                        $this->console->writeLn(C::lred("    no version matched. available: " . implode(', ', $available)));
                    } elseif (isset($extensionVersion)) {
                        $this->console->writeLn(C::white("    installing {$selected->format6()}"));
                        $this->installExtensionVersion($version, $extension, $extensionVersion, $downloadUrl, $autoActivate);
                    }
                }
            }
        }
    }

    public function installExtensionVersion(Version $version, string $extension, Version $extensionVersion, string $downloadUrl, bool $autoActivate): void
    {
        $extDir = "$this->baseDir/ext/php_{$extension}_{$version->formatFamilyPath()}_{$extensionVersion->format6()}";
        if (!is_dir($extDir)) {
            $zipFile = "$this->baseDir/tmp.zip";
            $zip = $this->httpHelper->downloadZip($downloadUrl, $zipFile);

            $this->console->ln()->writeLn("    Extracting to /ext/php_{$extension}_{$version->formatFamilyPath()}_{$extensionVersion->format6()}");
            $zip->extractTo($extDir);
            $zip->close();
        }

        $this->linkExtension($extDir, $this->getVersionExtDirectory($version));

        if ($autoActivate) {
            $this->switchExtension($extension, $version, true);
        }
    }

    public function linkExtension(string $extensionDir, string $targetDir): void
    {
        foreach (Io::scanDirectory($extensionDir, Io::RECURSIVE) as $file) {
            $name = $file->getName();
            if (in_array($name, PhpInfo::IGNORED_EXTENSION_FILES, true) || Re::match($name, PhpInfo::IGNORED_EXTENSION_FILE_TYPES) !== null) {
                continue;
            }
            Io::unlink($targetDir . '/' . $name, Io::IGNORE);
            Io::link($file, $targetDir . '/' . $name);
        }
    }

    /**
     * @param array<VersionFilter> $filters
     */
    public function uninstallExtension(string $extension, VersionFilter $extensionFilter, array $filters, bool $test): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Installing extension {$extension} on {$filter->format()}:"));

            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, PhpInfo::FAMILIES, $this->remote)) {
                        continue;
                    }

                    $this->console->writeLn("  " . C::white($version->format6()));

                    if ($test) {
                        continue;
                    }

                    $extension = PhpInfo::extensionDllName($version, $extension);

                    $this->switchExtension($extension, $version, false);

                    if (PhpInfo::isCoreExtension($extension, $version)) {
                        $this->console->writeLn("    skipping (core)");
                        continue;
                    }
                    if (PhpInfo::isBundledExtension($extension, $version)) {
                        $this->console->writeLn("    skipping (bundled)");
                        continue;
                    }

                    $available = [];
                    $selected = null;
                    $downloadUrl = null;
                    foreach ($this->remoteExtensions[$extension] as $for => $extVersions) {
                        $for = VersionFilter::parseExpression($for);
                        if (!$for->match($version)) {
                            continue;
                        }
                        foreach ($extVersions as $url => $extensionVersion) {
                            $available[] = $extensionVersion->format6();
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
                    } elseif (isset($extensionVersion)) {
                        $this->console->writeLn(C::white("    installing {$selected->format6()}"));
                        $this->uninstallExtensionVersion($version, $extension, $extensionVersion, $downloadUrl);
                    }
                }
            }
        }
    }

    public function uninstallExtensionVersion(Version $version, string $extension, Version $extensionVersion, string $downloadUrl): void
    {
        $this->switchExtension($extension, $version, false);

        $extDir = "$this->baseDir/ext/php_{$extension}_{$version->formatFamilyPath()}_{$extensionVersion->format6()}";
        if (!is_dir($extDir)) {
            $zipFile = "$this->baseDir/tmp.zip";
            $zip = $this->httpHelper->downloadZip($downloadUrl, $zipFile);

            $this->console->ln()->writeLn("    Extracting to /ext/php_{$extension}_{$version->formatFamilyPath()}_{$extensionVersion->format6()}");
            $zip->extractTo($extDir);
            $zip->close();
        }

        $this->unlinkExtension($extDir, $this->getVersionExtDirectory($version));
    }

    public function unlinkExtension(string $extensionDir, string $targetDir): void
    {
        foreach (Io::scanDirectory($extensionDir, Io::RECURSIVE) as $file) {
            $name = $file->getName();
            if (in_array($name, PhpInfo::IGNORED_EXTENSION_FILES, true) || Re::match($name, PhpInfo::IGNORED_EXTENSION_FILE_TYPES) !== null) {
                continue;
            }
            Io::unlink( $targetDir . '/' . $name, Io::IGNORE);
        }
    }

    // configuring -----------------------------------------------------------------------------------------------------

    /**
     * @param array<VersionFilter> $filters
     */
    public function configure(array $filters, bool $test): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Configuring ({$filter->format()}):"));

            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, PhpInfo::FAMILIES, $this->remote)) {
                        continue;
                    }
                    $this->console->writeLn("  Configuring " . C::white($version->format6()));

                    if ($test) {
                        continue;
                    }

                    $this->configureVersion($version);
                }
            }
        }
    }

    public function configureVersion(Version $version): void
    {
        $versionDir = $this->getVersionDirectory($version);

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

    public function writeBinFiles(Version $version, int $level): void
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
                . "export PHP_FCGI_MAX_REQUESTS=0\n"
                . "export PHP_FCGI_CHILDREN=16\n"
                . "\"{$this->getVersionCgiBinaryPath($version)}\" \"$@\"\n"
            );
            Io::write($binPath . "-cgi.bat", "@echo OFF\n"
                . "setlocal DISABLEDELAYEDEXPANSION\n"
                . "set PHP_FCGI_MAX_REQUESTS=0\n"
                . "set PHP_FCGI_CHILDREN=16\n"
                . "{$this->getVersionCgiBinaryPath($version)} %*\n"
            );
        }
    }

    public function removeBinFiles(Version $version, int $level): void
    {
        $binPath = $this->getBinPath($version, $level);

        Io::delete($binPath);
        Io::delete($binPath . '.bat');
        Io::delete($binPath . '-cgi');
        Io::delete($binPath . '-cgi.bat');
    }

    /**
     * @param array<VersionFilter> $filters
     */
    public function extensionOn(string $ext, array $filters): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Turn extension {$ext} ON ({$filter->format()}):"));

            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, PhpInfo::FAMILIES, $this->local)) {
                        continue;
                    }
                    // todo: info + test

                    $this->switchExtension($ext, $version, true);
                }
            }
        }
    }

    /**
     * @param array<VersionFilter> $filters
     */
    public function extensionOff(string $ext, array $filters): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Turn extension {$ext} OFF ({$filter->format()}):"));

            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, PhpInfo::FAMILIES, $this->local)) {
                        continue;
                    }
                    // todo: info + test

                    $this->console->writeLn(C::white("  {$version->format6()}:"));
                    $this->switchExtension($ext, $version, false);
                }
            }
        }
    }

    public function switchExtension(string $ext, Version $version, bool $on): void
    {
        $prefix = PhpInfo::extensionIniPrefix($version);
        $suffix = PhpInfo::extensionIniSuffix($version);

        $ini = $this->getVersionIniPath($version);
        if (!file_exists($ini)) {
            $this->console->writeLn(C::red("    no php.ini"));
            return;
        }
        $extension = $this->getVersionExtDirectory($version) . "/" . PhpInfo::extensionFilePrefix($version) . $ext . '.dll';
        if (!file_exists($extension)) {
            $this->console->writeLn(C::yellow("    not installed"));
            return;
        }

        $lines = Io::readLines($ini);
        $inserted = false;
        // switch in place
        foreach ($lines as $i => $line) {
            $match = Re::match($line, "~^\\s*;?\\s*extension\\s*=\\s*(?:$prefix)?$ext(.*?)(?:$suffix)?~");
            if ($match !== null) {
                $ver = $match[1];
                if (PhpInfo::isZendExtension($ext)) {
                    $lines[$i] = $on
                        ? "zend_extension=$prefix$ext$ver$suffix"
                        : ";zend_extension=$prefix$ext$ver$suffix";
                } else {
                    $lines[$i] = $on
                        ? "extension=$prefix$ext$ver$suffix"
                        : ";extension=$prefix$ext$ver$suffix";
                }

                if ($on && !$this->extensionExists($ext . $ver, $version)) {
                    $this->console->writeLn(C::red("Extension {$ext}{$ver} does not seem to be installed on {$version->format6()}."));
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
            $this->console->writeLn(C::red("Extension {$ext} does not seem to be installed on {$version->format6()}."));
        }

        Io::write($ini, implode("\n", $lines));

        $this->console->writeLn(C::lgreen($on ? "    turned on" : "    turned off"));
    }

    public function extensionExists(string $extension, Version $version): bool
    {
        $info = Io::getInfo($this->getVersionExtDirectory($version) . '/' . PhpInfo::extensionFilePrefix($version) . $extension . '.dll');

        return $info->exists();
    }

    // switching versions ----------------------------------------------------------------------------------------------

    /**
     * @param array<VersionFilter> $filters
     */
    public function select(array $filters, string $level): void
    {
        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Selecting as default ({$filter->format()}):"));

            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, PhpInfo::FAMILIES, $this->local)) {
                        continue;
                    }
                    $this->selectVersion($version, $filter, $level);
                }
            }
        }
    }

    public function selectVersion(Version $version, VersionFilter $filter, string $level): void
    {
        // phpXY
        $ff = clone $filter;
        unset($filter->patch);
        if (!$this->higherInstalled($version, $filter, $ff)) {
            $this->console->writeLn('  Selected ' . C::white($version->format6()) . " as default for " . C::white("php{$version->major}{$version->minor}"));
            $this->writeBinFiles($version, 2);
        }

        // phpX
        $f = clone $filter;
        unset($filter->minor, $filter->patch);
        if (!$this->higherInstalled($version, $filter, $f)) {
            if ($level === 'major' || $level === 'global') {
                $this->console->writeLn('  Selected ' . C::white($version->format6()) . " as default for " . C::white("php{$version->major}"));
                $this->writeBinFiles($version, 1);
            }
        }

        // php
        if (!$this->higherInstalled($version, $filter)) {
            if ($level === 'global') {
                $this->console->writeLn('  Selected ' . C::white($version->format6()) . " as default for " . C::white("php"));
                $this->writeBinFiles($version, 0);
            }
        }
    }

    // helpers ---------------------------------------------------------------------------------------------------------

    public function getVersionDirectory(Version $version): string
    {
        return $this->baseDir . '/versions/php' . $version->format6();
    }

    public function getVersionBinaryPath(Version $version): string
    {
        return $this->baseDir . '/versions/php' . $version->format3() . '/php.exe';
    }

    public function getVersionCgiBinaryPath(Version $version): string
    {
        return $this->baseDir . '/versions/php' . $version->format3() . '/php-cgi.exe';
    }

    public function getVersionExtDirectory(Version $version): string
    {
        return $this->baseDir . '/versions/php' . $version->format6() . PhpInfo::extensionDir($version);
    }

    public function getVersionIniPath(Version $version): string
    {
        return $this->baseDir . '/versions/php' . $version->format6() . '/php.ini';
    }

    public function getConfigIniPath(Version $version): string
    {
        return $this->baseDir . "/config/php{$version->major}{$version->minor}.ini";
    }

    public function getBinPath(Version $version, int $level): string
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
            return $this->baseDir . "/bin/php{$version->major}{$version->minor}{$version->patch}" . ($version->threadSafe === true ? "ts" : "");
        } elseif ($level === 5) {
            return $this->baseDir . "/bin/php{$version->major}{$version->minor}{$version->patch}" . ($version->threadSafe === true ? "ts" : "") . $version->platform;
        } else {
            throw new RuntimeException("Max level is 5.");
        }
    }

    public function getLatest(Version $family): int|string|null
    {
        return isset($this->remote[$family->formatFamily()])
            ? end($this->remote[$family->formatFamily()])->patch
            : null;
    }

    public function isAvailable(Version $version): bool
    {
        return isset($this->remote[$version->formatFamily()][$version->format6()]);
    }

    public function higherAvailable(Version $version, ?VersionFilter $filter = null): bool
    {
        $family = $version->getFamily();
        if (!isset($this->remote[$family->format6()])) {
            return false;
        }

        foreach ($this->remote[$family->format6()] as $remote) {
            if ($filter !== null && !$filter->match($remote)) {
                continue;
            }
            if ($remote->compare($version) === ComparisonResult::GREATER) {
                return true;
            }
        }

        return false;
    }

    public function isInstalled(Version $version): bool
    {
        return isset($this->local[$version->formatFamily()][$version->format6()]);
    }

    public function getLatestInstalled(Version $family): int|string|null
    {
        if (!isset($this->local[$family->formatFamily()])) {
            return null;
        }

        $last = end($this->local[$family->formatFamily()]);

        return $last !== false ? $last->patch : null;
    }

    public function higherInstalled(Version $version, VersionFilter ...$filters): bool
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

    // loaders ---------------------------------------------------------------------------------------------------------

    public function loadLocalVersions(): void
    {
        // create list of families
        foreach (PhpInfo::FAMILIES as $major => $minors) {
            foreach ($minors as $minor) {
                // no nts versions before 5.2 and no 64bit versions before 5.5
                if ($major > 5 || ($major === 5 && $minor >= 5)) {
                    $family = Version::newPhp($major, $minor, null, null, null,  false, '64');
                    $this->families[$family->format6()] = $family;
                }
                // no 64bit versions before 5.5
                if ($major > 5 || ($major === 5 && $minor >= 5)) {
                    $family = Version::newPhp($major, $minor, null, null, null, true, '64');
                    $this->families[$family->format6()] = $family;
                }
                // no nts versions before 5.2
                if ($major > 5 || ($major === 5 && $minor >= 2)) {
                    $family = Version::newPhp($major, $minor, null, null, null, false, '32');
                    $this->families[$family->format6()] = $family;
                }
                // always available
                $family = Version::newPhp($major, $minor, null, null, null, true, '32');
                $this->families[$family->format6()] = $family;
            }
        }

        $versions = [];
        foreach (Io::scanDirectory($this->baseDir . '/versions') as $directory) {
            if (!$directory->isDirectory() || !str_starts_with($directory->getName(), 'php')) {
                continue;
            }
            $versions[] = self::parsePhpDirectory($directory->getName());
        }

        $this->selected = [];
        foreach (Io::scanDirectory($this->baseDir . '/bin') as $file) {
            $fileName = $file->getName();
            if ($file->isDirectory() || !str_starts_with($fileName, 'php')) {
                continue;
            }
            if (str_ends_with($fileName, '-cgi') || str_ends_with($fileName, '.bat')) {
                continue;
            }
            $alias = $fileName;
            $lines = Io::readLines($file->getPath());
            $this->selected[$alias] = self::parsePhpDirectory($lines[1]);
        }

        /** @var Version $version */
        foreach (Arr::sortComparable($versions) as $version) {
            $this->local[$version->formatFamily()][$version->format6()] = $version;
        }
    }

    public function loadRemoteVersions(): void
    {
        $winNetBaseUrl = 'https://windows.php.net';

        $downloadsUrl = 'https://windows.php.net/download/';
        $latestUrl = 'https://windows.php.net/downloads/releases/archives/';
        $archivesUrl = 'https://windows.php.net/downloads/releases/archives/';
        $qaUrl = 'https://windows.php.net/qa/';
        $museumWin32Url = 'https://museum.php.net/win32/';
        $museumPhp5Url = 'https://museum.php.net/php5/';

        // https://windows.php.net/downloads/qa/php-8.1.0alpha1-nts-Win32-vs16-x64.zip
        $downloadsLinkRe = '~/downloads/(?:releases/|qa/)(?:archives/)?php-[0-9]+\\.[0-9]+\\.[0-9]+(?:(?:alpha|beta|RC)[0-9]+)?(?:-nts)?-Win32[^.]+\\.zip~i';
        $museumLinkRe = '~php-[0-9]+\\.[0-9]+\\.[0-9]+(RC[0-9])?(-nts)?-Win32\\.zip~i';

        $urls = [];

        // downloads (latest versions)
        $cache = new FileInfo($this->baseDir . '/cache/php-downloads.html');
        if ($cache->exists() && $cache->getModifiedTime()->isAfter(new DateTime('-1 day'))) {
            $html = $cache->read();
        } else {
            $this->console->writeLn("Refreshing downloads ({$latestUrl})");

            $html = Io::read($latestUrl, context: $this->httpHelper->createContext());
            $cache->write($html);
        }
        foreach (Re::matchAll($html, $downloadsLinkRe)[0] as $url) {
            $urls[] = $winNetBaseUrl . $url;
        }

        // archives
        $cache = new FileInfo($this->baseDir . '/cache/php-archives.html');
        if ($cache->exists() && $cache->getModifiedTime()->isAfter(new DateTime('-1 week'))) {
            $html = $cache->read();
        } else {
            $this->console->writeLn("Refreshing archives ({$archivesUrl})");

            $html = Io::read($archivesUrl, context: $this->httpHelper->createContext());
            $cache->write($html);
        }
        foreach (Re::matchAll($html, $downloadsLinkRe)[0] as $url) {
            $urls[] = $winNetBaseUrl . $url;
        }

        // qa (RC builds)
        $cache = new FileInfo($this->baseDir . '/cache/php-qa.html');
        if ($cache->exists() && $cache->getModifiedTime()->isAfter(new DateTime('-1 day'))) {
            $html = $cache->read();
        } else {
            $this->console->writeLn("Refreshing qa ({$qaUrl})");

            $html = Io::read($qaUrl, context: $this->httpHelper->createContext());
            $cache->write($html);
        }
        foreach (Re::matchAll($html, $downloadsLinkRe)[0] as $url) {
            $urls[] = $winNetBaseUrl . $url;
        }

//        // museum win32
//        $cache = new FileInfo($this->baseDir . '/cache/php-museum-win32.html');
//        if ($cache->exists() && $cache->getModifiedTime()->isAfter(new DateTime('-1 month'))) {
//            $html = $cache->read();
//        } else {
//            $this->console->writeLn("Refreshing museum win32 ({$museumWin32Url})");
//
//            $html = Io::read($museumWin32Url, context: $this->createContext());
//            $cache->write($html);
//        }
//        foreach (Re::matchAll($html, $museumLinkRe)[0] as $url) {
//            $urls[] = $museumWin32Url . $url;
//        }
//
//        // museum php5
//        $cache = new FileInfo($this->baseDir . '/cache/php-museum-php5.html');
//        if ($cache->exists() && $cache->getModifiedTime()->isAfter(new DateTime('-1 month'))) {
//            $html = $cache->read();
//        } else {
//            $this->console->writeLn("Refreshing museum php5 ({$museumPhp5Url})");
//
//            $html = Io::read($museumPhp5Url, context: $this->createContext());
//            $cache->write($html);
//        }
//        foreach (Re::matchAll($html, $museumLinkRe)[0] as $url) {
//            $urls[] = $museumPhp5Url . $url;
//        }

        // todo: snapshots?

        $versions = Arr::sortComparable(Arr::remap($urls, static fn (int $k, string $url) => [$url => self::parsePhpUrl($url)]));

        /** @var Version $version */
        foreach ($versions as $version) {
            $this->remote[$version->formatFamily()][$version->format6()] = $version;
        }

        $this->downloadUrls = Arr::remap($versions, static fn (string $url, Version $version) => [$version->format6() => $url]);
    }

    private static function parsePhpDirectory(string $dir): Version
    {
        $m = Re::match($dir, '~(?<major>[0-9]+)\\.(?<minor>[0-9]+)\\.(?<patch>[0-9]+)(?<type>(?:alpha|beta|RC)[0-9]+)?-?(?<threadSafe>ts)?-?(?<platform>32)?~i');
        if ($m === null) {
            throw new RuntimeException('Wrong directory');
        }

        return Version::newPhp(
            intval($m['major']),
            intval($m['minor']),
            intval($m['patch']),
            null,
            $m['type'] ?? null,
            isset($m['threadSafe']) && $m['threadSafe'] === 'ts',
            isset($m['platform']) && $m['platform'] === '32' ? '32' : '64',
        );
    }

    public static function parsePhpUrl(string $url): Version
    {
        $m = Re::match($url, '~php-(?<major>[0-9]+)\\.(?<minor>[0-9]+)\\.(?<patch>[0-9]+)(?<type>(?:alpha|beta|RC)[0-9]+)?-?(?<threadSafe>nts)?.*(?<platform>x64|x86|Win32)~i');

        return Version::newPhp(
            intval($m['major']),
            intval($m['minor']),
            intval($m['patch']),
            null,
            null,
            isset($m['threadSafe']) && $m['threadSafe'] !== 'nts',
            isset($m['platform']) && ($m['platform'] === 'x86' || $m['platform'] === 'Win32' || $m['platform'] === 'win32') ? '32' : '64',
        );
    }

    /**
     * @return array<Version|bool>
     */
    private function probeExtensions(Version $version): array
    {
        // installed and working
        $extensions = $this->probeWorkingExtensions($version);

        // enabled, but not working
        $activated = $this->probeIniExtensions($version);
        foreach ($activated as $extension) {
            if (!isset($extensions[$extension])) {
                $extensions[$extension] = false;
            }
        }

        // installed, but not enabled
        $installed = $this->probeInstalledExtensions($version);
        foreach ($installed as $extension) {
            if (!isset($extensions[$extension])) {
                $extensions[$extension] = true;
            }
        }

        ksort($extensions);

        return $extensions;
    }

    /**
     * @param Version $version
     * @return Version[]
     */
    private function probeWorkingExtensions(Version $version): array
    {
        $extensions = [];

        // todo: does not work on 3.x and 4.x - no -r parameter
        $output = [];
        $command = $version->major >= 6 || ($version->major === 5 && $version->minor >= 2)
            ? ' -r "$extensions = array_merge(get_loaded_extensions(), get_loaded_extensions(true)); foreach ($extensions as $ext) { $ref = new ReflectionExtension($ext); echo $ref->getName() . \'|\' . $ref->getVersion() . PHP_EOL; };" 2> nul'
            : ' -r "$extensions = get_loaded_extensions(); foreach ($extensions as $ext) { $ref = new ReflectionExtension($ext); echo $ref->getName() . \'|\' . $ref->getVersion() . PHP_EOL; };" 2> nul';
        exec($this->getVersionBinaryPath($version) . $command, $output);

        foreach ($output as $line) {
            $match = Re::match($line, '~([a-zA-Z0-9_]+)\\|([0-9.]*)~');
            if ($match !== null) {
                $extensions[strtolower($match[1])] = Version::parseRelease($match[2] ?: PHP_VERSION, '~(?<major>\d+)\.(?<minor>\d+)\.(?<patch>\d+)~');
            }
        }

        ksort($extensions);

        return $extensions;
    }

    /**
     * @return string[]
     */
    private function probeIniExtensions(Version $version): array
    {
        $extensions = [];

        $ini = $this->getConfigIniPath($version);
        foreach (Io::readLines($ini) as $line) {
            $match = Re::match($line, '/^extension\\s*=\\s*([a-zA-Z0-9_]+)/');
            if ($match !== null) {
                $extensions[] = PhpInfo::extensionInternalName($version, $match[1]);
            }
        }

        return $extensions;
    }

    /**
     * @return string[]
     */
    private function probeInstalledExtensions(Version $version): array
    {
        $extensions = [];

        $dir = $this->getVersionExtDirectory($version);
        foreach (Io::scanDirectory($dir) as $file) {
            $match = Re::match($file->getName(), '/^php_([a-zA-Z0-9_]+).dll$/');
            if ($match !== null) {
                $extensions[] = PhpInfo::extensionInternalName($version, $match[1]);
            }
        }

        return $extensions;
    }

    private function loadPeclExtensionsList(): void
    {
        $extListBaseUrl = 'https://pecl.php.net/package/';
        $extListUrl = 'https://pecl.php.net/package-stats.php';
        $extListRe = '~"/package/([a-zA-Z0-9_]+)">[a-zA-Z0-9_]+</a></td>\\s*<td>([0-9,]+)</td>~';

        $cache = new FileInfo($this->baseDir . '/cache/php-pecl-extensions.html');
        if ($cache->exists() && $cache->getModifiedTime()->isAfter(new DateTime('-1 week'))) {
            $html = $cache->read();
        } else {
            $this->console->writeLn("Refreshing extension list ({$extListUrl})");

            $html = Io::read($extListUrl, context: $this->httpHelper->createContext());
            $cache->write($html);
        }
        $extensions = [];
        foreach (Re::matchAll($html, $extListRe, PREG_SET_ORDER) as $row) {
            [, $name, $downloads] = $row;
            $extensions[$name] = [$extListBaseUrl . $name, (int) str_replace(',', '', $downloads)];
        }

        $this->extensions = $extensions;
    }

    private function isPeclExtension(string $extension): bool
    {
        return isset($this->extensions[$extension]);
    }

    /**
     * @param array<string, mixed> $extensions
     */
    private function loadRemoteExtensions(array $extensions): void
    {
        foreach ($extensions as $name => $x) {
            if (!$this->isPeclExtension($name)) {
                return;
            }

            $cache = new FileInfo($this->baseDir . '/cache/pecl-' . $name . '.json');
            if ($cache->exists() && $cache->getModifiedTime()->isAfter(new DateTime('-1 week'))) {
                $json = $cache->read();
                $versions = Json::decode($json, true);
            } else {
                $packageUrl = "https://pecl.php.net/package/{$name}";

                $this->console->writeLn("Refreshing {$name} extension versions ({$packageUrl})");

                $html = Io::read($packageUrl, context: $this->httpHelper->createContext());

                $versions = [];
                $versionRe = '~/package/\w+/(\d+\\.\d+\\.\d+)/windows~';
                foreach (Re::matchAll($html, $versionRe)[1] as $extVer) {
                    $versionUrl = "https://pecl.php.net/package/{$name}/{$extVer}/windows";
                    $html = Io::read($versionUrl, context: $this->httpHelper->createContext());

                    $releaseRe = "~https://windows.php.net/downloads/pecl/releases/{$name}/{$extVer}/php_{$name}-{$extVer}-(\d+\\.\d+(?:-nts|-ts)?(?:-v[cs]\d+)?-(?:x64|x86))\\.zip~";
                    foreach (Re::matchAll($html, $releaseRe, PREG_SET_ORDER) as [$releaseUrl, $ver]) {
                        $version = self::parsePhpUrl('php-' . $ver);
                        $versions[$version->formatFamily()][$releaseUrl] = $extVer;
                    }
                }

                ksort($versions);
                foreach ($versions as $i => $v) {
                    ksort($v);
                    $versions[$i] = $v;
                }

                $cache->write(Json::encode($versions, true));
            }

            foreach ($versions as $i => $v) {
                foreach ($v as $j => $version) {
                    $versions[$i][$j] = Version::parseExpression($version);
                }
            }

            $this->remoteExtensions[$name] = $versions;
        }
    }

    private function loadCachedExtensionVersions(): void
    {
        foreach (Io::scanDirectory($this->baseDir . '/cache') as $file) {
            if (!str_starts_with($file->getName(), 'pecl-')) {
                continue;
            }

            $name = Str::between($file->getName(), 'pecl-', '.json');
            $json = $file->read();
            $versions = Json::decode($json, true);

            foreach ($versions as $i => $v) {
                foreach ($v as $j => $version) {
                    $versions[$i][$j] = Version::parseExpression($version);
                }
            }

            $this->remoteExtensions[$name] = $versions;
        }
    }

}
