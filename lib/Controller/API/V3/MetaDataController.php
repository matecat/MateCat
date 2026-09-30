<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectAccessValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use DomainException;
use Exception;
use Model\Files\FileDao;
use Model\Files\MetadataDao as FileMetadataDao;
use Model\Projects\ProjectDao;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use Model\Projects\MetadataDao as ProjectMetadataDao;
use Model\Projects\ProjectStruct;
use ReflectionException;
use RuntimeException;
use stdClass;
use Throwable;
use Utils\Constants\EngineConstants;

class MetaDataController extends KleinController
{
    use ChunkNotFoundHandlerTrait;

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }


    /**
     * The public API read, restricted to the project's owner or a member of the team it sits in.
     *
     * The editor reads the same payload through the /api/app route, which carries no such restriction:
     * its caller is a translator working the job on its password, who is not necessarily a member of
     * anything. Both entry points exist because that is the only difference between them — the payload
     * is built once, in buildMetadata().
     *
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws RuntimeException
     * @throws DomainException
     * @throws Throwable
     */
    public function index(): void
    {
        $job = $this->requireJob();

        (new ProjectAccessValidator(
            $this,
            $job->getProject(new ProjectDao($this->getDatabase())),
            $this->getUser()
        ))->validate();

        $this->response->json($this->buildMetadata($job));
    }

    /**
     * The editor's read, reached from the UI on /api/app/jobs/{id_job}/{password}/metadata. Holding the
     * job password is the whole authorization: the settings this returns are what the editor needs to
     * open, and the translator it opens for is routinely outside the owning team.
     *
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws RuntimeException
     * @throws DomainException
     * @throws Exception
     */
    public function indexForUi(): void
    {
        $this->response->json($this->buildMetadata($this->requireJob()));
    }

    /**
     * @throws NotFoundException if no job matches the id and password, or if the job was deleted
     * @throws ReflectionException
     * @throws Exception
     */
    private function requireJob(): JobStruct
    {
        // params
        $id_job = (int)$this->request->param('id_job');
        $password = (string)$this->request->param('password');

        // find a job
        $job = $this->getJob($id_job, $password);

        if (null === $job) {
            throw new NotFoundException('Job not found.');
        }

        $this->chunk = $job;
        $this->return404IfTheJobWasDeleted();

        return $job;
    }

    /**
     * @throws RuntimeException
     * @throws DomainException
     * @throws ReflectionException
     * @throws Exception
     */
    private function buildMetadata(JobStruct $job): stdClass
    {
        $metadata = new stdClass();
        $metadata->project = $this->getProjectInfo($job->getProject(new ProjectDao($this->getDatabase())));
        $metadata->job = $this->getJobMetaData($job);
        $metadata->files = $this->getJobFilesMetaData($job);

        return $metadata;
    }

    /**
     * @param ProjectStruct $project
     *
     * @return stdClass
     * @throws DomainException
     * @throws Exception
     */
    private function getProjectInfo(ProjectStruct $project): stdClass
    {
        $metadata = new stdClass();
        $metadata->mt_extra = new stdClass();

        $myExtraKeys = [];

        foreach (EngineConstants::getAvailableEnginesList() as $engineName) {
            $myExtraKeys = array_merge($myExtraKeys, $engineName::getConfigurationParameters());
        }

        $myExtraKeys = array_unique($myExtraKeys);

        foreach ((new ProjectMetadataDao($this->getDatabase()))->setCacheTTL(3600)->allByProjectId((int) $project->id) as $metadatum) {
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
     * @throws DomainException
     * @throws \Exception
     */
    private function getJobMetaData(JobStruct $job): object
    {
        $metadata = new stdClass();
        $jobMetaDataDao = new MetadataDao($this->getDatabase());

        foreach ($jobMetaDataDao->getByJobIdAndPassword(
            $job->id ?? throw new DomainException('Job ID must not be null'),
            $job->password ?? throw new DomainException('Job password must not be null'),
            60 * 5
        ) as $metadatum) {
            $metadata->{$metadatum->key} = $metadatum->value;
        }

        if (!property_exists($metadata, JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value)) {
            $metadata->{JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value} = [];
        }

        return $metadata;
    }

    /**
     * @param JobStruct $job
     *
     * @return array<int, stdClass>
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws DomainException
     * @throws \Exception
     */
    private function getJobFilesMetaData(JobStruct $job): array
    {
        $metadata = [];
        $filesMetaDataDao = new FileMetadataDao($this->getDatabase());
        $projectId = $job->getProject(new ProjectDao($this->getDatabase()))->id ?? throw new DomainException('Project ID must not be null');

        foreach ($job->getFiles(new FileDao($this->getDatabase())) as $file) {
            $metadatum = new stdClass();
            foreach ($filesMetaDataDao->getByJobIdProjectAndIdFile($projectId, $file->id, 60 * 5) ?? [] as $meta) {
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