<?php

namespace Herd;

use Dogma\Application\Console;
use Dogma\Http\HttpRequest;
use Dogma\Io\FilesystemException;
use Dogma\Io\Io;
use Dogma\System\Os;
use StreamContext;
use Tracy\Debugger;
use ZipArchive;
use const CURLSSLOPT_NATIVE_CA;
use const CURLOPT_CAINFO;

class HttpHelper
{

    public string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';

    public function __construct(
        private Console $console,
    )
    {}

    public function downloadZip(string $downloadUrl, string $zipFile): ZipArchive
    {
        $last = 0;
        $context = $this->createContext();
        $context->onProgress(function (StreamContext $context, int $progress, int $size) use (&$last): void {
            if ($last === 0 || $progress - $last > 100000) {
                $this->console->write('.');
                $last = $progress;
            }
        });
        $this->console->writeLn("    Downloading {$downloadUrl}");
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

    public function createContext(): StreamContext
    {
        $context = StreamContext::createHttp();
        $context->setUserAgent($this->userAgent);

        return $context;
    }

    public function createHttpRequest(string $url): HttpRequest
    {
        $request = new HttpRequest($url);
        $this->setupSslCerts($request);
        $this->setupUserAgent($request);

        return $request;
    }

    public function setupSslCerts(HttpRequest $request): void
    {
        if (Os::isWindows()) {
            $request->setOption(CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA); // PHP 8.2+
        } else {
            $request->setOption(CURLOPT_CAINFO, "/etc/ssl/certs/ca-certificates.crt");
        }
    }

    public function setupUserAgent(HttpRequest $request): void
    {
        $version_info = curl_version();

        $request->setOption(CURLOPT_USERAGENT, "curl/{$version_info['version']}");
    }

}