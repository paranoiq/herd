<?php declare(strict_types = 1);

namespace Zoo;

use Dogma\Arr;
use Dogma\Io\FileInfo;
use Dogma\Io\FilesystemException;
use Dogma\Io\Io;
use Dogma\Str;
use Dogma\Time\DateTime;
use Nette\Utils\Json;
use StreamContext;
use Tracy\Debugger;
use ZipArchive;
use function exec;
use function ksort;
use function str_starts_with;
use function strtolower;

trait AppLoaders
{

    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.66 Safari/537.36';

    // PHP versions ----------------------------------------------------------------------------------------------------

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
            $versions[] = Version::parseDirectory($directory->getName());
        }

        /** @var Version $version */
        foreach (Arr::sortComparable($versions) as $version) {
            $this->local[$version->family()][$version->format()] = $version;
        }
    }

    private function loadRemotePhpVersions(): void
    {
        $winNetBaseUrl = 'https://windows.php.net';
        $downloadsUrl = 'https://windows.php.net/download/';
        $qaUrl = 'https://windows.php.net/qa/';
        $archivesUrl = 'https://windows.php.net/downloads/releases/archives/';
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
            $this->console->writeLn("Refreshing downloads ($downloadsUrl)");

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
            $this->console->writeLn("Refreshing qa ($qaUrl)");

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
            $this->console->writeLn("Refreshing archives ($archivesUrl)");

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
            $this->console->writeLn("Refreshing museum win32 ($museumWin32Url)");

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
            $this->console->writeLn("Refreshing museum php5 ($museumPhp5Url)");

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

    // extension versions ----------------------------------------------------------------------------------------------

    /**
     * @param Version $version
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
            $match = Str::match($line, '~([a-zA-Z0-9_]+)\\|([0-9.]*)~');
            if ($match !== null) {
                $extensions[strtolower($match[1])] = Version::parseExpression($match[2] ?: '*', $version);
            }
        }

        return $extensions;
    }

    /**
     * @param Version $version
     * @return string[]
     */
    private function probeIniExtensions(Version $version): array
    {
        $extensions = [];

        $ini = $this->getConfigIniPath($version);
        foreach (Io::readLines($ini) as $line) {
            $match = Str::match($line, '/^extension\\s*=\\s*([a-zA-Z0-9_]+)/');
            if ($match !== null) {
                $extensions[] = Extensions::internalName($version, $match[1]);
            }
        }

        return $extensions;
    }

    /**
     * @param Version $version
     * @return string[]
     */
    private function probeInstalledExtensions(Version $version): array
    {
        $extensions = [];

        $dir = $this->getVersionExtDirectory($version);
        foreach (Io::scanDirectory($dir) as $file) {
            $match = Str::match($file->getName(), '/^php_([a-zA-Z0-9_]+).dll$/');
            if ($match !== null) {
                $extensions[] = Extensions::internalName($version, $match[1]);
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
            $this->console->writeLn("Refreshing extension list ($extListUrl)");

            $html = Io::read($extListUrl, context: $this->createContext());
            $cache->write($html);
        }
        $extensions = [];
        foreach (Str::matchAll($html, $extListRe, PREG_SET_ORDER) as $row) {
            [, $name, $downloads] = $row;
            $extensions[$name] = [$extListBaseUrl . $name, (int) str_replace(',', '', $downloads)];
        }

        $this->extensions = $extensions;
    }

    private function isPeclExtension(string $extension): bool
    {
        return isset($this->extensions[$extension]);
    }

    private function loadRemoteExtensions(array $extensions): void
    {
        foreach ($extensions as $name => $x) {
            if (!$this->isPeclExtension($name)) {
                return;
            }

            $cache = new FileInfo($this->baseDir . '/cache/pecl-' . $name . '.json');
            if ($cache->exists() && $cache->getModifiedTime()->isAfter(new DateTime('-1 week'))) {
                $json = $cache->read();
                $versions = Json::decode($json, Json::FORCE_ARRAY);
            } else {
                $url = "https://pecl.php.net/package/$name";

                $this->console->writeLn("Refreshing $name extension versions ($url)");

                $html = Io::read($url, context: $this->createContext());

                $versions = [];
                $verRe = '~/package/[a-zA-Z0-9_]+/([0-9]+\\.[0-9]+\\.[0-9]+)/windows~';
                foreach (Str::matchAll($html, $verRe)[1] as $extVer) {
                    $url = "https://pecl.php.net/package/$name/$extVer/windows";
                    $html = Io::read($url, context: $this->createContext());

                    $versionRe = "~https://windows.php.net/downloads/pecl/releases/$name/$extVer/php_imagick-$extVer-([0-9]+\\.[0-9]+(?:-nts|-ts)?(?:-vc[0-9]+)?-(?:x64|x86))\\.zip~";
                    foreach (Str::matchAll($html, $versionRe, PREG_SET_ORDER) as [$url, $ver]) {
                        $version = Version::parseUrl('php-' . $ver);
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
            $versions = Json::decode($json, Json::FORCE_ARRAY);

            foreach ($versions as $i => $v) {
                foreach ($v as $j => $version) {
                    $versions[$i][$j] = Version::parseExpression($version);
                }
            }

            $this->remoteExtensions[$name] = $versions;
        }
    }

    // helpers ---------------------------------------------------------------------------------------------------------

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
        try {
            $data = Io::read($downloadUrl, 0, null, $context);
        } catch (FilesystemException $e) {
            Debugger::log($e);
            throw $e;
        }

        Io::write($zipFile, $data);

        $zip = new ZipArchive();
        $zip->open($zipFile);

        return $zip;
    }

    private function createContext(): StreamContext
    {
        $context = StreamContext::createHttp();
        $context->setUserAgent($this->userAgent);

        return $context;
    }

}