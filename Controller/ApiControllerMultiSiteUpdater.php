<?php declare(strict_types=1);

namespace FacturaScripts\Plugins\MultiSiteUpdater\Controller;

use FacturaScripts\Core\Template\ApiController;
use FacturaScripts\Plugins\MultiSiteUpdater\MultiSiteUpdater\MultiSiteUpdater;
use Symfony\Component\HttpFoundation\Response;

class ApiControllerMultiSiteUpdater extends ApiController
{
    protected function runResource(): void
    {
        $action = $this->request->request->get('action', $this->request->query->get('action', ''));
        switch ($action) {
            case 'update-plugin':
                $response = $this->updatePlugin();
                break;

            default:
                $response = $this->jsonResponse(true, 'OK', MultiSiteUpdater::getUpdateItems());
        }

        $this->response->setContent(json_encode($response));
    }

    protected function updatePlugin()
    {
        $multiSiteUpdater = new MultiSiteUpdater();

        // descargamos y actualizamos el plugin
        $idItem = $this->request->get('item', '');
        $updatedPlugin = $multiSiteUpdater->downloadAction($idItem) && $multiSiteUpdater->updateAction($idItem);

        // obtenemos los datos del plugin actualizado
        $idPlugin = $this->request->get('item', '');
        $plugin = $multiSiteUpdater->getPlugin($idPlugin);

        $response = $this->jsonResponse(true, 'Plugin actualizado correctamente', compact('plugin'));

        if (!$updatedPlugin) {
            $response = $this->jsonResponse(false, 'Error al actualizar el Plugin', compact('plugin'));
            $this->response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $response;
    }

    protected function jsonResponse(bool $success, string $message, array $data)
    {
        return compact('success', 'message', 'data');
    }
}
