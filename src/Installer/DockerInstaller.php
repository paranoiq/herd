<?php

namespace Herd\Installer;

use Dogma\Application\Colors as C;
use Dogma\Application\Configurator;
use Dogma\Application\Console;
use Dogma\Io\FileInfo;
use Dogma\Io\Io;
use Dogma\Re;
use Dogma\Time\Date;
use Dogma\Time\DateTime;
use Herd\HttpHelper;
use Herd\Info\PhpInfo;
use Herd\Version;
use function array_reverse;
use function exec;
use function implode;
use function json_decode;
use function json_encode;
use function passthru;
use function str_replace;
use function uksort;
use function version_compare;
use const JSON_PRETTY_PRINT;
use const PREG_SET_ORDER;

abstract class DockerInstaller
{

    // driver
    public string $fancyName;
    public string $dir;
    public string $minVersion; // cuts what versions are available on DockerHub (and therefore installable by default)

    // metadata
    public string $releaseNotesRe;
    public array $releaseDates = [];

    // docker
    public string $image;
    public string $containerPrefix;
    public string $volumePrefix;
    public string $volumeTarget;
    public string $runCommand = '';
    public array $ports;
    public array $envVars = [];

    public Console $console;

    public HttpHelper $httpHelper;

    public string $baseDir;

    /** @var array<string, string> ($family => $url) */
    public array $releaseNotesListsUrls = [];

    /** @var int[][] ($major => $minors) */
    public array $familyTree = [];

    /** @var Version[] ($family => $familyVersion) */
    public array $families = [];

    /** @var Version[][] ($family => $versionString => $versionObject) */
    public array $remote = [];

    /** @var Version[][] ($family => $versionString => $versionObject) */
    public array $local = [];

    /** @var array<string, bool> ($versionString => $running) */
    public array $running = [];

    public function __construct(Console $console,)
    {
        $this->console = $console;
        $this->httpHelper = new HttpHelper($console);
    }

    public function init(Configurator $config): void
    {
        $this->baseDir = (string) $config->baseDir;
        $cacheDir = "{$this->baseDir}/cache/{$this->dir}";

        Io::createDirectory($cacheDir, Io::IGNORE);

        if ($config->refresh) {
            $this->console->writeLn('Cleaning cache');
            Io::cleanDirectory($cacheDir);
        }
    }

    public function run(Configurator $config): void
    {
        $this->init($config);
        $this->loadRemoteVersions();
        $this->loadLocalVersions();
        $this->loadRunningVersions();

        if ($config->all) {
            $this->listRemote(Version::filter($config->all));
        } elseif ($config->local) {
            $this->listLocal(Version::filter($config->local));
        } elseif ($config->new) {
            $this->listNew(Version::filter($config->new));
        } elseif ($config->install) {
            $this->install(Version::filter($config->install, '**^'));
        } elseif ($config->uninstall) {
            $this->uninstall(Version::filter($config->uninstall, '**_'), (bool) $config->test);
        } elseif ($config->start) {
            $this->start(Version::filter($config->start, '^^^'), (bool) $config->test);
        } elseif ($config->stop) {
            $this->stop(Version::filter($config->stop, '^^^'), (bool) $config->test);
        } elseif ($config->configure) {
            $this->console->writeLn(C::lred("Configure is not implemented for {$this->fancyName}"));
        } elseif ($config->default) {
            $this->console->writeLn(C::lred("Default is not implemented for {$this->fancyName}"));
        } elseif ($config->info) {
            $this->console->writeLn(C::lred("Info is not implemented for {$this->fancyName}"));
        }
    }

    /** @override */
    public function familyKey(Version $version): string
    {
        return $version->format2();
    }

    /** @override */
    public function versionKey(Version $version): string
    {
        return $version->format3();
    }

    /** @override */
    public function dockerVersionKey(Version $version): string
    {
        return $version->format3();
    }

    /** @override */
    public function format(Version $version): string
    {
        return $version->format3();
    }

    /** @override */
    public function formatT(Version $version): string
    {
        return $version->format3t();
    }

    abstract public function loadReleaseNotesListsUrls();

