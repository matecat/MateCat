<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/11/2016
 * Time: 16:05
 */

namespace ConnectedServices\GDrive;

use ConnectedServices\GDriveUserAuthorizationModel;

class OAuthController extends \BaseKleinViewController
{

    /**
     * @var \Users_UserStruct
     */
    protected  $user ;

    public function response() {

        // get the cod e from querystring
        // use the code to ask for a token
        // encrypt the token in database

        // associated with the service....

        // TODO: ensure the user is logged in

        // TODO: sanitize this
        $code = $this->request->param( 'code' );
        $error = $this->request->param( 'error' );

        if ( isset($code) && $code ) {
            $this->__handleCode( $code ) ;
        } else if ( isset( $error ) ) {
            $this->__handleError( $error );
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
        // TODO:
    }

    private function __handleCode( $code ) {
        $model = new GDriveUserAuthorizationModel( $this->user );
        $model->updateOrCreateRecordByCode( $code ) ;
    }

    protected function afterConstruct() {

        if ( !$this->user ) {
            throw  new \Exception('Logged user not found.') ;
        }

        $this->setView( \INIT::$TEMPLATE_ROOT . '/ConnectedServices/gdrive_oauth.html');

    }
}