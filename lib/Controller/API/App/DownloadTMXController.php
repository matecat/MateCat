<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Klein\Response;
use Log;
use RuntimeException;
use TMS\TMSService;
use Utils;

class DownloadTMXController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function download(): Response
    {
        try {
            $request = $this->validateTheRequest();
            $res = $request['tmxHandler']->requestTMXEmailDownload(
                ( $request['download_to_email'] != false ? $request['download_to_email'] : $this->user->email ),
                $this->user->first_name,
                $this->user->last_name,
                $request['tm_key'],
                $request['strip_tags']
            );

            return $this->response->json([
                'errors' => [],
                'data' => $res,
            ]);

        } catch (Exception $exception){

            // Exception logging...
            $r = "<pre>";

            if(isset($data['download_to_email']) and !empty($data['download_to_email'])){
                $r .= print_r( "User Email: " . $data['download_to_email'], true ) . "\n";
            }

            $r .= print_r( "User ID: " . $this->user->uid, true ) . "\n";
            $r .= print_r( $exception->getMessage(), true ) . "\n";
            $r .= print_r( $exception->getTraceAsString(), true ) . "\n";

            $r .= "\n\n";
            $r .= " - REQUEST URI: " . print_r( @$_SERVER[ 'REQUEST_URI' ], true ) . "\n";
            $r .= " - REQUEST Message: " . print_r( $_REQUEST, true ) . "\n";
            $r .= "\n\n\n";
            $r .= "</pre>";

            Log::$fileName = 'php_errors.txt';
            $this->log( $r );

            try {
                Utils::sendErrMailReport( $r, "Download TMX Error: " . $e->getMessage() );
            } catch (Exception $exception){
                throw new RuntimeException("Error during sending the email");
            }

            return $this->returnException($exception);
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheRequest()
    {
        $id_job = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $tm_key = filter_var( $this->request->param( 'tm_key' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $tm_name = filter_var( $this->request->param( 'tm_name' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $downloadToken = filter_var( $this->request->param( 'downloadToken' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $download_to_email = filter_var( $this->request->param( 'email' ), FILTER_SANITIZE_EMAIL );
        $strip_tags = filter_var( $this->request->param( 'strip_tags' ), FILTER_VALIDATE_BOOLEAN );
        $source = filter_var( $this->request->param( 'source' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $target = filter_var( $this->request->param( 'target' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );

        if ( $download_to_email === false ) {
            throw new InvalidArgumentException("Invalid email provided for download.", -1);
        }

        if ( $tm_name === false ) {
            throw new InvalidArgumentException("Invalid TM name provided.", -2);
        }

        $tmxHandler = new TMSService();
        $tmxHandler->setName( $tm_name );

        return [
            'id_job' => $id_job,
            'password' => $password,
            'tm_key' => $tm_key,
            'tm_name' => $tm_name,
            'downloadToken' => $downloadToken,
            'download_to_email' => $download_to_email,
            'strip_tags' => $strip_tags,
            'source' => $source,
            'target' => $target,
            'tmxHandler' => $tmxHandler,
        ];
    }
}