    /**
     * @override
     * @return array<string, Version>
     */
    public function parseVersionsFromReleaseNotesList(string $html): array
    {
        $versions = [];
        foreach (Re::matchAll($html, $this->releaseNotesRe, PREG_SET_ORDER) as $match) {
            $version = Version::parseRelease($match[0], $this->releaseNotesRe, $this->fancyName);
            if ($version->date === null && isset($this->releaseDates[$this->versionKey($version)])) {
                $version->date = new Date($this->releaseDates[$this->versionKey($version)]);
            }
            $versions[$this->versionKey($version)] = $version;
        }

        return $versions;
    }

    public function loadRemoteVersions(): void
    {
        $this->loadReleaseNotesListsUrls();

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

            foreach ($this->parseVersionsFromReleaseNotesList($html) as $version) {
                if (!isset($this->minVersion) || version_compare($this->versionKey($version), $this->minVersion) >= 0) {
                    $this->remote[$this->familyKey($version)][$this->versionKey($version)] = $version;
                }
            }
        }

        uksort($this->remote, static function ($a, $b): int {
            return version_compare($b, $a);
        });

        foreach ($this->remote as $fam => $versions) {
            uksort($this->remote[$fam], static function ($a, $b): int {
                return version_compare($a, $b);
            });
        }

        foreach ($this->remote as $fam => $versions) {
            $v = end($versions);
            $vp = explode('.', $this->familyKey($v));
            $vp[] = null;
            $vp[] = null;
            $this->families[$fam] = new Version(...$vp);
        }

