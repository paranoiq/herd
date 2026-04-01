<?php

namespace Herd;

use Herd\App\Action;
use Herd\App\Choices;
use Herd\App\Argument;
use Herd\App\Option;
use Herd\App\RouteList;

#[RouteList(RouteList::CLI)]
#[Argument('expr', [self::class, 'expressionHelp'])]
#[Option('noAutoSelect', 'S')]
#[Option('noAutoActivate', 'A')]
#[Choices(['noColors', 'noLogo', 'clearCache'])]
class Application
{

    /** @param list<string> $versions */
    #[Action('configure', 'c', 'update php.ini files from templates', ['expr'])]
    public function configure(array $versions): void
    {

    }

    #[Action('select', 's', 'select version as default (levels: global|major|minor)', ['expr[:level]'])]
    public function selectVersion(): void
    {

    }

    public function listVersions(): void
    {

    }

    public function listLocalVersions(): void
    {

    }

    public function listRemoteVersions(): void
    {

    }

    public function listNewVersions(): void
    {

    }

    public function infoForVersion(): void
    {

    }

    /** @param list<string> $expr */
    #[Action('install', 'i', 'install versions', ['expr'])]
    public function installVersion(): void
    {

    }

    public function uninstallVersion(): void
    {

    }

    // extensions ------------------------------------------------------------------------------------------------------

    public function listExtensions(): void
    {

    }

    public function listLocalExtensions(): void
    {

    }

    public function listRemoteExtensions(): void
    {

    }

    public function listNewExtensions(): void
    {

    }

    public function infoForExtension(): void
    {

    }

    /** @param list<string> $versions */
    /** @param list<string> $extVersions */
    #[Action('install', 'i', 'install extension', ['expr', 'name', 'expr'])]
    public function installExtension(array $versions, string $name, ?array $extVersions = null): void
    {

    }

    /** @param list<string> $versions */
    /** @param list<string> $names */
    #[Action('install', 'i', 'install extensions', ['expr', 'names'])]
    public function installExtensions(array $versions, array $names): void
    {

    }

    /** @param list<string> $versions */
    /** @param list<string> $names */
    #[Action('uninstall', 'U', 'uninstall extensions', ['expr', 'names'])]
    public function uninstallExtension(array $versions, array $names): void
    {

    }

    /** @param list<string> $versions */
    /** @param list<string> $names */
    #[Action('uninstall', 'U', 'uninstall extensions', ['expr', 'names'])]
    public function uninstallExtensions(array $versions, array $names): void
    {

    }

    public function activateExtension(): void
    {

    }

    public function deactivateExtension(): void
    {

    }

}
