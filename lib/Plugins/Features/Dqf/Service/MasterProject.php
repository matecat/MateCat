<?php



namespace Features\Dqf\Service;

use Exception;
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Features\Dqf\Service\Struct\ProjectRequestStruct ;
use INIT;
use Log;

class MasterProject {

    protected $session;
    protected $params;
    protected $request;

    public function __construct( Session $session ) {
        $this->session = $session ;
    }

    public function create( ProjectRequestStruct $projectRequestData ) {

        $projectRequestData->sessionId = $this->session->getSessionId() ;
        $projectRequestData->apiKey    = INIT::$DQF_API_KEY ;

        $client = new Client();
        $client->setSession( $this->session );

        $request = $client->createResource( '/project/master', 'post', [
                'formData' => $projectRequestData->getParams(),
                'headers'  => $projectRequestData->getHeaders()
        ] );

        $client->curl()->multiExec();

        $content = json_decode( $client->curl()->getSingleContent( $request ), true );

        Log::doLog( var_export( $content, true ) ) ;

        if ( $client->curl()->hasError( $request ) ) {
            throw new Exception('Error during project creation: ' . json_encode( $client->curl()->getErrors() ) ) ;
        }

        return new CreateProjectResponseStruct( $content );

    }

}
