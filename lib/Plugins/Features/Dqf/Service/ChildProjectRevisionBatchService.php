<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 08/08/2017
 * Time: 15:44
 */

namespace Features\Dqf\Service;


class ChildProjectRevisionBatchService  {

    public function __construct($session) {

        $this->session = $session ;
        $this->client = new Client();
        $this->client->setSession( $this->session );

    }
}