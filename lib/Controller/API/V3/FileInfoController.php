<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 08/02/2019
 * Time: 13:03
 */

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\ChunkPasswordValidator;
use API\V3\Json\FilesInfo;
use Chunks_ChunkStruct;
use Jobs_JobDao;
use Projects_ProjectStruct;


class FileInfoController extends KleinController {

    /**
     * @var Projects_ProjectStruct
     */
    protected $project;

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;

    protected function afterConstruct() {
        $Validator = new ChunkPasswordValidator( $this );
        $Validator->onSuccess( function () use ( $Validator ) {
            $this->setChunk( $Validator->getChunk() );
            //those are not needed at moment, so avoid unnecessary queries
//            $this->setProject( $Validator->getChunk()->getProject() );
//            $this->setFeatureSet( $this->project->getFeaturesSet() );
        } );
        $this->appendValidator( $Validator );
    }

    private function setChunk( Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk;
    }

    private function setProject( Projects_ProjectStruct $project ) {
        $this->project = $project;
    }

    public function getInfo() {

        /**
         * get info for every file
         */
        $fileInfo = Jobs_JobDao::getFirstSegmentOfFilesInJob( $this->chunk );
        $this->response->json( ( new FilesInfo() )->render( $fileInfo ) );

    }

}