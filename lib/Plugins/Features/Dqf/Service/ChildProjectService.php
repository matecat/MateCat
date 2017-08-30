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
use Features\Dqf\Model\DqfProjectMapStruct;
use Features\Dqf\Model\UserModel;
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Features\Dqf\Service\Struct\Request\ChildProjectRequestStruct;
use Features\Dqf\Service\Struct\Request\ChildProjectTranslationRequestStruct;
use Features\Dqf\Service\Struct\Request\ProjectTargetLanguageRequestStruct;
use Features\Dqf\Service\Struct\Response\MaserFileCreationResponseStruct;
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

    public function __construct(Session $session, Chunks_ChunkStruct $chunk, $splittedIndex = null ) {
        $this->chunk   = $chunk  ;
        $this->session = $session ;
        $this->clientId = $this->chunk->getIdentifier();

        if ( !is_null( $splittedIndex ) ) {
            $this->clientId .= '-' . $splittedIndex ;
        }
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

        if ( count($client->curl()->getErrors() ) > 0 ) {
            throw  new Exception('Error on update of remote child project: ' . implode( ', ',  $client->curl()->getAllContents() ) ) ;
        }

        $returnable = $client->curl()->getAllContents();

        return $returnable  ;
    }

    public function setCompleted(ChildProjectRequestStruct $requestStruct ) {
        $client = new Client();
        $client->setSession( $this->session );

        $resource =  $client->createResource('/project/child/%s/status', 'put', [
                'headers'    =>  $requestStruct->getHeaders(),
                'pathParams' =>  $requestStruct->getPathParams(),
                'formData'   =>  ['status' => 'completed']
        ] );

        $client->execRequests();
        $this->_checkError( $client, 'Error while updating child project status to completed.');

        return $client->curl()->getSingleContent( $resource );

    }

    private function _checkError( Client $client, $message ) {
        $client->execRequests();

        if ( count($client->curl()->getErrors() ) > 0 ) {
            throw  new Exception( $message . ' - ' . var_export( $client->curl()->getAllContents(), true ) );
        }
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
            throw  new Exception('Error while fetching remote child project: ' . var_export( $responses, true ) );
        }

        $returnable =  array_values( array_map( function( $item ) {
            return new ProjectResponseStruct( json_decode( $item, true )['model'] );
        }, $responses ) ) ;

        return $returnable  ;
    }

    /***
     * @param DqfProjectMapStruct       $dqfChildProject
     * @param ChildProjectRequestStruct $request
     *
     * @return array
     *
     * Find back the remote project, merge data and update the resource again.
     */
    public function updateTranslationChild( DqfProjectMapStruct $dqfChildProject, ChildProjectRequestStruct $request) {
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
     * @param CreateProjectResponseStruct       $parent
     * @param MaserFileCreationResponseStruct[] $remoteFiles
     *
     * @param UserModel                         $assignee
     *
     * @return CreateProjectResponseStruct
     * @throws Exception
     * @internal param MaserFileCreationResponseStruct[] $remoteFiles
     */
    public function createTranslationChild(CreateProjectResponseStruct $parent, $remoteFiles, UserModel $assignee = null ) {

        $projectStruct            = new ChildProjectRequestStruct() ;
        $projectStruct->sessionId = $this->session->getSessionId();
        $projectStruct->clientId  = Functions::scopeId( $this->clientId );
        $projectStruct->parentKey = $parent->dqfUUID ;
        $projectStruct->type      = self::TRANSLATION ;

        if ( $assignee ) {
            $projectStruct->assignee = $assignee->getDqfUsername() ;
        }

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

        $this->_setFilesTargetLanguage( $remoteFiles, $childProject, $this->getSessionForFiles( $assignee ) ) ;

        return $childProject ;
    }

    /**
     *  What session to use for files.
     */
    protected function getSessionForFiles( UserModel $assignee = null ) {
        if ( $assignee ) {
            return $assignee->getSession()->login();
        }
        else {
            return $this->session ;
        }
    }

    /**
     * @param $remoteFiles
     * @param $childProject
     *
     * @throws Exception
     */
    protected function _setFilesTargetLanguage( $remoteFiles, $childProject, Session $session ) {

        $client = new Client() ;
        $client->setSession( $session );

        foreach ( $this->chunk->getFiles() as $file ) {
            // for each file in the chunk create a
            $languageStruct                     = new ProjectTargetLanguageRequestStruct();
            $languageStruct->fileId             = $remoteFiles[ Functions::scopeId( $file->id ) ]->dqfId;
            $languageStruct->projectKey         = $childProject->dqfUUID;
            $languageStruct->projectId          = $childProject->dqfId;
            $languageStruct->targetLanguageCode = $this->chunk->target;

            $client->createResource( '/project/child/%s/file/%s/targetLang', 'post', [
                    'formData'   => $languageStruct->getParams(),
                    'headers'    => $languageStruct->getHeaders(),
                    'pathParams' => $languageStruct->getPathParams()
            ] );
        }

        $client->curl()->multiExec();

        if ( count( $client->curl()->getErrors() ) > 0 ) {
            throw new Exception( 'Error in creation of target langauge for files: ' . implode( $client->curl()->getAllContents() ) );
        }
    }
}

