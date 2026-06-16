<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 11/11/2016
 * Time: 16:01
 */

namespace Controller\API\App;


use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Model\ConnectedServices\ConnectedServiceDao;
use Model\ConnectedServices\ConnectedServiceStruct;
use Model\ConnectedServices\GDrive\GDriveTokenVerifyModel;
use Model\ConnectedServices\Oauth\Google\GoogleProvider;
use Model\Exceptions\NotFoundException;
use PDOException;
use TypeError;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;
use View\API\App\Json\ConnectedService;

class ConnectedServicesController extends AbstractStatefulKleinController
{

    /**
     * @var ?ConnectedServiceStruct
     */
    protected ?ConnectedServiceStruct $connectedServiceStruct = null;

    /**
     * @return void
     */
    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }


    /**
     * @throws NotFoundException
     * @throws Exception
     * @throws TypeError
     */
    public function verify(): void
    {
        $this->__validateOwnership();

        if ($this->connectedServiceStruct->service == ConnectedServiceDao::GDRIVE_SERVICE) {
            $this->__handleGDrive();
        }
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     * @throws TypeError
     */
    public function update(): void
    {
        $this->__validateOwnership();
        $service = $this->connectedServiceStruct ?? throw new TypeError('connectedServiceStruct is null');

        $params = filter_var_array($this->request->params(), [
            'disabled' => FILTER_VALIDATE_BOOLEAN
        ]);

        if ($params['disabled']) {
            $service->disabled_at = Utils::mysqlTimestamp(time());
        } else {
            $service->disabled_at = null;
        }

        (new ConnectedServiceDao($this->db()))->updateStruct($service, ['fields' => ['disabled_at']]);

        $this->refreshClientSessionIfNotApi();

        $formatter = new ConnectedService([]);
        $this->response->json(['connected_service' => $formatter->renderItem($service)]);
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    private function __handleGDrive(): void
    {
        $service = $this->connectedServiceStruct ?? throw new TypeError('connectedServiceStruct is null');
        $verifier = new GDriveTokenVerifyModel($service);

        $client = (new GoogleProvider())->getClient(AppConfig::$HTTPHOST . "/gdrive/oauth/response");

        if ($verifier->validOrRefreshed($client)) {
            $this->response->code(200);
        } else {
            $this->response->code(403);
        }

        $formatter = new ConnectedService([]);
        $this->response->json(['connected_service' => $formatter->renderItem($verifier->getService())]);
    }

    /**
     * @throws NotFoundException
     * @throws PDOException
     *
     * @phpstan-assert !null $this->connectedServiceStruct
     */
    private function __validateOwnership(): void
    {
        $serviceDao = new ConnectedServiceDao($this->db());
        $this->connectedServiceStruct = $serviceDao->findServiceByUserAndId($this->user, $this->request->param('id_service'));

        if (!$this->connectedServiceStruct) {
            throw new NotFoundException('connectedServiceStruct not found');
        }
    }
}