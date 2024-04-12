<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 30/05/19
 * Time: 12.05
 *
 */

namespace API\App;


use API\App\Json\Ping;
use API\V2\KleinController;
use API\V2\Validators\WhitelistAccessValidator;
use INIT;
use RuntimeException;

class HeartBeat extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new WhitelistAccessValidator( $this ) );
    }

    public function ping() {
        \Database::obtain()->ping();
        if ( !touch( INIT::$ROOT . DIRECTORY_SEPARATOR . "touch" ) ) {
            throw new RuntimeException( "Storage unavailable." );
        }

        $format = new Ping( $this );
        $this->response->json( $format->render() );

    }

}