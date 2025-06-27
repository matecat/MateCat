<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Database;
use Exception;
use InvalidArgumentException;
use Klein\Response;
use TMS\TMSService;
use Utils\ConnectedServices\Google\GDrive\Session;

class AjaxUtilsController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function ping(): void {
        $db   = Database::obtain();
        $stmt = $db->getConnection()->prepare( "SELECT 1" );
        $stmt->execute();

        $this->response->json( [
                'data' => [
                        "OK", time()
                ]
        ] );
    }

    /**
     * @return void
     * @throws Exception
     */
    public function checkTMKey(): void {

        $tm_key = filter_var( $this->request->param( 'tm_key' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );

        if ( empty( $tm_key ) ) {
            throw new InvalidArgumentException( "TM key not provided.", -9 );
        }

        $tmxHandler = new TMSService();
        $keyExists  = $tmxHandler->checkCorrectKey( $tm_key );

        if ( !isset( $keyExists ) or $keyExists === false ) {
            throw new InvalidArgumentException( "TM key is not valid.", -9 );
        }

        $this->response->json( [
                'success' => true
        ] );

    }

    /**
     * @return Response
     */
    public function clearNotCompletedUploads(): void {

        ( new Session() )->cleanupSessionFiles();

        $this->response->json( [
                'success' => true
        ] );
    }
}