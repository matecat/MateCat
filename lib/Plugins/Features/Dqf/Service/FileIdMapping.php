<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 14/07/2017
 * Time: 16:45
 */

namespace Features\Dqf\Service;

use Exception;
use Features\Dqf\Utils\Functions;
use Files_FileStruct;
use INIT;

class FileIdMapping {

    protected $session ;
    protected $file ;

    public function __construct(ISession $session, Files_FileStruct $file ) {
        $this->session = $session ;
        $this->file = $file ;
    }

    public function getRemoteId() {
        $client = new Client();
        $client->setSession( $this->session );

        $headers = [
                'sessionId'  =>   $this->session->getSessionId() ,
                'apiKey'     =>   INIT::$DQF_API_KEY ,
                'clientId'   =>   Functions::scopeId( $this->file->id )
        ];

        $resource = $client->createResource('/DQFFileId', 'get', [ 'headers' => $headers ]);

        $client->curl()->multiExec() ;
        $content = json_decode( $client->curl()->getSingleContent( $resource ), true ) ;

        if ( $client->curl()->hasError( $resource ) ) {
            throw new Exception('Error trying to get remote file id') ;
        }

        return $content['dqfId'] ;
    }
}