        foreach ($this->families as $fam => $x) {
            [$major, $minor] = explode('.', $fam . '.');
            $this->familyTree[(int) $major][] = (int) $minor;
        }
    }

    public function loadLocalVersions(): void
    {
        $path = "{$this->baseDir}/{$this->dir}.local.json";
        if (!Io::exists($path)) {
            return;
        }

        $data = json_decode(Io::read($path), true);
        foreach ($data as $fam => $versions) {
            $this->local[$fam] = [];
            uksort($versions, static function ($a, $b): int {
                return version_compare($a, $b);
            });
            foreach ($versions as $key => $version) {
                $this->local[$fam][$key] = Version::jsonUnserialize($version);
            }
        }
    }

    public function saveLocalVersions(): void
    {
        $path = "{$this->baseDir}/{$this->dir}.local.json";

        $data = [];
        foreach ($this->local as $fam => $versions) {
            $data[$fam] = [];
            foreach ($versions as $key => $version) {
                $data[$fam][$key] = $version->jsonSerialize();
            }
        }
        Io::write($path, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function loadRunningVersions(): void
    {
        if (exec('docker ps', $output) !== false) {
            foreach ($this->running as $ver => $runs) {
                $this->running[$ver] = false;
            }
            foreach ($output as $line) {
                if ($match = Re::match($line, "~{$this->containerPrefix}(\d+\.\d+\.\d+)~")) {
                    $this->running[$match[1]] = true;
                }
            }
        }
    }

    // list ------------------------------------------------------------------------------------------------------------

    private function listRemote(array $filters): void
    {
        $this->console->writeLn(C::lcyan("Available {$this->fancyName} versions") . ' ' . C::lyellow('installed') . ' ' . C::lgreen('running'));

        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Filtered by {$filter->format3()}:"));

            foreach ($this->families as $family => $familyVersion) {
                if ($filter->match($familyVersion)) {
                    $this->console->writeLn(C::white("  {$family}:"));
                }
                if (!isset($this->remote[$family])) {
                    continue;
                }
                $versions = [];
                foreach (array_reverse($this->remote[$family]) as $version) {
                    if ($filter->match($version)) {
                        $v = $this->formatT($version);
                        $versions[] = isset($this->running[$this->versionKey($version)])
                            ? C::lgreen($v)
                            : ($this->isInstalled($version) ? C::lyellow($v) : $v);
                    }
                }
                if ($versions !== []) {
                    $this->console->writeLn('    ' . implode(', ', $versions));
                }
            }
        }
    }

    private function listLocal(array $filters): void
    {
        $this->console->writeLn(C::lcyan("Local {$this->fancyName} versions") . ' - ' . C::lgreen('running') . ' (' . C::yellow('port') . ')');

        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Filtered by {$filter->format3()}:"));

            foreach ($this->families as $name => $family) {
                if ($filter->match($family)) {
                    $this->console->writeLn(C::white("  $name:"));
                }
                if (!isset($this->local[$name])) {
                    continue;
                }
                $ver = [];
                foreach (array_reverse($this->local[$name]) as $version) {
                    if ($filter->match($version)) {
                        if (isset($this->running[$this->versionKey($version)])) {
                            $portsInfo = $this->portsInfo($version);
                            $v = C::lgreen($version->format3t()) . ' (' . $portsInfo . ')';
                        } else {
                            $v = $version->format3t();
                        }
                        $ver[] = $v;
                    }
                }
                if ($ver !== []) {
                    $this->console->writeLn('    ' . implode(', ', $ver));
                }
            }
        }
    }

    private function listNew(array $filters): void
    {
        $this->console->writeLn(C::lcyan("New {$this->fancyName} versions") . ' - ' . C::lyellow('older') . ' ' . C::lgreen('last'));

        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Filtered by {$filter->format3()}:"));

            $allInstalled = true;
            $allUpToDate = true;
            foreach ($this->families as $family => $familyVersion) {
                if (!$filter->match($familyVersion)) {
                    continue;
                }
                $this->console->writeLn(C::white('  ' . $family . ':'));
                $latest = $this->getLatest($familyVersion);
                $lv = $this->createLatest($familyVersion, $latest);

                $versions = [];
                $versionUpToDate = false;
                foreach ($this->local[$family] ?? [] as $version) {
                    $rv = $this->remote[$this->familyKey($version)][$this->versionKey($version)];
                    $date = $rv->date !== null ? ' ' . C::gray($rv->date->format('(Y-m-d)')) : '';

                    if ($this->versionKey($version) === $this->versionKey($lv)) {
                        $versions[] = C::lgreen($this->formatT($version)) . $date;
                        $versionUpToDate = true;
                    } else {
                        $versions[] = C::lyellow($this->formatT($version)) . $date;
                    }
                }
                if ($versions === []) {
                    $this->console->writeLn('    installed: ' . c::lred('none'));
                } else {
                    $this->console->writeLn('    installed: ', implode(', ', array_reverse($versions)));
                }

                if ($latest === null) {
                    $this->console->writeLn('    latest:    ' . C::lred('unknown'));
                } elseif ($versions === [] || $version->patch !== $latest) {
                    $rv = $this->remote[$this->familyKey($lv)][$this->versionKey($lv)];
                    $date = $rv->date !== null ? ' ' . C::gray($rv->date->format('(Y-m-d)')) : '';

                    $this->console->writeLn('    latest:    ' . C::white($this->formatT($lv)) . ' ' . $date);
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

    // install/uninstall -----------------------------------------------------------------------------------------------

    /**
     * @param Version[] $filters
     */
    public function install(array $filters): void
    {
        $this->console->writeLn(C::lcyan("Installing {$this->fancyName} versions"));

        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Filtered by {$filter->format3()}:"));

            foreach ($this->remote as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, $this->familyTree, $this->remote)) {
                        continue;
                    }
                    if ($this->isInstalled($version)) {
                        $this->console->writeLn(C::white('  ' . $version->format3()) . ' already installed');
                        continue;
                    }
                    $this->console->writeLn(C::white('  ' . $version->format3()));

                    $this->installVersion($version);
                }
            }
        }
    }

    public function installVersion(Version $version): void
    {
        $vk = $this->versionKey($version);
        $dvk = $this->dockerVersionKey($version);
        $this->console->ln()->writeLn("  Installing {$this->image}:{$vk}");

        if (passthru("docker pull {$this->image}:{$dvk}", $resultCode) !== false && $resultCode === 0) {
            $this->local[$this->familyKey($version)][$vk] = $version;

            $this->saveLocalVersions();
        }
    }

    /**
     * @param Version[] $filters
     */
    public function uninstall(array $filters, bool $test): void
    {
        $this->console->writeLn(C::lcyan("Uninstalling {$this->fancyName} versions"));

        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Filtered by {$filter->format3()}:"));

            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, PhpInfo::FAMILIES, $this->local)) {
                        continue;
                    }

                    $this->console->ln()->writeLn("  Removing {$this->image}:{{$this->versionKey($version)}}");
                    if ($test) {
                        continue;
                    }

                    $this->uninstallVersion($version);
                }
            }
        }
    }

    public function uninstallVersion(Version $version): void
    {
        $vk = $this->versionKey($version);
        $dvk = $this->versionKey($version);
        if (
            passthru("docker stop {$this->containerPrefix}{$vk}") !== false
            && passthru("docker rm {$this->containerPrefix}{$vk}") !== false
            && passthru("docker volume rm {$this->volumePrefix}{$vk}") !== false
            && passthru("docker rmi {$this->image}:{$dvk}", $resultCode) !== false
            && $resultCode === 0 // check only the image removal
        ) {
            unset($this->local[$this->familyKey($version)][$vk]);

            $this->saveLocalVersions();
        }
    }

    public function start(array $filters, bool $test): void
    {
        $this->console->writeLn(C::lcyan("Starting {$this->fancyName} versions"));

        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Filtered by {$filter->format3()}:"));

            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, PhpInfo::FAMILIES, $this->local)) {
                        continue;
                    }

                    $vk = $this->versionKey($version);
                    $this->console->ln()->writeLn("  Starting {$this->image}:{$vk}");

                    if ($test) {
                        continue;
                    }

                    $this->startVersion($version);
                }
            }
        }
    }

    public function startVersion(Version $version): void
    {
        $vk = $this->versionKey($version);
        $dvk = $this->versionKey($version);

        $ports = [];
        foreach ($this->ports as $port) {
            $ports[] = "-p {$this->translatePort($port, $version)}:{$port}";
        }
        $ports = implode(' ', $ports);

        $envVars = [];
        foreach ($this->envVars as $var => $val) {
            $envVars[] = "-e {$var}={$val}";
        }
        $envVars = implode(' ', $envVars);

        $command = "docker run -d --name {$this->containerPrefix}{$vk} -v {$this->volumePrefix}{$vk}:{$this->volumeTarget} {$ports} {$envVars} {$this->image}:{$dvk}";
        if (passthru($command, $resultCode) !== false && $resultCode === 0) {
            $this->console->ln()->writeLn("    Running on port " . C::yellow($this->portsInfo($version)));
            /// todo
        }
    }

    public function stop(array $filters, bool $test): void
    {
        $this->console->writeLn(C::lcyan("Stopping {$this->fancyName} versions"));

        foreach ($filters as $filter) {
            $this->console->writeLn(C::white("Filtered by {$filter->format3()}:"));

            foreach ($this->local as $versions) {
                foreach ($versions as $version) {
                    if (!$filter->match($version, PhpInfo::FAMILIES, $this->local)) {
                        continue;
                    }

                    $vk = $this->versionKey($version);
                    $this->console->ln()->writeLn("  Stopping {$this->image}:{$vk}");

                    if ($test) {
                        continue;
                    }

                    $this->stopVersion($version);
                }
            }
        }
    }

    public function stopVersion(Version $version): void
    {
        $ver = $version->format3();

        if (passthru("docker stop {$this->containerPrefix}{$ver}", $resultCode) !== false && $resultCode === 0) {
            /// todo
        }
    }

    // helpers ---------------------------------------------------------------------------------------------------------

    /** @override */
    public function getLatest(Version $family): int|string|null
    {
        return isset($this->remote[$this->familyKey($family)])
            ? end($this->remote[$this->familyKey($family)])->patch
            : null;
    }

    public function createLatest(Version $version, int|string $last): Version
    {
        return $version->setPatch($last);
    }

    public function isInstalled(Version $version): bool
    {
        return isset($this->local[$this->familyKey($version)][$this->versionKey($version)]);
    }

    abstract public function translatePort(int $port, Version $version): int;

    public function portsInfo(Version $version): string
    {
        $ports = [];
        foreach ($this->ports as $port) {
            $ports[] = C::yellow($this->translatePort($port, $version));
        }

        return implode(', ', $ports);
    }

}