<?php declare(strict_types=1);

namespace FacturaScripts\Plugins\MultiSiteUpdater\Controller;

use FacturaScripts\Core\Internal\Forja;
use FacturaScripts\Core\Plugins;
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
        $idPlugin = $this->request->get('id', '');

        // evitamos que se actualice el Core
        if($idPlugin == Forja::CORE_PROJECT_ID){
            return $this->jsonResponse(false, 'No está permitido actualizar el Core desde la API', []);
        }

        $multiSiteUpdater = new MultiSiteUpdater();
        $updatedPlugin = $multiSiteUpdater->downloadAction($idPlugin) && $multiSiteUpdater->updateAction($idPlugin);

        // obtenemos los datos del plugin actualizado
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
