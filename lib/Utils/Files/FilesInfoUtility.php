<?php

namespace Files;

use API\V2\Exceptions\NotFoundException;
use API\V3\Json\FilesInfo;
use Files\MetadataDao as Files_MetadataDao;
use Jobs_JobStruct;

class FilesInfoUtility {

    /**
     * @var Jobs_JobStruct
     */
    private $chunk;

    /**
     * @var \Projects_ProjectStruct
     */
    private $project;

    /**
     * FilesInfoUtility constructor.
     *
     * @param Jobs_JobStruct $chunkStruct
     */
    public function __construct( \Jobs_JobStruct $chunkStruct ) {
        $this->chunk   = $chunkStruct;
        $this->project = $chunkStruct->getProject();
    }

    /**
     * get info for every file
     *
     * @return array
     */
    public function getInfo() {

        $fileInfo    = \Jobs_JobDao::getFirstSegmentOfFilesInJob( $this->chunk, 60 * 5 );
        $metadataDao = new Files_MetadataDao;
        foreach ( $fileInfo as &$file ) {
            $metadata = [];
            foreach ( $metadataDao->getByJobIdProjectAndIdFile( $this->project->id, $file->id_file, 60 * 5 ) as $metadatum ) {
                $metadata[ $metadatum->key ] = $metadatum->value;
            }

            $file->metadata = $metadata;
        }

        return ( new FilesInfo() )->render( $fileInfo, $this->chunk->job_first_segment, $this->chunk->job_last_segment );
    }

    /**
     * @param $id_file
     *
     * @return array|null
     */
    public function getInstructions($id_file) {
        if(\Files_FileDao::isFileInProject($id_file, $this->project->id)){
            $metadataDao = new Files_MetadataDao;
            $instructions = $metadataDao->get( $this->project->id, $id_file, 'instructions', 60 * 5 );

            if(!$instructions){
                return null;
            }

            return ['instructions' => $instructions->value];
        }

        return null;
    }

    /**
     * @param $id_file
     * @param $instructions
     *
     * @return bool
     */
    public function setInstructions($id_file, $instructions) {

        if(\Files_FileDao::isFileInProject($id_file, $this->project->id)){
            $metadataDao = new Files_MetadataDao;
            if($metadataDao->get( $this->project->id, $id_file, 'instructions', 60 * 5 )){
                $metadataDao->update( $this->project->id, $id_file, 'instructions', $instructions );
            } else {
                $metadataDao->insert( $this->project->id, $id_file, 'instructions', $instructions );
            }

            return true;
        }

        return false;
    }
}