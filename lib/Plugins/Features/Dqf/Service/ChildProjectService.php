<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/03/2017
 * Time: 14:48
 */

namespace Features\Dqf\Service;

use Chunks_ChunkStruct;
use Exception;
use Features\Dqf\Model\ChildProjectsMapStruct;
use Features\Dqf\Model\UserModel;
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Features\Dqf\Service\Struct\Request\ChildProjectRequestStruct;
use Features\Dqf\Service\Struct\Request\ChildProjectTranslationRequestStruct;
use Features\Dqf\Service\Struct\Request\ProjectTargetLanguageRequestStruct;
use Features\Dqf\Service\Struct\Response\MasterFileResponseStruct;
use Features\Dqf\Service\Struct\Response\ProjectResponseStruct;
use Features\Dqf\Utils\Functions;

class ChildProjectService {
    const TRANSLATION = 'translation' ;
    const REVIEW = 'review' ;

    /**
     * @var Session
     */
    protected $session ;

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk ;

    protected $remoteProjects;

    public function __construct(Session $session, Chunks_ChunkStruct $chunk ) {
        $this->chunk   = $chunk  ;
        $this->session = $session ;
    }

    public function updateChildProjects( $requestStructs ) {
        $client = new Client() ;
        $client->setSession( $this->session );

        $resources = [] ;

        /** @var ChildProjectRequestStruct $requestStruct */
        foreach( $requestStructs as $requestStruct ) {
            $resources[] = $client->createResource('/project/child/%s', 'put', [
                    'headers'    => $requestStruct->getHeaders(),
                    'pathParams' => $requestStruct->getPathParams(),
                    'formData'   => $requestStruct->getParams()
            ]) ;
        }

        $client->execRequests();

        $responses = $client->curl()->getAllContents();

        if ( count($client->curl()->getErrors() ) > 0 ) {
            throw  new Exception('Error on update of remote child project: ' . implode( ', ',  $client->curl()->getAllContents() ) ) ;
        }

        $returnable = $client->curl()->getAllContents();

        // $returnable =  array_map( function( $item ) {
        //     return new ProjectResponseStruct( json_decode( $item, true )['model'] );
        // }, $responses );

        return $returnable  ;
    }

    public function getRemoteResources( $requestStructs ) {
        $client = new Client();
        $client ->setSession( $this->session ) ;

        $resources = [] ;
        /** @var ChildProjectRequestStruct $requestStruct */
        foreach( $requestStructs as $requestStruct ) {
            $resources[] = $client->createResource('/project/child/%s', 'get', [
                    'headers'    => $requestStruct->getHeaders(),
                    'pathParams' => $requestStruct->getPathParams()
            ]) ;
        }

        $client->execRequests();

        $responses = $client->curl()->getAllContents();

        if ( count($client->curl()->getErrors() ) > 0 ) {
            throw  new Exception('Error while fetching remote child project' );
        }

        $returnable =  array_values( array_map( function( $item ) {
            return new ProjectResponseStruct( json_decode( $item, true )['model'] );
        }, $responses ) ) ;

        return $returnable  ;
    }

    /***
     * @param ChildProjectsMapStruct    $dqfChildProject
     * @param ChildProjectRequestStruct $request
     *
     * @return array
     *
     * Find back the remote project, merge data and update the resource again.
     */
    public function updateTranslationChild(ChildProjectsMapStruct $dqfChildProject, ChildProjectRequestStruct $request) {
        $client = new Client();
        $client ->setSession( $this->session ) ;

        $resource = $client->createResource('/project/child/%s', 'put', [
                'headers'     =>  $request->getHeaders(),
                'formData'    =>  $request->getParams(),
                'pathParams'  =>  $request->getPathParams()
        ]);

        $client->execRequests() ;

        $response =  $client->curl()->getAllContents();

        return $response ;
    }

    /**
     * Creates a translation child for the given input parent project.
     *
     * @param remoteFiles MasterFileResponseStruct[]
     *
     */
    public function createTranslationChild(CreateProjectResponseStruct $parent, $remoteFiles ) {
        $projectStruct            = new ChildProjectRequestStruct() ;
        $projectStruct->sessionId = $this->session->getSessionId();
        $projectStruct->clientId  = Functions::scopeId( $this->chunk->getIdentifier() );
        $projectStruct->parentKey = $parent->dqfUUID ;
        $projectStruct->type      = self::TRANSLATION ;

        $client = new Client() ;
        $client->setSession( $this->session );
        $resource = $client->createResource('/project/child', 'post', [
                'formData' => $projectStruct->getParams(),
                'headers'  => $projectStruct->getHeaders()
        ]);

        $client->execRequests();

        if ( count( $client->curl()->getErrors() ) > 0 ) {
            throw new Exception( 'Error in creation of child project: ' . implode( $client->curl()->getAllContents() ) ) ;
        }

        $childProject = new CreateProjectResponseStruct( json_decode( $client->curl()->getSingleContent( $resource ), true ) );

        $client = new Client() ;
        $client->setSession( $this->session );

        foreach( $this->chunk->getFiles() as $file ) {
            // for each file in the chunk create a
            $languageStruct = new ProjectTargetLanguageRequestStruct();
            $languageStruct->fileId = $remoteFiles[ Functions::scopeId($file->id) ]->dqfId ;
            $languageStruct->projectKey = $childProject->dqfUUID ;
            $languageStruct->projectId = $childProject->dqfId ;
            $languageStruct->targetLanguageCode = $this->chunk->target ;

            $client->createResource('/project/child/%s/file/%s/targetLang', 'post', [
                    'formData'   => $languageStruct->getParams(),
                    'headers'    => $languageStruct->getHeaders(),
                    'pathParams' => $languageStruct->getPathParams()
            ]);
        }

        $client->curl()->multiExec();

        if ( count( $client->curl()->getErrors() ) > 0 ) {
            throw new Exception( 'Error in creation of child project: ' . implode( $client->curl()->getAllContents() ) ) ;
        }

        return $childProject ;
    }

}