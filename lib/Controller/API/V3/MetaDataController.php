<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Model\Engines\Structs\EngineStruct;
use Model\Files\MetadataDao as FileMetadataDao;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use Model\Projects\ProjectStruct;
use ReflectionException;
use stdClass;
use Utils\Constants\EngineConstants;
use Utils\Engines\MyMemory;

class MetaDataController extends KleinController
{
    use ChunkNotFoundHandlerTrait;

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }


    /**
     * @throws ReflectionException
     * @throws NotFoundException
     */
    public function index(): void
    {
        // params
        $id_job = $this->request->param('id_job');
        $password = $this->request->param('password');

        // find a job
        $job = $this->getJob($id_job, $password);

        if (null === $job) {
            throw new NotFoundException('Job not found.');
        }

        $this->chunk = $job;
        $this->return404IfTheJobWasDeleted();

        $metadata = new stdClass();
        $metadata->project = $this->getProjectInfo($job->getProject());
        $metadata->job = $this->getJobMetaData($job);
        $metadata->files = $this->getJobFilesMetaData($job);

        $this->response->json($metadata);
    }

    /**
     * @param ProjectStruct $project
     *
     * @return stdClass
     */
    private function getProjectInfo(ProjectStruct $project): stdClass
    {
        $metadata = new stdClass();
        $metadata->mt_extra = new stdClass();

        $myExtraKeys = [];

        foreach (EngineConstants::getAvailableEnginesList() as $engineName) {
            $myExtraKeys = array_merge(
                $myExtraKeys,
                (new $engineName(
                    new EngineStruct([
                        'type' => $engineName == MyMemory::class ? EngineConstants::TM : EngineConstants::MT,
                    ])
                ))->getConfigurationParameters()
            );
        }

        $myExtraKeys = array_unique($myExtraKeys);

        foreach ($project->getAllMetadata() as $metadatum) {
            $key = $metadatum->key;

            if (in_array($key, $myExtraKeys)) {
                $metadata->mt_extra->$key = $metadatum->value;
            } else {
                $metadata->$key = $metadatum->value;
            }
        }

        return $metadata;
    }

    /**
     * @param JobStruct $job
     *
     * @return stdClass
     * @throws ReflectionException
     */
    private function getJobMetaData(JobStruct $job): object
    {
        $metadata = new stdClass();
        $jobMetaDataDao = new MetadataDao();

        foreach ($jobMetaDataDao->getByJobIdAndPassword($job->id, $job->password, 60 * 5) as $metadatum) {
            $metadata->{$metadatum->key} = $metadatum->value;
        }

        if (!property_exists($metadata, MetadataDao::SUBFILTERING_HANDLERS)) {
            $metadata->{MetadataDao::SUBFILTERING_HANDLERS} = [];
        }

        return $metadata;
    }

    /**
     * @param JobStruct $job
     *
     * @return array
     * @throws ReflectionException
     */
    private function getJobFilesMetaData(JobStruct $job): array
    {
        $metadata = [];
        $filesMetaDataDao = new FileMetadataDao();

        foreach ($job->getFiles() as $file) {
            $metadatum = new stdClass();
            foreach ($filesMetaDataDao->getByJobIdProjectAndIdFile($job->getProject()->id, $file->id, 60 * 5) as $meta) {
                $metadatum->{$meta->key} = $meta->value;
            }

            $metadataObject = new stdClass();
            $metadataObject->id = $file->id;
            $metadataObject->filename = $file->filename;
            $metadataObject->data = $metadatum;

            $metadata[] = $metadataObject;
        }

        return $metadata;
    }
}