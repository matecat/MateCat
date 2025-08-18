<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Utils\TMS\TMSService;

class RequestExportTMXController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws Exception
     */
    public function download(): void {

        $request = $this->validateTheRequest();

        /**
         * @var TMSService $tmxHandler
         */
        $tmxHandler = $request[ 'tmxHandler' ];

        $res = $tmxHandler->requestTMXEmailDownload(
                $this->user->email,
                $this->user->first_name,
                $this->user->last_name,
                $request[ 'tm_key' ],
                $request[ 'strip_tags' ]
        );

        $this->response->json( [
                'errors' => [],
                'data'   => $res,
        ] );

    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheRequest(): array {
        $id_job            = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $password          = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $tm_key            = filter_var( $this->request->param( 'tm_key' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $tm_name           = filter_var( $this->request->param( 'tm_name' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $downloadToken     = filter_var( $this->request->param( 'downloadToken' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $download_to_email = filter_var( $this->request->param( 'email' ), FILTER_SANITIZE_EMAIL );
        $strip_tags        = filter_var( $this->request->param( 'strip_tags' ), FILTER_VALIDATE_BOOLEAN );
        $source            = filter_var( $this->request->param( 'source' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $target            = filter_var( $this->request->param( 'target' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );

        if ( $download_to_email === false ) {
            throw new InvalidArgumentException( "Invalid email provided for download.", -1 );
        }

        if ( $tm_name === false ) {
            throw new InvalidArgumentException( "Invalid TM name provided.", -2 );
        }

        $tmxHandler = new TMSService();
        $tmxHandler->setName( $tm_name );

        return [
                'id_job'            => $id_job,
                'password'          => $password,
                'tm_key'            => $tm_key,
                'tm_name'           => $tm_name,
                'downloadToken'     => $downloadToken,
                'download_to_email' => $download_to_email,
                'strip_tags'        => $strip_tags,
                'source'            => $source,
                'target'            => $target,
                'tmxHandler'        => $tmxHandler,
        ];
    }
}