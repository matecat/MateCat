<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/03/2017
 * Time: 15:13
 */

namespace Features\Dqf\Service;

use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Features\Dqf\Service\Struct\MasterFileRequestStruct;



class MasterProjectFiles {

    /**
     * @var MasterFileRequestStruct[]
     */
    protected $files = [];

    protected $client ;
    /**
     * @var CreateProjectResponseStruct
     */
    protected $remoteProject ;

    public function __construct( Client $client, CreateProjectResponseStruct $remoteProject ) {
        $this->client = $client ;
        $this->remoteProject = $remoteProject ;
    }

    public function setFile( \Files_FileStruct $file, $numberOfSegments ) {
        $fileRequestStruct = new MasterFileRequestStruct();

        $fileRequestStruct->sessionId   = $this->client->getSession()->getSessionId();
        $fileRequestStruct->projectKey  = $this->remoteProject->dqfUUID ;

        // $fileRequestStruct->projectId = $this->remoteProject->dqfId ;

        $fileRequestStruct->name             = $file->filename ;
        $fileRequestStruct->clientId         = $file->id ;
        $fileRequestStruct->numberOfSegments = $numberOfSegments ;

        $this->files[] = $fileRequestStruct ;

        // TODO: keep working from here
    }

    public function send() {
        $curl = new \MultiCurlHandler();
        $url = sprintf( '/project/master/%s/file', $this->remoteProject->dqfId);

        foreach( $this->files as $file ) {

            $this->client->setHeaders( $file );
            $this->client->setPostParams( $file );

            $curl->createResource( $this->client->url( $url ), $this->client->getCurlOptions() );
        }

        $curl->multiExec();

        if ( count( $curl->getErrors() ) > 0 ) {
            throw new \Exception('Errors while creating files') ;
        }

        $result = $curl->getAllInfo();


    }
}