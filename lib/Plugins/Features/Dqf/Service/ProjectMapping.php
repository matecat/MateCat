<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 29/08/2017
 * Time: 18:10
 */

namespace Features\Dqf\Service;


use Exception;
use Features\Dqf\Model\DqfProjectMapStruct;
use Features\Dqf\Utils\Functions;
use INIT;

class ProjectMapping {

    protected $session ;
    /**
     * @var DqfProjectMapStruct
     */
    protected $project ;

    public function __construct( ISession $session, DqfProjectMapStruct $project ) {
        $this->session = $session ;
        $this->project = $project ;
    }

    public function getRemoteId() {
        $client = new Client();
        $client->setSession( $this->session );

        $headers = [
                'sessionId'  =>   $this->session->getSessionId() ,
                'apiKey'     =>   INIT::$DQF_API_KEY ,
                'clientId'   =>   Functions::scopeId( $this->project->id )
        ];

        $resource = $client->createResource('/DQFProjectId', 'get', [ 'headers' => $headers ]);

        $client->curl()->multiExec() ;
        $content = json_decode( $client->curl()->getSingleContent( $resource ), true ) ;

        if ( $client->curl()->hasError( $resource ) ) {
            throw new Exception('Error trying to get remote project id') ;
        }

        return $content ;

    }
}
