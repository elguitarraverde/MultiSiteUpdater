<?php declare(strict_types=1);

namespace FacturaScripts\Plugins\MultiSiteUpdater;

use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Core\Kernel;
use FacturaScripts\Dinamic\Controller\ApiRoot;

class Init extends InitClass
{
    /** Code to load every time FacturaScripts starts. */
    public function init(): void
    {
        Kernel::addRoute('/api/3/multisiteupdater', 'ApiControllerMultiSiteUpdater');
        ApiRoot::addCustomResource('multisiteupdater');
    }

    /** Code that is executed when uninstalling a plugin. */
    public function uninstall(): void
    {
        // TODO: Implement uninstall() method.
    }

    /** Code to load every time the plugin is enabled or updated. */
    public function update(): void
    {
        // TODO: Implement update() method.
    }
}
