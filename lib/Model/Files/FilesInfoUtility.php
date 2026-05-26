<?php

namespace Model\Files;

use Exception;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use PDOException;
use ReflectionException;
use RuntimeException;
use View\API\V3\Json\FilesInfo;

class FilesInfoUtility
{

    private JobStruct $chunk;
    private int $projectId;
    private JobDao $jobDao;
    private MetadataDao $metadataDao;
    private FilesPartsDao $filesPartsDao;
    private FileDao $fileDao;

    /**
     * FilesInfoUtility constructor.
     *
     * @param JobStruct       $chunkStruct
     * @param JobDao|null     $jobDao
     * @param MetadataDao|null $metadataDao
     * @param FilesPartsDao|null $filesPartsDao
     * @param FileDao|null    $fileDao
     * @throws RuntimeException
     */
    public function __construct(
        JobStruct $chunkStruct,
        ?JobDao $jobDao = null,
        ?MetadataDao $metadataDao = null,
        ?FilesPartsDao $filesPartsDao = null,
        ?FileDao $fileDao = null
    ) {
        $this->chunk = $chunkStruct;
        $projectId = $chunkStruct->getProject()->id;
        if ($projectId === null) {
            throw new RuntimeException('Project ID must not be null');
        }
        $this->projectId = $projectId;
        $this->jobDao = $jobDao ?? new JobDao();
        $this->metadataDao = $metadataDao ?? new MetadataDao();
        $this->filesPartsDao = $filesPartsDao ?? new FilesPartsDao();
        $this->fileDao = $fileDao ?? new FileDao();
    }

    /**
     * get info for every file
     *
     * @param bool $showMetadata
     *
     * @return array<string, mixed>
     * @throws ReflectionException
     * @throws Exception
     */
    public function getInfo(bool $showMetadata = true): array
    {
        $fileInfo = $this->jobDao->getFilesInfoInJob($this->chunk, 60 * 5);
        $fileMetadataDao = $this->metadataDao;
        $filePartsDao = $this->filesPartsDao;

        // Show metadata
        if ($showMetadata) {
            // File parts
            foreach ($fileInfo as $file) {
                $filePartsIdArray = [];
                $metadata = [];

                foreach ($fileMetadataDao->getByJobIdProjectAndIdFile($this->projectId, $file->id_file, 60 * 5) ?? [] as $metadatum) {
                    if ($metadatum->files_parts_id !== null) {
                        $metadata['files_parts'][(int)$metadatum->files_parts_id][$metadatum->key] = $metadatum->value;

                        if (!in_array($metadatum->files_parts_id, $filePartsIdArray)) {
                            $filePartsIdArray[] = (int)$metadatum->files_parts_id;
                        }
                    } else {
                        $metadata[$metadatum->key] = $metadatum->value;
                    }
                }

                $index = 0;
                if (isset($metadata['files_parts'])) {
                    foreach ($metadata['files_parts'] as $id => $filesPart) {
                        $filesPart['id'] = $id;
                        $metadata['files_parts'][$index] = $filesPart;
                        unset($metadata['files_parts'][$id]);
                        $index++;
                    }

                    $metadata['files_parts'] = array_values($metadata['files_parts']);
                }

                if (!isset($metadata['files_parts'])) {
                    $metadata['files_parts'] = [];

                    $fileParts = $filePartsDao->getByFileId($file->id_file);

                    foreach ($fileParts as $filePart) {
                        $metadata['files_parts'][] = [
                            'id' => (int)$filePart->id
                        ];
                    }
                }

                if (!isset($metadata['instructions'])) {
                    $metadata['instructions'] = null;
                }

                $file->metadata = $metadata;
            }
        }

        return (new FilesInfo())->render($fileInfo, $this->chunk->job_first_segment, $this->chunk->job_last_segment);
    }

    /**
     * @param int $id_file
     * @param int|null $filePartsId
     *
     * @return array{instructions: mixed}|null
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
     */
    public function getInstructions(int $id_file, ?int $filePartsId = null): ?array
    {
        if ($this->fileDao->isFileInProject($id_file, $this->projectId)) {
            $metadataDao = $this->metadataDao;
            $instructions = $metadataDao->get($this->projectId, $id_file, 'instructions', $filePartsId, 60 * 5);

            if (!$instructions) {
                $instructions = $metadataDao->get($this->projectId, $id_file, 'mtc:instructions', $filePartsId, 60 * 5);
            }

            if (!$instructions) {
                return null;
            }

            return ['instructions' => $instructions->value];
        }

        return null;
    }

    /**
     * @param int $id_file
     * @param string $instructions
     *
     * @return bool
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
     */
    public function setInstructions(int $id_file, string $instructions): bool
    {
        if ($this->fileDao->isFileInProject($id_file, $this->projectId)) {
            if ($this->metadataDao->get($this->projectId, $id_file, 'instructions')) {
                $this->metadataDao->update($this->projectId, $id_file, 'instructions', $instructions);
            } else {
                $this->metadataDao->insert($this->projectId, $id_file, 'instructions', $instructions);
            }

            $this->metadataDao->destroyCacheByJobIdProjectAndIdFile($this->projectId, $id_file);

            return true;
        }

        return false;
    }
}
