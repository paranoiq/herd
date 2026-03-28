<?php

namespace Herd;

use Herd\App\Action;

class Input
{

    /** @var array{string, ?string} */
    #[Action('all', 'a', 'list all (remote) versions', 'expr')]
    public array $listAll;

    /** @var array{string, ?string} */
    #[Action('local', 'l', 'list local versions', 'expr')]
    public array $listLocal;

    //public $listRemote;

    /** @var array{string, ?string} */
    #[Action('new', 'n', 'check for new versions', 'expr')]
    public array $listNew;

    /** @var array{string, ?string} */
    #[Action('info', 'f', 'info about version', 'expr')]
    public array $info;

    /** @var array{string, ?string} */
    #[Action('install', 'i', 'install versions', 'expr')]
    public array $install;

    /** @var array{string, ?string} */
    #[Action('uninstall', 'U', 'uninstall versions', 'expr')]
    public array $uninstall;

    /** @var array{string} */
    #[Action('select', 's', 'select version as default (levels: global|major|minor)', 'expr[:level]')]
    public array $select;

    // config ----------------------------------------------------------------------------------------------------------

    /** @var array{string} */
    #[Action('on', 'o', 'turn extension on', 'expr')]
    public $turnOn;

    /** @var array{string} */
    #[Action('off', 'O', 'turn extension off', 'expr')]
    public $rutnOff;

    /** @var array{string} */
    #[Action('configure', 'c', 'update php.ini files', 'expr')]
    public $configure;

    // extensions ------------------------------------------------------------------------------------------------------

    #[Action('select', 's', 'select version as default (levels: global|major|minor)', 'expr[:level]')]
    public $listExtensions;

    #[Action('select', 's', 'select version as default (levels: global|major|minor)', 'expr[:level]')]
    public $listLocalExtensions;

    //public $listRemoteExtensions;

    #[Action('select', 's', 'select version as default (levels: global|major|minor)', 'expr[:level]')]
    public $listNewExtensions;

    #[Action('select', 's', 'select version as default (levels: global|major|minor)', 'expr[:level]')]
    public $infoForExtension;

    #[Action('select', 's', 'select version as default (levels: global|major|minor)', 'expr[:level]')]
    public $installExtension;

    #[Action('select', 's', 'select version as default (levels: global|major|minor)', 'expr[:level]')]
    public $uninstallExtension;

    #[Action('select', 's', 'select version as default (levels: global|major|minor)', 'expr[:level]')]
    public $activateExtension;

    #[Action('select', 's', 'select version as default (levels: global|major|minor)', 'expr[:level]')]
    public $deactivateExtension;

}
