<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/11/2016
 * Time: 16:05
 */

namespace Controller\ConnectedServices\GDrive;

use Controller\Abstracts\AbstractStatefulKleinController;
use Exception;
use INIT;
use Model\ConnectedServices\GDriveUserAuthorizationModel;
use Model\Exceptions\ValidationError;
use ReflectionException;

class OAuthController extends AbstractStatefulKleinController {

    /**
     * @throws ReflectionException
     * @throws ValidationError
     */
    public function response() {

        if( empty( $this->request->param( 'state' ) ) || $_SESSION[ 'googledrive-' . INIT::$XSRF_TOKEN ] !== $this->request->param( 'state' ) ){
            $this->response->code( 401 );
            return;
        }

        unset( $_SESSION[ 'google-drive-' . INIT::$XSRF_TOKEN ] );

        $code  = $this->request->param( 'code' );
        $error = $this->request->param( 'error' );

        if ( isset( $code ) && $code ) {
            $this->__handleCode( $code );
        } else {
            if ( isset( $error ) ) {
                $this->__handleError( $error );
            }
        }

        $body =<<<EOF
<html><head>
<script> window.close(); </script>
</head>
</html>
EOF;

        $this->response->body( $body );
    }

    private function __handleError( $error ) {

    }

    /**
     * @throws ValidationError
     * @throws ReflectionException
     */
    private function __handleCode( $code ) {
        $model = new GDriveUserAuthorizationModel( $this->user );
        $model->updateOrCreateRecordByCode( $code );
        $this->refreshClientSessionIfNotApi();
    }

    /**
     * @throws Exception
     */
    protected function afterConstruct() {
        if ( !$this->user ) {
            throw new Exception( 'Logged user not found.' );
        }
    }

}