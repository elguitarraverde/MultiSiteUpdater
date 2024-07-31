<?php declare(strict_types=1);

namespace FacturaScripts\Plugins\MultiSiteUpdater\MultiSiteUpdater;

use DirectoryIterator;
use FacturaScripts\Core\Base\FileManager;
use FacturaScripts\Core\Base\Migrations;
use FacturaScripts\Core\Base\TelemetryManager;
use FacturaScripts\Core\Cache;
use FacturaScripts\Core\Http;
use FacturaScripts\Core\Internal\Forja;
use FacturaScripts\Core\Internal\Plugin;
use FacturaScripts\Core\Kernel;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use ZipArchive;

class MultiSiteUpdater
{
    public const CORE_ZIP_FOLDER = 'facturascripts';
    public const UPDATE_CORE_URL = 'https://facturascripts.com/DownloadBuild';

    /** @var array */
//    public $coreUpdateWarnings = [];

    /** @var TelemetryManager */
    public $telemetryManager;

    /** @var array */
    public $updaterItems = [];

    public function __construct()
    {
        $this->telemetryManager = new TelemetryManager();
    }

    public static function getCoreVersion(): float
    {
        return Kernel::version();
    }

    public static function getUpdateItems(): array
    {
        $items = [];

        // comprobamos si se puede actualizar algún plugin
        foreach (Plugins::list() as $plugin) {
            $item = self::getUpdateItemsPlugin($plugin);
            if (!empty($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Download selected update.
     *
     * @param string $idItem
     * @param string $disable
     *
     * @return bool
     */
    public function downloadAction(string $idItem, string $disable = ''): bool
    {
        $success = true;
        $this->updaterItems = self::getUpdateItems();
        foreach ($this->updaterItems as $key => $item) {
            if ($item['id'] != $idItem) {
                continue;
            }

            if (file_exists(Tools::folder($item['filename']))) {
                unlink(Tools::folder($item['filename']));
            }

            $url = $this->telemetryManager->signUrl($item['url']);
            $http = Http::get($url);
            if ($http->saveAs(Tools::folder($item['filename']))) {
                Tools::log()->notice('download-completed');
                $this->updaterItems[$key]['downloaded'] = true;
                break;
            }

            Tools::log()->error('download-error', [
                '%body%' => $http->body(),
                '%error%' => $http->errorMessage(),
                '%status%' => $http->status(),
            ]);
            $success = false;
        }

        // ¿Hay que desactivar algo?
        foreach (explode(',', $disable) as $plugin) {
            Plugins::disable($plugin);
        }

        return $success;
    }

    private static function getUpdateItemsPlugin(Plugin $plugin): array
    {
        $id = $plugin->forja('idplugin', 0);
        $fileName = 'update-' . $id . '.zip';
        foreach (Forja::getBuilds($id) as $build) {
            if ($build['version'] <= $plugin->version) {
                continue;
            }

            $item = [
                'description' => Tools::lang()->trans('plugin-update', [
                    '%pluginName%' => $plugin->name,
                    '%version%' => $build['version'],
                ]),
                'downloaded' => file_exists(Tools::folder($fileName)),
                'filename' => $fileName,
                'id' => $id,
                'name' => $plugin->name,
                'stable' => $build['stable'],
                'url' => self::UPDATE_CORE_URL . '/' . $id . '/' . $build['version'],
                'version' => $build['version'],
                'mincore' => $build['mincore'],
                'maxcore' => $build['maxcore'],
            ];

            if ($build['stable']) {
                return $item;
            }

            if ($build['beta'] && Tools::settings('default', 'enableupdatesbeta', false)) {
                return $item;
            }
        }

        return [];
    }

    private function postUpdateAction($plugName = ''): void
    {
        if ($plugName) {
            Plugins::deploy(true, true);
            return;
        }

        Migrations::run();
        Plugins::deploy(true, true);
    }

    /**
     * Extract zip file and update all files.
     *
     * @param string $idItem
     * @return bool
     */
    public function updateAction(string $idItem)
    {
        $fileName = 'update-' . $idItem . '.zip';

        // open the zip file
        $zip = new ZipArchive();
        $zipStatus = $zip->open(Tools::folder($fileName), ZipArchive::CHECKCONS);
        if ($zipStatus !== true) {
            Tools::log()->critical('ZIP ERROR: ' . $zipStatus);
            return false;
        }

        // get the name of the plugin to init after update (if the plugin is enabled)
        $init = '';
        foreach (self::getUpdateItems() as $item) {
            if ($idItem == Forja::CORE_PROJECT_ID) {
                break;
            }

            if ($item['id'] == $idItem && Plugins::isEnabled($item['name'])) {
                $init = $item['name'];
                break;
            }
        }

        // extract core/plugin zip file
        $done = $this->updatePlugin($zip, $fileName);

        if ($done) {
            Plugins::deploy(true, false);
            Cache::clear();
            $this->postUpdateAction($init);
            return true;
        }

        return false;
    }

    private function updatePlugin(ZipArchive $zip, string $fileName): bool
    {
        $zip->close();

        // use plugin manager to update
        $return = Plugins::add($fileName, 'plugin.zip', true);

        // remove zip file
        unlink(Tools::folder($fileName));
        return $return;
    }

    public function getPlugin($idPlugin)
    {
        $this->updaterItems = self::getUpdateItemsFromDisk();
        foreach ($this->updaterItems as $key => $item) {
            if ($item['id'] != $idPlugin) {
                continue;
            }

            return $item;
        }

        return null;
    }

    public static function getUpdateItemsFromDisk(): array
    {
        $items = [];

        // comprobamos si se puede actualizar algún plugin
        foreach (self::loadPluginsFromDisk() as $plugin) {
            $item = self::getUpdateItemsPlugin($plugin);
            if (!empty($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private static function loadPluginsFromDisk(): array
    {
        $folder = FS_FOLDER . DIRECTORY_SEPARATOR . 'Plugins';
        if (false === file_exists($folder)) {
            return [];
        }

        $plugins = [];
        $dir = new DirectoryIterator($folder);
        foreach ($dir as $file) {
            if (false === $file->isDir() || $file->isDot()) {
                continue;
            }

            $pluginName = $file->getFilename();
            $plugin = new Plugin(['name' => $pluginName]);
            $plugins[] = $plugin;
        }

        return $plugins;
    }
}
