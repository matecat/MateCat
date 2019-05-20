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
use Features\Dqf\Service\Struct\Request\ProjectTargetLanguageRequestStruct;
use Features\Dqf\Service\Struct\Response\MaserFileCreationResponseStruct;
use Features\Dqf\Service\Struct\Response\ProjectResponseStruct;
use Features\Dqf\Utils\Functions;

class ChildProjectService {
    const TRANSLATION = 'translation' ;
    const REVIEW = 'review' ;

    /**
     * @var ISession
     */
    protected $session ;

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk ;

    protected $remoteProjects;

    protected $clientId ;

    public function __construct(ISession $session, Chunks_ChunkStruct $chunk, $id_project ) {
        $this->chunk    = $chunk  ;
        $this->session  = $session ;
        $this->clientId = $id_project ;
    }

    public function deleteProject(ChildProjectRequestStruct $struct ) {
        $client = new Client() ;
        $client->setSession( $this->session );

        $resource =  $client->createResource('/project/child/%s', 'delete', [
                'headers'    =>  $this->session->filterHeaders( $struct ),
                'pathParams' =>  $struct->getPathParams(),
        ] );

        $client->execRequests();

        if ( count($client->curl()->getErrors() ) > 0 ) {
            throw  new Exception('Error on delete of remote child project: ' .
                    implode( ', ',  $client->curl()->getAllContents() )
            ) ;
        }

        $returnable = $client->curl()->getAllContents();
        return $returnable ;
    }

    public function updateChildProjects( $requestStructs ) {
        $client = new Client() ;
        $client->setSession( $this->session );

        $resources = [] ;

        /** @var ChildProjectRequestStruct $requestStruct */
        foreach( $requestStructs as $requestStruct ) {
            $resources[] = $client->createResource('/project/child/%s', 'put', [
                    'headers'    => $this->session->filterHeaders( $requestStruct ),
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

    public function setCompleted( ChildProjectRequestStruct $requestStruct ) {
        $client = new Client();
        $client->setSession( $this->session );

        $resource =  $client->createResource('/project/child/%s/status', 'put', [
                'headers'    =>  $this->session->filterHeaders( $requestStruct ),
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
                    'headers'    => $this->session->filterHeaders( $requestStruct ),
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
                'headers'     =>  $this->session->filterHeaders( $request ),
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
     * @return CreateProjectResponseStruct
     * @throws Exception
     * @internal param MaserFileCreationResponseStruct[] $remoteFiles
     */
    public function createTranslationChild( DqfProjectMapStruct $parent, $remoteFiles ) {
        $projectStruct            = new ChildProjectRequestStruct() ;
        $projectStruct->type      = self::TRANSLATION ;

        return $this->createChild( $parent, $remoteFiles, $projectStruct );
    }

    public function createRevisionChild( DqfProjectMapStruct $parent, $remoteFiles ) {
        $projectStruct                  = new ChildProjectRequestStruct() ;
        $projectStruct->type            = self::REVIEW ;
        $projectStruct->reviewSettingId = $this->chunk->getProject()->getMetadataValue('dqf_review_settings_id');

        return $this->createChild( $parent, $remoteFiles, $projectStruct );
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
     * @param          $remoteFiles
     * @param          $childProject
     *
     * @param ISession $session
     *
     * @throws Exception
     */
    protected function _setFilesTargetLanguage( $remoteFiles, $childProject, ISession $session ) {

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
                    'headers'    => $session->filterHeaders( $languageStruct ),
                    'pathParams' => $languageStruct->getPathParams()
            ] );
        }

        $client->curl()->multiExec();

        if ( count( $client->curl()->getErrors() ) > 0 ) {
            throw new Exception( 'Error in creation of target langauge for files: ' . implode( $client->curl()->getAllContents() ) );
        }
    }

    /**
     * @param DqfProjectMapStruct $parent
     * @param                     $remoteFiles
     * @param                     $projectStruct
     *
     * @return CreateProjectResponseStruct
     * @throws Exception
     */
    protected function createChild( DqfProjectMapStruct $parent, $remoteFiles, $projectStruct ) {

        $projectStruct->sessionId = $this->session->getSessionId();
        $projectStruct->clientId  = Functions::scopeId( $this->clientId );
        $projectStruct->parentKey = $parent->dqf_project_uuid ;

        $client = new Client();
        $client->setSession( $this->session );
        $resource = $client->createResource( '/project/child', 'post', [
                'formData' => $projectStruct->getParams(),
                'headers'  => $this->session->filterHeaders( $projectStruct ),
        ] );

        $client->execRequests();

        if ( count( $client->curl()->getErrors() ) > 0 ) {
            throw new Exception( 'Error in creation of child project: ' . implode( $client->curl()->getAllContents() ) );
        }

        $childProject = new CreateProjectResponseStruct( json_decode( $client->curl()->getSingleContent( $resource ), true ) );

        $this->_setFilesTargetLanguage( $remoteFiles, $childProject, $this->getSessionForFiles() );

        return $childProject;
    }
}

