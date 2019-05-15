<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 17/07/2017
 * Time: 17:21
 */

namespace Features\Dqf\Service;

use Exception;
use Features\Dqf\Service\Struct\Request\ChildProjectTranslationRequestStruct;
use Log;

class TranslationBatchService extends AbstractService {

    protected $resources = [] ;
    protected $client;
    protected $structs = []  ;

    public function __construct( ISession $session ) {
        $this->session = $session ;
        $this->client = new Client();
        $this->client->setSession( $this->session );
    }

    public function addRequestStruct( ChildProjectTranslationRequestStruct $requestStruct ) {
        $this->structs[] = $requestStruct ;
    }

    public function process() {
        foreach( $this->structs as $struct ) {
            $this->_createCurlResource( $struct );
        }

        $this->client->curl()->multiExec();

        foreach( $this->resources as $resource ) {
            if ( $error = $this->client->curl()->hasError( $resource ) ) {
                throw new Exception('Error sending translation batch: ' .
                        var_export( $this->client->curl()->getAllContents(), true )
                ) ;
            }
        }

        return $this->client->curl()->getAllContents() ;
    }

    protected function _createCurlResource( ChildProjectTranslationRequestStruct $struct ) {
        $url = "/project/child/%s/file/%s/targetLang/%s/sourceSegment/translation/batch" ;

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