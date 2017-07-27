<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 08/12/2016
 * Time: 09:45
 */

namespace API\App;


use API\V2\KleinController;
use Bootstrap;
use Exceptions\NotFoundError;
use Users\MetadataDao;
use Users\MetadataModel;
use Users_UserDao;

class UserMetadataController extends AbstractStatefulKleinController {

    public function update() {
        $dao  = new Users_UserDao();
        $user = $dao->getByUid( $_SESSION[ 'uid' ] );

        if ( !$user ) {
            throw new NotFoundError( 'user not found' );
        }

        $model = new MetadataModel( $user, $this->request->param( 'metadata' ) );

        $model->save();
        $data = $user->getMetadataAsKeyValue();

        if ( empty ( $data ) ) {
            $data = null;
        }

        $this->response->json( $data );
    }

    protected function afterConstruct() {
        Bootstrap::sessionClose();
    }

}