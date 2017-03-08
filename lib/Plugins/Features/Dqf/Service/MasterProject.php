<?php



namespace Features\Dqf\Service;

use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Features\Dqf\Service\Struct\ProjectRequestStruct ;
use API\V2\Exceptions\AuthenticationError ;
use Features\Dqf\Service\Struct\LoginResponseStruct ;


class MasterProject {

    protected $session;
    protected $params;
    protected $request;

    public function __construct( Client $client ) {
        $this->client = $client ;
        $this->session = $client->getSession();
    }

    public function create( ProjectRequestStruct $projectRequestData ) {
        $curl = new \MultiCurlHandler();

        $projectRequestData->sessionId = $this->session->getSessionId() ;
        $projectRequestData->apiKey = \INIT::$DQF_API_KEY ;

        $this->client->setHeaders( $projectRequestData ) ;
        $this->client->setPostParams( $projectRequestData ) ;

        $request = $curl->createResource(
                $this->client->url('/project/master'),
                $this->client->getCurlOptions()
        );

        $curl->multiExec();
        $curl->setRequestHeader( $request );

        $content = json_decode( $curl->getSingleContent( $request ), true );

        \Log::doLog( var_export( $content, true ) ) ;

        if ( $curl->hasError( $request ) ) {
            // TODO: log error

        }

        return new CreateProjectResponseStruct( $content );

    }

}
