<?php declare(strict_types = 1);

namespace Zoo;

use Dogma\StaticClassMixin;
use function in_array;

class Extensions
{
    use StaticClassMixin;

    public const CORE = [
        '8.0' => [
            'bcmath', 'calendar', 'core', 'ctype', 'date', 'dom', 'filter', 'hash', 'iconv', 'json',
            'libxml', 'mysqlnd', 'pcre', 'pdo', 'phar', 'readline', 'reflection', 'session', 'simplexml',
            'spl', 'standard', 'tokenizer', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib',
        ],
        '7.4' => [
            'bcmath', 'calendar', 'core', 'ctype', 'date', 'dom', 'filter', 'hash', 'iconv', 'json',
            'libxml', 'mysqlnd', 'pcre', 'pdo', 'phar', 'readline', 'reflection', 'session', 'simplexml',
            'spl', 'standard', 'tokenizer', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib',
            // -wddx
        ],
        '7.3' => [
            'bcmath', 'calendar', 'core', 'ctype', 'date', 'dom', 'filter', 'hash', 'iconv', 'json',
            'libxml', 'mysqlnd', 'pcre', 'pdo', 'phar', 'readline', 'reflection', 'session', 'simplexml',
            'spl', 'standard', 'tokenizer', 'wddx', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib',
        ],
        '7.2' => [
            'bcmath', 'calendar', 'core', 'ctype', 'date', 'dom', 'filter', 'hash', 'iconv', 'json',
            'libxml', 'mysqlnd', 'pcre', 'pdo', 'phar', 'readline', 'reflection', 'session', 'simplexml',
            'spl', 'standard', 'tokenizer', 'wddx', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib',
            // -mcrypt
        ],
        '7.1' => [
            'bcmath', 'calendar', 'core', 'ctype', 'date', 'dom', 'filter', 'hash', 'iconv', 'json',
            'libxml', 'mcrypt', 'mysqlnd', 'pcre', 'pdo', 'phar', 'readline', 'reflection', 'session', 'simplexml',
            'spl', 'standard', 'tokenizer', 'wddx', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib',
        ],
        '7.0' => [
            'bcmath', 'calendar', 'core', 'ctype', 'date', 'dom', 'filter', 'hash', 'iconv', 'json',
            'libxml', 'mcrypt', 'mysqlnd', 'pcre', 'pdo', 'phar', 'readline', 'reflection', 'session', 'simplexml',
            'spl', 'standard', 'tokenizer', 'wddx', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib',
            // -ereg -ftp -mhash -odbc +readline +mysqlnd
        ],
        '5.6' => [
            'bcmath', 'calendar', 'core', 'ctype', 'date', 'dom', 'ereg', 'filter', 'ftp', 'hash', 'iconv', 'json',
            'libxml', 'mcrypt', 'mhash', 'mysqlnd', 'odbc', 'pcre', 'pdo', 'phar', 'reflection', 'session', 'simplexml',
            'spl', 'standard', 'tokenizer', 'wddx', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib',
        ],
        '5.5' => [
            'bcmath', 'calendar', 'core', 'ctype', 'date', 'dom', 'ereg', 'filter', 'ftp', 'hash', 'iconv', 'json',
            'libxml', 'mcrypt', 'mhash', 'mysqlnd', 'odbc', 'pcre', 'pdo', 'phar', 'reflection', 'session', 'simplexml',
            'spl', 'standard', 'tokenizer', 'wddx', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib',
        ],
        '5.4' => [
            'bcmath', 'calendar', 'core', 'ctype', 'date', 'dom', 'ereg', 'filter', 'ftp', 'hash', 'iconv', 'json',
            'libxml', 'mcrypt', 'mhash', 'mysqlnd', 'odbc', 'pcre', 'pdo', 'phar', 'reflection', 'session', 'simplexml',
            'spl', 'standard', 'tokenizer', 'wddx', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib',
        ],
        '5.3' => [
            'bcmath', 'calendar', 'core', 'ctype', 'date', 'dom', 'ereg', 'filter', 'ftp', 'hash', 'iconv', 'json',
            'libxml', 'mcrypt', 'mhash', 'mysqlnd', 'odbc', 'pcre', 'pdo', 'phar', 'reflection', 'session', 'simplexml',
            'spl', 'standard', 'tokenizer', 'wddx', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib',
            // -com_dotnet +ereg +mcrypt +mhash +mysqlnd +phar +zip +pdo
        ],
        '5.2' => [
            'bcmath', 'calendar', 'com_dotnet', 'ctype', 'date', 'dom', 'filter', 'ftp', 'hash', 'iconv', 'json',
            'libxml', 'odbc', 'pcre', 'reflection', 'session', 'simplexml',
            'spl', 'standard', 'tokenizer', 'wddx', 'xml', 'xmlreader', 'xmlwriter', 'zlib',
            // +filter +json
        ],
        '5.1' => [
            'bcmath', 'calendar', 'com_dotnet', 'ctype', 'date', 'dom', 'ftp', 'hash', 'iconv',
            'libxml', 'odbc', 'pcre', 'reflection', 'session', 'simplexml',
            'spl', 'standard', 'tokenizer', 'wddx', 'xml', 'xmlreader', 'xmlwriter', 'zlib',
            // -sqlite +date +hash +reflection +xmlreader +xmlwriter
        ],
        '5.0' => [
            'bcmath', 'calendar', 'com_dotnet', 'ctype', 'dom', 'ftp', 'iconv',
            'libxml', 'odbc', 'pcre', 'session', 'simplexml',
            'spl', 'sqlite', 'standard', 'tokenizer', 'wddx', 'xml', 'zlib',
            // -mysql -overload +dom +iconv +libxml +simplexml +spl +sqlite
        ],
        '4.4' => [
            'bcmath', 'calendar', 'com', 'ctype', 'ftp', 'mysql',
            'odbc', 'overload', 'pcre', 'session',
            'standard', 'tokenizer', 'wddx', 'xml', 'zlib',
        ],
        '4.3' => [
            'bcmath', 'calendar', 'com', 'ctype', 'ftp', 'mysql',
            'odbc', 'overload', 'pcre', 'session',
            'standard', 'tokenizer', 'wddx', 'xml', 'zlib',
            // +ctype +overload +tokenizer +zlib
        ],
        '4.2' => [
            'bcmath', 'calendar', 'com', 'ftp', 'mysql',
            'odbc', 'pcre', 'session',
            'standard', 'wddx', 'xml',
            // -variant
        ],
        '4.1' => [
            'bcmath', 'calendar', 'com', 'variant', 'ftp', 'mysql',
            'odbc', 'pcre', 'session',
            'standard', 'wddx', 'xml',
        ],
        '4.0' => [ // todo: `php40 -m` segfaults
            'bcmath', 'calendar', 'com', 'variant', 'ftp', 'mysql',
            'odbc', 'pcre', 'session',
            'standard', 'wddx', 'xml',
        ],
        '3.0' => [ // todo: `php30 -m` unable to parse configuration file
            'bcmath', 'calendar', 'com', 'variant', 'ftp', 'mysql',
            'odbc', 'pcre', 'session',
            'standard', 'wddx', 'xml'
        ],
    ];

