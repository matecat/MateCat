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
     * @param bool $showMetadata
     * @param int $page
     * @param int $itemsPerPage
     *
     * @return array
     */
    public function getInfo($showMetadata = true) {

        $fileInfo    = \Jobs_JobDao::getFirstSegmentOfFilesInJob( $this->chunk, 60 * 5 );
        $fileMetadataDao = new Files_MetadataDao();
        $filePartsDao = new FilesPartsDao();

        // Show metadata
        if($showMetadata){

            $metadata = [];

            // File parts
            foreach ( $fileInfo as &$file ) {

                $filePartsIdArray = [];

                foreach ( $fileMetadataDao->getByJobIdProjectAndIdFile( $this->project->id, $file->id_file, 60 * 5 ) as $metadatum ) {

                    if($metadatum->files_parts_id !== null){
                        $metadata[ 'files_parts' ][ (int)$metadatum->files_parts_id ][ $metadatum->key ] = $metadatum->value;

                        if(!in_array($metadatum->files_parts_id, $filePartsIdArray)){
                            $filePartsIdArray[] = (int)$metadatum->files_parts_id;
                        }

                    } else {
                        $metadata[ $metadatum->key ] = $metadatum->value;
                    }
                }

                $index = 0;
                if(isset($metadata[ 'files_parts' ])){
                    foreach ($metadata[ 'files_parts' ] as $id => $filesPart){
                        $filesPart['id'] = $id;
                        $metadata[ 'files_parts' ][$index] = $filesPart;
                        unset( $metadata[ 'files_parts' ][$id]);
                        $index++;
                    }

                    $metadata[ 'files_parts' ] = array_values($metadata[ 'files_parts' ]);
                }

                if(!isset($metadata['files_parts'])){

                    $metadata['files_parts'] = [];

                    $fileParts = $filePartsDao->getByFileId($file->id_file);

                    foreach ($fileParts as $filePart){
                        $metadata['files_parts'][] = [
                            'id' => (int)$filePart->id
                        ];
                    }
                }

                if(!isset($metadata['instructions'])){
                    $metadata['instructions'] = null;
                }

                $file->metadata = $metadata;
            }
        }

        return ( new FilesInfo() )->render( $fileInfo, $this->chunk->job_first_segment, $this->chunk->job_last_segment );
    }

    /**
     * @param      $id_file
     * @param null $filePartsId
     *
     * @return array|null
     */
    public function getInstructions($id_file, $filePartsId = null) {

        if(\Files_FileDao::isFileInProject($id_file, $this->project->id)){
            $metadataDao = new Files_MetadataDao;
            $instructions = $metadataDao->get( $this->project->id, $id_file, 'instructions', $filePartsId, 60 * 5 );

            if(!$instructions){
                $instructions = $metadataDao->get( $this->project->id, $id_file, 'mtc:instructions', $filePartsId, 60 * 5 );
            }

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