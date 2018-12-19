<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 11/11/2016
 * Time: 16:01
 */

namespace ConnectedServices;


use API\App\AbstractStatefulKleinController;
use API\App\Json\ConnectedService;
use Exceptions\NotFoundException;

class ConnectedServicesController extends AbstractStatefulKleinController  {

    /**
     * @var \Users_UserStruct
     */
    protected $user ;

    /**
     * @var ConnectedServiceStruct
     */
    protected $service ;

    public function verify() {
        $this->__validateOwnership();

        if ( $this->service->service == ConnectedServiceDao::GDRIVE_SERVICE ) {
            $this->__handleGDrive();
        }
    }

    public function update() {
        $this->__validateOwnership() ;

        $params = filter_var_array( $this->request->params(), array(
            'disabled' => FILTER_VALIDATE_BOOLEAN
        ));

        if ( $params['disabled'] ) {
            $this->service->disabled_at = \Utils::mysqlTimestamp( time() ) ;
        }
        else {
           $this->service->disabled_at = null ;
        }

        ConnectedServiceDao::updateStruct($this->service, array('disabled_at') ) ;

        $formatter = new ConnectedService( array() );
        $this->response->json( array( 'connected_service' => $formatter->renderItem( $this->service ) ) ) ;
    }

    protected function afterConstruct() {
        \Bootstrap::sessionClose();
    }

    private function __handleGDrive() {
        $verifier = new GDriveTokenVerifyModel( $this->service ) ;

        if ( $verifier->validOrRefreshed() ) {
            $this->response->code( 200 ) ;
        }
        else {
            $this->response->code( 403 ) ;
        }

        $formatter = new ConnectedService( array() );
        $this->response->json( array( 'connected_service' => $formatter->renderItem( $verifier->getService() ) ) ) ;
    }

    private function __validateOwnership() {

        // check for the user to be logged
        if ( !$this->user ) {
            throw new NotFoundException('user not found') ;
        }

        $serviceDao = new ConnectedServiceDao();
        $this->service = $serviceDao->findServiceByUserAndId( $this->user, $this->request->param('id_service') );

        if ( !$this->service ) {
            throw new NotFoundException( 'service not found' ) ;
        }
    }
}