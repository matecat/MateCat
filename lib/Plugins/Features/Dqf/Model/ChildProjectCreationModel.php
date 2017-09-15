<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 30/08/2017
 * Time: 15:01
 */

namespace Features\Dqf\Model;


use Chunks_ChunkStruct;
use Database;
use Exception;
use Features\Dqf\Service\ChildProjectService;
use Features\Dqf\Service\ISession;
use Features\Dqf\Service\Session;
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Features\Dqf\Utils\Functions;
use Utils;

class ChildProjectCreationModel {

    /**
     * @var DqfProjectMapStruct
     */
    protected $savedDqfChildProjectMap ;

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
     * @var UserModel
     */
    protected $user ;

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk ;

    protected $project_type ;

    public function __construct( CreateProjectResponseStruct $parentProject, Chunks_ChunkStruct $chunk, $project_type ) {
        $this->chunk         = $chunk ;
        $this->parentProject = $parentProject ;

        $this->id_project = Database::obtain()->nextSequence('id_dqf_project') [ 0 ] ;

        if ( !in_array( $project_type, [ 'vendor_root', 'translate', 'revise' ] )) {
            throw  new Exception('type not supported: ' . $project_type ) ;
        }

        $this->project_type = $project_type ;
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

    public function setUser( UserModel $user ) {
        $this->user = $user  ;
    }

    /**
     * @return CreateProjectResponseStruct
     * @throws Exception
     */
    public function create() {
        if ( empty( $this->files ) ) {
            throw new Exception("Files are not set");
        }

        if ( is_null( $this->user ) ) {
            throw new Exception('User is not set') ;
        }

        if ( in_array( $this->project_type, [ 'translate', 'vendor_root' ] ) ) {
            $remoteProject = $this->createForTranslation();
        }
        else  {
            $remoteProject = $this->createForRevision() ;
        }

        $this->_saveDqfChildProjectMap( $this->chunk, $remoteProject ) ;

        return $remoteProject ;
    }

    protected function createForRevision() {
        $projectService = new ChildProjectService( $this->user->getSession()->login(), $this->chunk, $this->id_project ) ;

        return $projectService->createRevisionChild(
                $this->parentProject, $this->files
        );
    }

    /**
     * @return CreateProjectResponseStruct
     * @throws Exception
     */
    protected function createForTranslation() {
        $projectService = new ChildProjectService( $this->user->getSession()->login(), $this->chunk, $this->id_project ) ;

        return $projectService->createTranslationChild(
                $this->parentProject, $this->files
        );
    }

    public function getSavedRecord() {
        return $this->savedDqfChildProjectMap ;
    }

    /**
     * @param Chunks_ChunkStruct          $chunk
     * @param CreateProjectResponseStruct $remoteProject
     *
     */
    protected function _saveDqfChildProjectMap( Chunks_ChunkStruct $chunk, CreateProjectResponseStruct $remoteProject ) {
        $struct = new DqfProjectMapStruct() ;

        $struct->id               = $this->id_project ;
        $struct->id_job           = $chunk->id ;
        $struct->first_segment    = $chunk->job_first_segment ;
        $struct->last_segment     = $chunk->job_last_segment ;
        $struct->password         = $chunk->password ;
        $struct->dqf_project_id   = $remoteProject->dqfId ;
        $struct->dqf_project_uuid = $remoteProject->dqfUUID ;
        $struct->dqf_parent_uuid  = $this->parentProject->dqfUUID ;
        $struct->create_date      = Utils::mysqlTimestamp(time()) ;
        $struct->project_type     = $this->project_type ;
        $struct->uid              = $this->user->getMateCatUser()->uid ;

        $struct->id = DqfProjectMapDao::insertStructWithAutoIncrements( $struct ) ;

        $this->savedDqfChildProjectMap = $struct ;

    }

}