<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 11/11/2016
 * Time: 16:01
 */

namespace ConnectedServices;


use API\App\Json\ConnectedService;
use API\Commons\AbstractStatefulKleinController;
use Bootstrap;
use Exception;
use Exceptions\NotFoundException;
use INIT;
use Users_UserStruct;

class ConnectedServicesController extends AbstractStatefulKleinController {

    /**
     * @var Users_UserStruct
     */
    protected $user;

    /**
     * @var ConnectedServiceStruct
     */
    protected $service;

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function verify() {
        $this->__validateOwnership();

        if ( $this->service->service == ConnectedServiceDao::GDRIVE_SERVICE ) {
            $this->__handleGDrive();
        }
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function update() {
        $this->__validateOwnership();

        $params = filter_var_array( $this->request->params(), [
                'disabled' => FILTER_VALIDATE_BOOLEAN
        ] );

        if ( $params[ 'disabled' ] ) {
            $this->service->disabled_at = \Utils::mysqlTimestamp( time() );
        } else {
            $this->service->disabled_at = null;
        }

        ConnectedServiceDao::updateStruct( $this->service, [ 'disabled_at' ] );

        $formatter = new ConnectedService( [] );
        $this->response->json( [ 'connected_service' => $formatter->renderItem( $this->service ) ] );
    }

    protected function afterConstruct() {
        Bootstrap::sessionClose();
    }

    /**
     * @throws Exception
     */
    private function __handleGDrive() {
        $verifier = new GDriveTokenVerifyModel( $this->service );

        $client = GoogleClientFactory::getGoogleClient( INIT::$HTTPHOST . "/gdrive/oauth/response" );

        if ( $verifier->validOrRefreshed( $client ) ) {
            $this->response->code( 200 );
        } else {
            $this->response->code( 403 );
        }

        $formatter = new ConnectedService( [] );
        $this->response->json( [ 'connected_service' => $formatter->renderItem( $verifier->getService() ) ] );
    }

    /**
     * @throws NotFoundException
     */
    private function __validateOwnership() {

        // check for the user to be logged
        if ( !$this->user ) {
            throw new NotFoundException( 'user not found' );
        }

        $serviceDao    = new ConnectedServiceDao();
        $this->service = $serviceDao->findServiceByUserAndId( $this->user, $this->request->param( 'id_service' ) );

        if ( !$this->service ) {
            throw new NotFoundException( 'service not found' );
        }
    }
}