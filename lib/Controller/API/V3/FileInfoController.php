<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 08/02/2019
 * Time: 13:03
 */

namespace API\V3;

use API\V2\Exceptions\NotFoundException;
use API\V2\KleinController;
use API\V2\Validators\ChunkPasswordValidator;
use API\V3\Json\FilesInfo;
use Chunks_ChunkStruct;
use Jobs_JobDao;
use Projects_ProjectStruct;
use Files\MetadataDao as Files_MetadataDao;
use Files_FileDao;


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
            $this->setProject( $Validator->getChunk()->getProject() );
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
        $this->response->json( ( new FilesInfo() )->render( $fileInfo, $this->chunk->job_first_segment, $this->chunk->job_last_segment ) );

    }

    public function getInstructions() {
        $id_file = $this->request->param( 'id_file' );
        if(Files_FileDao::isFileInProject($id_file, $this->project->id)){
            $metadataDao = new Files_MetadataDao;
            $fileInfo = $metadataDao->get( $this->project->id, $id_file, 'instructions' );
            $this->response->json($fileInfo);
        } else {
            throw new NotFoundException('File not found on this project');
        }
    }

}