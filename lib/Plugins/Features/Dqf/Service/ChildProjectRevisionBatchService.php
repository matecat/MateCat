<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 08/08/2017
 * Time: 15:44
 */

namespace Features\Dqf\Service;


use Exception;
use Features\Dqf\Service\Struct\Request\RevisionRequestStruct;
use Log;

class ChildProjectRevisionBatchService  {

    /** @var ISession */
    protected  $session ;

    /** @var Client */
    protected $client ;

    protected $structs = [] ;

    protected $resources = [] ;

    public function __construct(ISession $session) {
        $this->session = $session ;
        $this->client = new Client();
        $this->client->setSession( $this->session );
    }

    /**
     * @param RevisionRequestStruct $revision
     */
    public function addRevision( RevisionRequestStruct $revision ) {
        $this->structs[]  = $revision ;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function process() {

        foreach( $this->structs as $struct ) {
            $this->_createCurlResource( $struct );
        }

        $this->client->curl()->multiExec();

        foreach( $this->resources as $resource ) {
            if ( $error = $this->client->curl()->hasError( $resource ) ) {
                throw new Exception('Error sending revision batch: ' .
                        var_export( $this->client->curl()->getAllContents(), true )
                ) ;
            }
        }

        return $this->client->curl()->getAllContents() ;
    }

    protected function _createCurlResource( RevisionRequestStruct $struct ) {
        $url = "/project/child/%s/file/%s/targetLang/%s/translation/%s/batchReview" ;
        
        Log::doJsonLog( $struct->getParams() ) ;
        Log::doJsonLog( $this->session->filterHeaders( $struct) ) ;

        $resource = $this->client->createResource( $url, 'post', [
                'headers'    => $this->session->filterHeaders( $struct ),
                'pathParams' => $struct->getPathParams(),
                'json'       => $struct->getBody()
        ] );

        $this->resources[] = $resource ;
    }


}