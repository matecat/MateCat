<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 30/05/19
 * Time: 12.05
 *
 */

namespace Controller\API\App;


use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\WhitelistAccessValidator;
use Model\DataAccess\Database;
use RuntimeException;
use Utils\Registry\AppConfig;
use View\API\App\Json\Ping;

class HeartBeat extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new WhitelistAccessValidator( $this ) );
    }

    public function ping() {
        Database::obtain()->ping();
        if ( !touch( AppConfig::$ROOT . DIRECTORY_SEPARATOR . "touch" ) ) {
            throw new RuntimeException( "Storage unavailable." );
        }

        $format = new Ping( $this );
        $this->response->json( $format->render() );

    }

}