<?php

namespace Features\Dqf\Service;

use Exception;
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Features\Dqf\Service\Struct\ProjectRequestStruct;
use Features\Dqf\Service\Struct\Response\ProjectResponseStruct;
use INIT;
use Log;

class MasterProject {

    protected $session;
    protected $params;
    protected $request;

    public function __construct( ISession $session ) {
        $this->session = $session ;
    }

    public function create( ProjectRequestStruct $projectRequestData ) {

        $projectRequestData->sessionId = $this->session->getSessionId() ;
        $projectRequestData->apiKey    = INIT::$DQF_API_KEY ;

        $client = new Client();
        $client->setSession( $this->session );

        $request = $client->createResource( '/project/master', 'post', [
                'formData' => $projectRequestData->getParams(),
                'headers'  => $this->session->filterHeaders( $projectRequestData )
        ] );

        $client->curl()->multiExec();

        $content = json_decode( $client->curl()->getSingleContent( $request ), true );

        Log::doJsonLog( var_export( $content, true ) ) ;

        if ( $client->curl()->hasError( $request ) ) {
            throw new Exception('Error during project creation: ' . json_encode( $client->curl()->getErrors() ) ) ;
        }

        return new CreateProjectResponseStruct( $content );
    }

    public function getByDqfId( $mappingResponse ) {

        $requestStruct             = new ProjectRequestStruct();
        $requestStruct->projectId  = $mappingResponse['dqfId'] ;
        $requestStruct->projectKey = $mappingResponse['dqfUUID'];

        $requestStruct->sessionId = $this->session->getSessionId() ;
        $requestStruct->apiKey    = INIT::$DQF_API_KEY ;

        $client = new Client();
        $client->setSession( $this->session );

        $request = $client->createResource( '/project/master/%s', 'get', [
                'headers'  => $this->session->filterHeaders( $requestStruct ),
                'pathParams' => $requestStruct->getPathParams()
        ] );

        $client->curl()->multiExec();

        $content = json_decode( $client->curl()->getSingleContent( $request ), true );

        Log::doJsonLog( var_export( $content, true ) ) ;

        if ( $client->curl()->hasError( $request ) ) {
            throw new Exception('Error while fetching project: ' . json_encode( $client->curl()->getErrors() ) ) ;
        }

        return new ProjectResponseStruct( $content['model'] );

    }

}
