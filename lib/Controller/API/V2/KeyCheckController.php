<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 30/01/2017
 * Time: 18:09
 */

namespace API\V2;


class KeyCheckController extends KleinController {

    public function ping() {
        if ( !$this->api_record ) {
            throw new AuthenticationError() ;
        }

        $this->response->code(200) ;
    }

}