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
            $this->setProject( $Validator->getChunk()->getProject() );
            //those are not needed at moment, so avoid unnecessary queries
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
        $metadataDao = new Files_MetadataDao;
        foreach($fileInfo as &$file){
            $metadata = [];
            foreach ( $metadataDao->getByJobIdProjectAndIdFile( $this->project->id, $file->id_file ) as $metadatum ) {
                $metadata[ $metadatum->key ] = $metadatum->value;
            }

            $file->metadata = $metadata;
        }

        $this->response->json( ( new FilesInfo() )->render( $fileInfo, $this->chunk->job_first_segment, $this->chunk->job_last_segment ) );

    }

    public function getInstructions() {
        $id_file = $this->request->param( 'id_file' );
        if(Files_FileDao::isFileInProject($id_file, $this->project->id)){
            $metadataDao = new Files_MetadataDao;
            $instructions = $metadataDao->get( $this->project->id, $id_file, 'instructions' );
            if($instructions){
                $this->response->json(['instructions' => $instructions->value]);
            } else {
                throw new NotFoundException('No instructions for this file');
            }
        } else {
            throw new NotFoundException('File not found on this project');
        }
    }

    public function setInstructions() {

        $id_file = $this->request->param( 'id_file' );
        $instructions = $this->request->param( 'instructions' );
        if(Files_FileDao::isFileInProject($id_file, $this->project->id)){
            $metadataDao = new Files_MetadataDao;
            if($metadataDao->get( $this->project->id, $id_file, 'instructions' )){
                $metadataDao->update( $this->project->id, $id_file, 'instructions', $instructions );
            } else {
                $metadataDao->insert( $this->project->id, $id_file, 'instructions', $instructions );
            }
            $this->response->json(true);
        } else {
            throw new NotFoundException('File not found on this project');
        }
    }

}