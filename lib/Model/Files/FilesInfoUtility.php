<?php

namespace Model\Files;

use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use ReflectionException;
use View\API\V3\Json\FilesInfo;

class FilesInfoUtility
{

    /**
     * @var JobStruct
     */
    private JobStruct $chunk;

    /**
     * @var ProjectStruct
     */
    private ProjectStruct $project;

    /**
     * FilesInfoUtility constructor.
     *
     * @param JobStruct $chunkStruct
     */
    public function __construct(JobStruct $chunkStruct)
    {
        $this->chunk   = $chunkStruct;
        $this->project = $chunkStruct->getProject();
    }

    /**
     * get info for every file
     *
     * @param bool $showMetadata
     *
     * @return array
     * @throws ReflectionException
     */
    public function getInfo(bool $showMetadata = true): array
    {
        $fileInfo        = JobDao::getFirstSegmentOfFilesInJob($this->chunk, 60 * 5);
        $fileMetadataDao = new MetadataDao();
        $filePartsDao    = new FilesPartsDao();

        // Show metadata
        if ($showMetadata) {
            // File parts
            foreach ($fileInfo as $file) {
                $filePartsIdArray = [];
                $metadata         = [];

                foreach ($fileMetadataDao->getByJobIdProjectAndIdFile($this->project->id, $file->id_file, 60 * 5) as $metadatum) {
                    if ($metadatum->files_parts_id !== null) {
                        $metadata[ 'files_parts' ][ (int)$metadatum->files_parts_id ][ $metadatum->key ] = $metadatum->value;

                        if (!in_array($metadatum->files_parts_id, $filePartsIdArray)) {
                            $filePartsIdArray[] = (int)$metadatum->files_parts_id;
                        }
                    } else {
                        $metadata[ $metadatum->key ] = $metadatum->value;
                    }
                }

                $index = 0;
                if (isset($metadata[ 'files_parts' ])) {
                    foreach ($metadata[ 'files_parts' ] as $id => $filesPart) {
                        $filesPart[ 'id' ]                   = $id;
                        $metadata[ 'files_parts' ][ $index ] = $filesPart;
                        unset($metadata[ 'files_parts' ][ $id ]);
                        $index++;
                    }

                    $metadata[ 'files_parts' ] = array_values($metadata[ 'files_parts' ]);
                }

                if (!isset($metadata[ 'files_parts' ])) {
                    $metadata[ 'files_parts' ] = [];

                    $fileParts = $filePartsDao->getByFileId($file->id_file);

                    foreach ($fileParts as $filePart) {
                        $metadata[ 'files_parts' ][] = [
                                'id' => (int)$filePart->id
                        ];
                    }
                }

                if (!isset($metadata[ 'instructions' ])) {
                    $metadata[ 'instructions' ] = null;
                }

                $file->metadata = $metadata;
            }
        }

        return (new FilesInfo())->render($fileInfo, $this->chunk->job_first_segment, $this->chunk->job_last_segment);
    }

    /**
     * @param int      $id_file
     * @param int|null $filePartsId
     *
     * @return array|null
     * @throws ReflectionException
     */
    public function getInstructions(int $id_file, ?int $filePartsId = null): ?array
    {
        if (FileDao::isFileInProject($id_file, $this->project->id)) {
            $metadataDao  = new MetadataDao;
            $instructions = $metadataDao->get($this->project->id, $id_file, 'instructions', $filePartsId, 60 * 5);

            if (!$instructions) {
                $instructions = $metadataDao->get($this->project->id, $id_file, 'mtc:instructions', $filePartsId, 60 * 5);
            }

            if (!$instructions) {
                return null;
            }

            return ['instructions' => $instructions->value];
        }

        return null;
    }

    /**
     * @param int    $id_file
     * @param string $instructions
     *
     * @return bool
     * @throws ReflectionException
     */
    public function setInstructions(int $id_file, string $instructions): bool
    {
        if (FileDao::isFileInProject($id_file, $this->project->id)) {
            $metadataDao = new MetadataDao;
            if ($metadataDao->get($this->project->id, $id_file, 'instructions')) {
                $metadataDao->update($this->project->id, $id_file, 'instructions', $instructions);
            } else {
                $metadataDao->insert($this->project->id, $id_file, 'instructions', $instructions);
            }

            $metadataDao->destroyCacheByJobIdProjectAndIdFile((int)$this->project->id, $id_file);

            return true;
        }

        return false;
    }
}