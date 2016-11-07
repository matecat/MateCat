<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/11/2016
 * Time: 16:05
 */

namespace ConnectedServices\GDrive;

use API\V2\KleinController;

class OAuthController extends KleinController
{

    public function response() {
        $this->response->json(array('ok' => true));
    }

    protected function afterConstruct() {

    }
}