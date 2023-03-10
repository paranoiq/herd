<?php declare(strict_types = 1);

namespace Herd;

class ExtensionInfoLoader
{

    private const VERSIONS = [
        'curl' => '~cURL Information => 7.73.0',
        'curl/ssl' => '~SSL Version => OpenSSL/1.1.1h',
        'curl/zlib' => '~ZLib Version => 1.2.11',
        'curl/libssh' => '~libSSH Version => libssh2/1.9.0',
        'date/timelib' => '~timelib version => 2020.02',
        'date/timezones' => '~"Olson" Timezone Database Version => 2020.4',
        'dom' => '~DOM/XML API Version => 20031129',
        'dom/libxml' => '~libxml Version => 2.9.10',
        'exif' => '~Supported EXIF Version => 0220',
        'fileinfo/libmagick' => '~libmagic => 539',
        'gd' => '~GD Version => bundled (2.1.0 compatible)',
        'gd/freetype' => '~FreeType Version => 2.9.1',
        'gd/libjpeg' => '~libJPEG Version => 9 compatible',
        'gd/libpng' => '~libPNG Version => 1.6.34',
        'gd/libxpm' => '~libXpm Version => 30512',
        'gmp' => '~MPIR version => 3.0.0',
        'iconv' => '~iconv library version => 1.16',
        'imap' => '~IMAP c-Client Version => 2007f',
        'intl/icu' => '~ICU version => 68.1',
        'intl/unicode' => '~ICU Unicode version => 13.0',
        'libxml' => '~libXML Compiled Version => 2.9.10',
        'mbstring' => '~libmbfl version => 1.3.2',
        'mbstring/oniguruma' => '~Multibyte regex (oniguruma) version => 6.9.6',
        'mysqli' => '~Client API library version => mysqlnd 8.0.0',
        'mysqlnd' => '~Version => mysqlnd 8.0.0',
        'openssl' => '~OpenSSL Library Version => OpenSSL 1.1.1h  22 Sep 2020',
        'pcre' => '~PCRE Library Version => 10.35 2020-05-09',
        'pcre/unicode' => '~PCRE Unicode Version => 13.0.0',
        'pdo/mysql' => '~Client API version => mysqlnd 8.0.0',
        'pdo/pgsql' => '~PostgreSQL(libpq) Version => 11.4',
        'pdo/sqlite' => '~SQLite Library => 3.33.0',
        'phar' => '~Phar API version => 1.1.1',
        'sodium' => '~libsodium headers version => 1.0.18',
        'sqlite' => '~SQLite Library => 3.33.0',
        'tidy' => '~libTidy Version => 5.6.0',
        'xml' => '~libxml2 Version => 2.9.10',
        'zip' => '~Zip version => 1.19.1',
        'zip/libzip' => '~Libzip version => 1.7.1',
        'zlib' => '~Compiled Version => 1.2.11',

    ];

}