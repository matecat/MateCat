<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 30/08/2017
 * Time: 15:01
 */

namespace Features\Dqf\Model;


use Chunks_ChunkStruct;
use Exception;
use Features\Dqf\Service\ChildProjectService;
use Features\Dqf\Service\Session;
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Utils;

class ChildProjectCreationModel {

    protected $childProjectRecordId ;

    /**
     * @var CreateProjectResponseStruct
     */
    protected $remoteProject ;

    /**
     * @var UserModel
     */
    protected $assignee ;

    /**
     * @var CreateProjectResponseStruct
     */
    protected $parentProject ;

    protected $files ;

    /**
     * @var Session
     */
    protected $ownerSession ;

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk ;

    protected $splittedIndex = null;

    public function __construct( CreateProjectResponseStruct $parentProject, Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk ;
        $this->parentProject = $parentProject ;
    }

    public function setSplittedIndex( $k ) {
        $this->splittedIndex = $k ;
    }

    public function setFiles( $files ) {
        foreach ($files as $file ) {
            $class = 'Features\Dqf\Service\Struct\Response\MaserFileCreationResponseStruct' ;

            if ( get_class( $file ) != $class ) {
                throw new Exception('unexpected file type ' . get_class( $file ) );
            }
        }

        $this->files = $files ;
    }

    public function setAssignee(UserModel $user) {
        $this->assignee = $user ;
    }

    public function setOwnerSession( Session $session ) {
        $this->ownerSession = $session  ;
    }

    /**
     * @return CreateProjectResponseStruct
     * @throws Exception
     */
    public function createForTranslation() {
        if ( empty( $this->files ) ) {
            throw new Exception("Files are not set");
        }

        if ( is_null( $this->ownerSession ) ) {
            throw new Exception('Session is not set') ;
        }

        $projectService  = new ChildProjectService( $this->ownerSession, $this->chunk, $this->splittedIndex ) ;

        $remoteProject = $projectService->createTranslationChild(
                $this->parentProject, $this->files, $this->assignee
        );

        $this->_saveDqfChildProjectMap( $this->chunk, $remoteProject ) ;

        return $remoteProject ;
    }

    protected function getAssigneeEmail() {
        return isset( $this->assignee ) ? $this->assignee->getDqfUsername() : null ;
    }

    public function getChildProjectRecordId() {
        return $this->childProjectRecordId ;
    }

    /**
     * @param Chunks_ChunkStruct          $chunk
     * @param CreateProjectResponseStruct $remoteProject
     *
     */
    protected function _saveDqfChildProjectMap( Chunks_ChunkStruct $chunk, CreateProjectResponseStruct $remoteProject ) {
        $struct = new DqfProjectMapStruct() ;

        $struct->id_job           = $chunk->id ;
        $struct->first_segment    = $chunk->job_first_segment ;
        $struct->last_segment     = $chunk->job_last_segment ;
        $struct->password         = $chunk->password ;
        $struct->dqf_project_id   = $remoteProject->dqfId ;
        $struct->dqf_project_uuid = $remoteProject->dqfUUID ;
        $struct->dqf_parent_uuid  = $this->parentProject->dqfUUID ;
        $struct->create_date      = Utils::mysqlTimestamp(time()) ;

        $this->childProjectRecordId = DqfProjectMapDao::insertStruct( $struct ) ;
    }

}