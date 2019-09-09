<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 08/12/2016
 * Time: 09:45
 */

namespace API\App;


use Bootstrap;
use Exceptions\NotFoundException;
use Users\MetadataModel;

class UserMetadataController extends AbstractStatefulKleinController {

    public function update() {

        if ( !$this->user ) {
            throw new NotFoundException( 'user not found' );
        }

        $model = new MetadataModel( $this->user, $this->request->param( 'metadata' ) );

        $model->save();
        $data = $this->user->getMetadataAsKeyValue();

        if ( empty ( $data ) ) {
            $data = null;
        }

        $this->response->json( $data );
    }

    protected function afterConstruct() {
        Bootstrap::sessionClose();
    }

}