    public const BUNDLED = [
        '8.0' => [
            'bz2', 'com_dotnet', 'curl', 'dba', 'enchant', 'exif', 'ffi', 'fileinfo', 'ftp', 'gd', 'gettext', 'gmp',
            'imap', 'intl', 'ldap', 'mbstring', 'mysqli', 'oci8_12c', 'oci8_19', 'odbc', 'opcache', 'openssl',
            'pdo_firebird', 'pdo_mysql', 'pdo_oci', 'pdo_odbc', 'pdo_pgsql', 'pdo_sqlite', 'pgsql', 'phpdbg_webhelper',
            'shmop', 'snmp', 'soap', 'sockets', 'sodium', 'sqlite3', 'sysvshm', 'tidy', 'xsl', 'zend_test',
            // -pcov -xmlrpc +oci8_19
        ],
        '7.4' => [
            'bz2', 'com_dotnet', 'curl', 'dba', 'enchant', 'exif', 'ffi', 'fileinfo', 'ftp', 'gd', 'gettext', 'gmp',
            'imap', 'intl', 'ldap', 'mbstring', 'mysqli', 'oci8_12c', 'odbc', 'opcache', 'openssl', 'pcov',
            'pdo_firebird', 'pdo_mysql', 'pdo_oci', 'pdo_odbc', 'pdo_pgsql', 'pdo_sqlite', 'pgsql', 'phpdbg_webhelper',
            'shmop', 'snmp', 'soap', 'sockets', 'sodium', 'sqlite3', 'sysvshm', 'tidy', 'xmlrpc', 'xsl', 'zend_test',
            // +ffi +pcov -interbase
        ],
        '7.3' => [
            'bz2', 'com_dotnet', 'curl', 'dba', 'enchant', 'exif', 'fileinfo', 'ftp', 'gd', 'gettext', 'gmp',
            'imap', 'interbase', 'intl', 'ldap', 'mbstring', 'mysqli', 'oci8_12c', 'odbc', 'opcache', 'openssl',
            'pdo_firebird', 'pdo_mysql', 'pdo_oci', 'pdo_odbc', 'pdo_pgsql', 'pdo_sqlite', 'pgsql', 'phpdbg_webhelper',
            'shmop', 'snmp', 'soap', 'sockets', 'sodium', 'sqlite3', 'sysvshm', 'tidy', 'xmlrpc', 'xsl', 'zend_test',
        ],
        '7.2' => [
            'bz2', 'com_dotnet', 'curl', 'dba', 'enchant', 'exif', 'fileinfo', 'ftp', 'gd', 'gettext', 'gmp',
            'imap', 'interbase', 'intl', 'ldap', 'mbstring', 'mysqli', 'oci8_12c', 'odbc', 'opcache', 'openssl',
            'pdo_firebird', 'pdo_mysql', 'pdo_oci', 'pdo_odbc', 'pdo_pgsql', 'pdo_sqlite', 'pgsql', 'phpdbg_webhelper',
            'shmop', 'snmp', 'soap', 'sockets', 'sodium', 'sqlite3', 'sysvshm', 'tidy', 'xmlrpc', 'xsl', 'zend_test',
            // +zend_test +dba
        ],
        '7.1' => [
            'bz2', 'com_dotnet', 'curl', 'enchant', 'exif', 'fileinfo', 'ftp', 'gd', 'gettext', 'gmp',
            'imap', 'interbase', 'intl', 'ldap', 'mbstring', 'mysqli', 'oci8_12c', 'odbc', 'opcache', 'openssl',
            'pdo_firebird', 'pdo_mysql', 'pdo_oci', 'pdo_odbc', 'pdo_pgsql', 'pdo_sqlite', 'pgsql', 'phpdbg_webhelper',
            'shmop', 'snmp', 'soap', 'sockets', 'sqlite3', 'sysvshm', 'tidy', 'xmlrpc', 'xsl',
        ],
        '7.0' => [
            'bz2', 'com_dotnet', 'curl', 'enchant', 'exif', 'fileinfo', 'ftp', 'gd', 'gettext', 'gmp',
            'imap', 'interbase', 'intl', 'ldap', 'mbstring', 'mysqli', 'oci8_12c', 'odbc', 'opcache', 'openssl',
            'pdo_firebird', 'pdo_mysql', 'pdo_oci', 'pdo_odbc', 'pdo_pgsql', 'pdo_sqlite', 'pgsql', 'phpdbg_webhelper',
            'shmop', 'snmp', 'soap', 'sockets', 'sqlite3', 'sysvshm', 'tidy', 'xmlrpc', 'xsl',
        ],
        '5.6' => [
            'bz2', 'com_dotnet', 'curl', 'enchant', 'exif', 'fileinfo', 'gd', 'gettext', 'gmp',
            'imap', 'interbase', 'intl', 'ldap', 'mbstring', 'mysql', 'mysqli', 'oci8_12c', 'opcache', 'openssl',
            'pdo_firebird', 'pdo_mysql', 'pdo_oci', 'pdo_odbc', 'pdo_pgsql', 'pdo_sqlite', 'pgsql',
            'shmop', 'snmp', 'soap', 'sockets', 'sqlite3', 'sybase_ct', 'tidy', 'xmlrpc', 'xsl',
            // -oci8
        ],
        '5.5' => [
            'bz2', 'com_dotnet', 'curl', 'enchant', 'exif', 'fileinfo', 'gd', 'gettext', 'gmp',
            'imap', 'interbase', 'intl', 'ldap', 'mbstring', 'mysql', 'mysqli', 'oci8', 'oci8_11g', 'opcache', 'openssl',
            'pdo_firebird', 'pdo_mysql', 'pdo_oci', 'pdo_odbc', 'pdo_pgsql', 'pdo_sqlite', 'pgsql',
            'shmop', 'snmp', 'soap', 'sockets', 'sqlite3', 'sybase_ct', 'tidy', 'xmlrpc', 'xsl',
            // +opcache
        ],
        '5.4' => [
            'bz2', 'com_dotnet', 'curl', 'enchant', 'exif', 'fileinfo', 'gd', 'gettext', 'gmp',
            'imap', 'interbase', 'intl', 'ldap', 'mbstring', 'mysql', 'mysqli', 'oci8', 'oci8_11g', 'openssl',
            'pdo_firebird', 'pdo_mysql', 'pdo_oci', 'pdo_odbc', 'pdo_pgsql', 'pdo_sqlite', 'pgsql',
            'shmop', 'snmp', 'soap', 'sockets', 'sqlite3', 'sybase_ct', 'tidy', 'xmlrpc', 'xsl',
            // -sqlite
        ],
        '5.3' => [
            'bz2', 'com_dotnet', 'curl', 'enchant', 'exif', 'fileinfo', 'gd', 'gettext', 'gmp',
            'imap', 'interbase', 'intl', 'ldap', 'mbstring', 'mysql', 'mysqli', 'oci8', 'oci8_11g', 'openssl', 'pgsql',
            'pdo_firebird', 'pdo_mysql', 'pdo_oci', 'pdo_odbc', 'pdo_pgsql', 'pdo_sqlite',
            'shmop', 'snmp', 'soap', 'sockets', 'sqlite', 'sqlite3', 'sybase_ct', 'tidy', 'xmlrpc', 'xsl',
            // +com_dotnet +enchant +fileinfo +intl +oci8_11g +sqlite3
            // -dba -dbase -fdf -mcrypt -mhash -mime_magick -ming -msql -mssql -pspell -pdo_sqlite_external -zip
        ],
        '5.2' => [
            'bz2', 'curl', 'dba', 'dbase', 'exif', 'fdf', 'gd', 'gettext', 'gmp',
            'imap', 'interbase', 'ldap', 'mbstring', 'mcrypt', 'mhash', 'mime_magic', 'ming',
            'msql', 'mssql', 'mysql', 'mysqli', 'oci8', 'openssl', 'pgsql', 'pspell', 'pdo',
            'pdo_firebird', 'pdo_mssql', 'pdo_mysql', 'pdo_oci', 'pdo_oci8', 'pdo_odbc', 'pdo_pgsql', 'pdo_sqlite', 'pdo_sqlite_external',
            'shmop', 'snmp', 'soap', 'sockets', 'sqlite', 'sybase_ct', 'tidy', 'xmlrpc', 'xsl', 'zip',
            // -filepro -ifx +pdo_sqlite_external +zip
        ],
        '5.1' => [
            'bz2', 'curl', 'dba', 'dbase', 'exif', 'fdf', 'filepro', 'gd', 'gettext', 'gmp', 'ifx',
            'imap', 'interbase', 'ldap', 'mbstring', 'mcrypt', 'mhash', 'mime_magic', 'ming',
            'msql', 'mssql', 'mysql', 'mysqli', 'oci8', 'openssl', 'pgsql', 'pspell', 'pdo',
            'pdo_firebird', 'pdo_mssql', 'pdo_mysql', 'pdo_oci', 'pdo_oci8', 'pdo_odbc', 'pdo_pgsql', 'pdo_sqlite',
            'shmop', 'snmp', 'soap', 'sockets', 'sqlite', 'sybase_ct', 'tidy', 'xmlrpc', 'xsl',
            // +bz2 -cpdf -dbx -dio +gmp +pdo... -sybase_ct
        ],
        '5.0' => [
            'cpdf', 'curl', 'dba', 'dbase', 'dbx', 'dio', 'exif', 'fdf', 'filepro', 'gd', 'gettext', 'ifx',
            'imap', 'interbase', 'ldap', 'mbstring', 'mcrypt', 'mhash', 'mime_magic', 'ming',
            'msql', 'mssql', 'mysql', 'mysqli', 'oci8', 'openssl', 'oracle', 'pgsql', 'pspell',
            'shmop', 'snmp', 'soap', 'sockets', 'sybase_ct', 'tidy', 'xmlrpc', 'xsl',
            // -bz2 -crack -db -domxml -fribidi -hyperwave -iconv -java +mysql +mysqli -pdf +soap -w32api +tidy -xslt -yaz -zip
        ],
        '4.4' => [
            'bz2', 'cpdf', 'crack', 'curl', 'db', 'dba', 'dbase', 'dbx', 'domxml', 'exif',
            'fdf', 'filepro', 'fribidi', 'gd', 'gettext', 'hyperwave', 'iconv', 'ifx',
            'imap', 'interbase', 'java', 'ldap', 'mbstring', 'mcrypt', 'mhash', 'mime_magic', 'ming',
            'msql', 'mssql', 'oci8', 'openssl', 'oracle', 'pdf', 'pgsql', 'pspell',
            'shmop', 'snmp', 'sockets', 'sybase_ct', 'w32api', 'xmlrpc', 'xslt', 'yaz', 'zip',
        ],
        '4.3' => [
            'bz2', 'cpdf', 'crack', 'curl', 'db', 'dba', 'dbase', 'dbx', 'domxml', 'exif',
            'fdf', 'filepro', 'fribidi', 'gd', 'gettext', 'hyperwave', 'iconv', 'ifx',
            'imap', 'interbase', 'java', 'ldap', 'mbstring', 'mcrypt', 'mhash', 'mime_magic', 'ming',
            'msql', 'mssql', 'oci8', 'openssl', 'oracle', 'pdf', 'pgsql', 'pspell',
            'shmop', 'snmp', 'sockets', 'sybase_ct', 'w32api', 'xmlrpc', 'xslt', 'yaz', 'zip',
            // -gd(1) -iisfunc -ixsfunc +mcrypt +mime_magic -printer -overload +pspell -tokenizer -zlib +zip
        ],
        '4.2' => [
            'bz2', 'cpdf', 'ctype', 'curl', 'db', 'dba', 'dbase', 'dbx', 'domxml', 'exif',
            'fdf', 'filepro', 'gd', 'gd2', 'gettext', 'hyperwave', 'iconv', 'ifx', 'iisfunc',
            'imap', 'interbase', 'ixsfunc', 'java', 'ldap', 'mbstring', 'mhash', 'ming',
            'msql', 'mssql', 'oci8', 'openssl', 'oracle', 'overload', 'pdf', 'pgsql', 'printer',
            'shmop', 'snmp', 'sockets', 'sybase_ct', 'tokenizer', 'w32api', 'xmlrpc', 'xslt', 'yaz', 'zlib',
            // -cybercash -fbsql +iisfunc -ingres +ixsfunc -notes +overload +printer +tokenizer +w32api +xmlrpc
        ],
        '4.1' => [
            'bz2', 'cpdf', 'ctype', 'curl', 'cybercash', 'db', 'dba', 'dbase', 'dbx', 'domxml', 'exif', 'fbsql',
            'fdf', 'filepro', 'gd', 'gd2', 'gettext', 'hyperwave', 'iconv', 'ifx',
            'imap', 'ingres', 'interbase', 'java', 'ldap', 'mbstring', 'mhash', 'ming',
            'msql', 'mssql', 'notes', 'oci8', 'openssl', 'oracle', 'pdf', 'pgsql',
            'shmop', 'snmp', 'sockets', 'sybase_ct', 'xslt', 'yaz', 'zlib',
            // +dbx -dotnet +gd2 +iisfunc +mbstring +notes +printer -sablot +shmop +sockets +xslt
        ],
        '4.0' => [
            'bz2', 'cpdf', 'ctype', 'curl', 'cybercash', 'db', 'dba', 'dbase', 'domxml', 'dotnet', 'exif', 'fbsql',
            'fdf', 'filepro', 'gd', 'gettext', 'hyperwave', 'iconv', 'ifx', 'iisfunc',
            'imap', 'ingres', 'interbase', 'java', 'ldap', 'mhash', 'ming',
            'msql', 'mssql', 'oci8', 'openssl', 'oracle', 'pdf', 'pgsql', 'printer',
            'sablot', 'snmp', 'sybase_ct', 'yaz', 'zlib',
            // ...
        ],
        '3.0' => [
            'calendar', 'crypt', 'db2', 'dbase', 'dbm', 'fb',
            'filepro', 'ftp', 'gd', 'hyperwave', 'imap4r1',
            'interbase', 'ldap',
            'msql1', 'msql2', 'mssql', 'mssql70', 'mysql', 'oci73', 'oci80', 'pcre', 'pdflib',
            'snmp', 'solid23', 'xml', 'zlib',
        ],
    ];

