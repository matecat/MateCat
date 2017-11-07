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
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
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
     * @var DqfProjectMapDao
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

    protected $id_project ;

    protected $first_segment ;
    protected $last_segment ;

    public function __construct( DqfProjectMapStruct $parentProject, Chunks_ChunkStruct $chunk, $project_type ) {
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
     *
     * We need to pass first and last segments explicitly here becasuse we don't know if we
     * are creating a dqf child project for a job that was merged before or not. So we cannot
     * rely on the information coming from the parent project nor on the one coming from the parent.
     *
     * @param $first_segment
     * @param $last_segment
     *
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
     * @internal param $first_segment
     * @internal param $last_segment
     */
    protected function _saveDqfChildProjectMap( Chunks_ChunkStruct $chunk, CreateProjectResponseStruct $remoteProject ) {
        $struct = new DqfProjectMapStruct() ;


        $struct->id               = $this->id_project ;
        $struct->id_job           = $chunk->id ;
        $struct->password         = $chunk->password ;

        list( $first, $last ) = $this->getFirstAndLast();

        $struct->first_segment    = $first ;
        $struct->last_segment     = $last ;

        $struct->dqf_project_id   = $remoteProject->dqfId ;
        $struct->dqf_project_uuid = $remoteProject->dqfUUID ;
        $struct->dqf_parent_uuid  = $this->parentProject->dqf_project_uuid ;
        $struct->create_date      = Utils::mysqlTimestamp(time()) ;
        $struct->project_type     = $this->project_type ;
        $struct->uid              = $this->user->getMateCatUser()->uid ;

        $struct->id = DqfProjectMapDao::insertStructWithAutoIncrements( $struct ) ;

        $this->savedDqfChildProjectMap = $struct ;

    }

    /**
     * This method returns the first and last segment to use for the DQF remote project.
     * This is based on the assumption that DQF projects can only be splitted once.
     *
     * If the job is merged, we may have more parent DQF records due to a previous split,
     * so we must use first and last segment of the parent project.
     *
     * Otherwise if the job is splitted, we assume the preivous DQF project was just one for the job.
     *
     * @return array
     */
    protected  function getFirstAndLast() {
       if ( $this->chunk->hasSiblings() )  {
           return [ $this->chunk->job_first_segment, $this->chunk->job_last_segment ] ;
       }
       else {
           return [ $this->parentProject->first_segment, $this->parentProject->last_segment ] ;
       }
    }

}