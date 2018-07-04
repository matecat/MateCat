<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 29/09/2017
 * Time: 16:31
 */

namespace Features\Dqf\Model;


use Exception;
use Features\Dqf\Service\ChildProjectService;
use Features\Dqf\Service\FileIdMapping;
use Features\Dqf\Service\ISession;
use Features\Dqf\Service\Struct\Request\ChildProjectRequestStruct;
use Files_FileStruct;
use Jobs\MetadataDao;
use Users_UserDao;

abstract class AbstractChildProject {

    protected $type;

    /**
     * @var \Chunks_ChunkStruct
     */
    protected $chunk ;

    /** * @var ProjectMapResolverModel */
    protected $dqfProjectMapResolver  ;

    /** * @var DqfProjectMapStruct[] */
    protected $dqfChildProjects = [];

    /** * @var UserModel */
    protected $dqfUser ;

    /** * @var ISession */
    protected $userSession ;

    /** * @var Files_FileStruct[] */
    protected $files ;

    public function __construct( $chunk, $type ) {
        $this->chunk = $chunk;
        $this->type = $type ;

        $this->dqfProjectMapResolver = new ProjectMapResolverModel( $this->chunk, $type );
        $this->dqfChildProjects = $this->dqfProjectMapResolver->getMappedProjects();

        $this->_initUserAndSession() ;
    }

    public function setCompleted() {
        /** @var DqfProjectMapStruct $project */
        foreach( $this->dqfChildProjects as $project ) {
            $service = new ChildProjectService(
                    $this->userSession, $this->chunk, $project->id ) ;

            $struct = new ChildProjectRequestStruct([
                    'projectId' => $project->dqf_project_id,
                    'projectKey' => $project->dqf_project_uuid
            ]);

            $service->setCompleted( $struct );
        }
    }

    protected function _initUserAndSession() {
        $uid = ( new MetadataDao() )
                ->get( $this->chunk->id, $this->chunk->password, $this->_getMetadataUserKey() )
                ->value ;

        if ( !$uid ) {
            throw new Exception( $this->_getMetadataUserKey() . ' must be set' );
        }

        $this->dqfUser     = new UserModel( ( new Users_UserDao() )->getByUid( $uid ) );
        $this->userSession = $this->dqfUser->getSession()->login();
    }

    /**
     * @return string
     */
    protected function _getMetadataUserKey() {
        return $this->type == 'translate' ?
                CatAuthorizationModel::DQF_TRANSLATE_USER :
                CatAuthorizationModel::DQF_REVISE_USER ;
    }

    protected function createRemoteProjects() {
        $parents = $this->dqfProjectMapResolver->getParents();

        $this->dqfChildProjects = [] ;

        foreach( $parents as $parent ) {
            $project = new ChildProjectCreationModel($parent, $this->chunk, $this->type  );

            $model = new ProjectModel( $parent );

            $project->setUser( $this->dqfUser );
            $project->setFiles( $model->getFilesResponseStruct() ) ;

            $project->create();

            // TODO: not sure this assignment is really helpful
            // reloading the projectMapper should be enough
            $this->dqfChildProjects[]  = $project->getSavedRecord() ;

            $this->dqfProjectMapResolver->reload();
        }
    }

    protected function projectCreationRequired() {
        return empty( $this->dqfChildProjects ) ;
    }

    public function createRemoteProjectsAndSubmit() {
        if ( $this->projectCreationRequired() ) {
            $this->createRemoteProjects();
        }
        $this->_submitData() ;
        $this->dqfProjectMapResolver->archiveInverseType();

    }


    abstract protected function _submitData() ;

    protected function _findRemoteFileId( Files_FileStruct $file ) {
        $projectOwner = new UserModel ( $this->chunk->getProject()->getOriginalOwner()  ) ;
        $service = new FileIdMapping( $projectOwner->getSession()->login(), $file ) ;

        return $service->getRemoteId() ;
    }

}