<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 11/11/2016
 * Time: 16:01
 */

namespace ConnectedServices;


use AbstractControllers\AbstractStatefulKleinController;
use API\App\Json\ConnectedService;
use API\Commons\Exceptions\AuthenticationError;
use ConnectedServices\Google\GoogleProvider;
use Exception;
use Exceptions\NotFoundException;
use INIT;
use Utils;

class ConnectedServicesController extends AbstractStatefulKleinController {

    /**
     * @var ?ConnectedServiceStruct
     */
    protected ?ConnectedServiceStruct $connectedServiceStruct = null;

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function verify() {
        $this->__validateOwnership();

        if ( $this->connectedServiceStruct->service == ConnectedServiceDao::GDRIVE_SERVICE ) {
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
            $this->connectedServiceStruct->disabled_at = Utils::mysqlTimestamp( time() );
        } else {
            $this->connectedServiceStruct->disabled_at = null;
        }

        ConnectedServiceDao::updateStruct( $this->connectedServiceStruct, [ 'fields'=> [ 'disabled_at' ] ] );

        $this->refreshClientSessionIfNotApi();

        $formatter = new ConnectedService( [] );
        $this->response->json( [ 'connected_service' => $formatter->renderItem( $this->connectedServiceStruct ) ] );
    }

    /**
     * @throws Exception
     */
    private function __handleGDrive() {
        $verifier = new GDriveTokenVerifyModel( $this->connectedServiceStruct );

        $client = GoogleProvider::getClient( INIT::$HTTPHOST . "/gdrive/oauth/response" );

        if ( $verifier->validOrRefreshed( $client ) ) {
            $this->response->code( 200 );
        } else {
            $this->response->code( 403 );
        }

        $formatter = new ConnectedService( [] );
        $this->response->json( [ 'connected_service' => $formatter->renderItem( $verifier->getService() ) ] );
    }

    /**
     * @throws AuthenticationError
     * @throws NotFoundException
     */
    private function __validateOwnership() {

        // check for the user to be logged
        if ( !$this->user ) {
            throw new AuthenticationError( 'user not found' );
        }

        $serviceDao    = new ConnectedServiceDao();
        $this->connectedServiceStruct = $serviceDao->findServiceByUserAndId( $this->user, $this->request->param( 'id_service' ) );

        if ( !$this->connectedServiceStruct ) {
            throw new NotFoundException( 'connectedServiceStruct not found' );
        }
    }
}