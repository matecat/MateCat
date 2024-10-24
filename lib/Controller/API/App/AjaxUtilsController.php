<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use ConnectedServices\Google\GDrive\Session;
use Database;
use Exception;
use InvalidArgumentException;
use Klein\Response;
use TMS\TMSService;

class AjaxUtilsController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function ping(): Response
    {
        $db   = Database::obtain();
        $stmt = $db->getConnection()->prepare( "SELECT 1" );
        $stmt->execute();

        return $this->response->json([
            'data' => [
                "OK", time()
            ]
        ]);
    }

    public function checkTMKey(): Response
    {
        try {
            $tm_key = filter_var( $this->request->param( 'tm_key' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
            $tmxHandler = new TMSService();
            $keyExists = $tmxHandler->checkCorrectKey( $tm_key );

            if ( !isset( $keyExists ) || $keyExists === false ) {
                throw new InvalidArgumentException("TM key is not valid.", -9);
            }

            return $this->response->json([
                'success' => true
            ]);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    public function clearNotCompletedUploads(): Response
    {
        try {
            ( new Session() )->cleanupSessionFiles();

            return $this->response->json([
                'success' => true
            ]);

        } catch ( Exception $exception ) {
            return $this->returnException($exception);
        }
    }
}