    public static function isCore(string $ext, Version $version): bool
    {
        $key = $version->major . '.' . $version->minor;

        return isset(self::CORE[$key]) && in_array($ext, self::CORE[$key]);
    }

    public static function isBundled(string $ext, Version $version): bool
    {
        $key = $version->major . '.' . $version->minor;

        return isset(self::CORE[$key]) && in_array($ext, self::CORE[$key]);
    }

    public static function getDir(Version $version): string
    {
        if ($version->major <= 3) {
            return '';
        } elseif ($version->major < 5) {
            return '/extensions';
        } else {
            return '/ext';
        }
    }

    public static function getFilePrefix(Version $version): string
    {
        if ($version->major <= 3) {
            return 'php3_';
        } else {
            return 'php_';
        }
    }

    public static function getIniPrefix(Version $version): string
    {
        if ($version->major <= 3) {
            return 'php3_';
        } elseif ($version->major < 7 || ($version->major === 7 && $version->minor <= 1)) {
            return 'php_';
        } else {
            return '';
        }
    }

    public static function getIniSuffix(Version $version): string
    {
        if ($version->major < 7 || ($version->major === 7 && $version->minor <= 1)) {
            return '.dll';
        } else {
            return '';
        }
    }

    public static function dllName(Version $version, string $name): string
    {
        if ($name === 'memcached') {
            return 'memcache';
        } elseif ($name !== 'gd' && $name !== 'gd2') {
            return $name;
        } elseif ($version->major >= 8 || $version->major <= 3) {
            return 'gd';
        } elseif ($version->major >= 5) {
            return 'gd2';
        } elseif ($version->minor === 0) {
            return 'gd';
        } elseif ($version->minor >= 3) {
            return 'gd2';
        } else {
            return $name;
        }
    }

    public static function internalName(Version $version, string $name): string
    {
        if ($name === 'memcached') {
            return 'memcache';
        } elseif ($name === 'gd2') {
            return 'gd';
        } else {
            return $name;
        }
    }

}
