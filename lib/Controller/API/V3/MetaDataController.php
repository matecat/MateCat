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
        /**
         * The MT settings (DeepL formality, Lara style, the glossaries, the MT application
         * threshold) are reported on both scopes, and a client has to read `job` first and only then
         * `project`:
         *
         * - a project created before those settings moved to the job answers from `project` alone,
         *   and cannot be migrated — production holds billions of project_metadata rows;
         * - a project created after the move answers from `job` alone, per target language, and is
         *   the only scope the owner's later edits are written to.
         *
         * The engine parameters sit under `mt_extra` on both scopes; everything else, the
         * threshold included, is reported flat.
         *
         * @see \Model\Jobs\JobSettingsResolver the same precedence, applied server side
         */
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

        $myExtraKeys = self::engineConfigurationKeys();

        foreach ((new ProjectMetadataDao($this->getDatabase()))->setCacheTTL(3600)->allByProjectId((int) $project->id) as $metadatum) {
            $key = $metadatum->key;

            if (in_array($key, $myExtraKeys, true)) {
                $metadata->mt_extra->$key = $metadatum->value;
            } else {
                $metadata->$key = $metadatum->value;
            }
        }

        return $metadata;
    }

    /**
     * The union of the configuration parameters of every registered MT/TM engine.
     *
     * Both scopes report these under `mt_extra` instead of flat, so the same key is found in the
     * same place whichever scope answered.
     *
     * @return list<string>
     */
    private static function engineConfigurationKeys(): array
    {
        $keys = [];

        foreach (EngineConstants::getAvailableEnginesList() as $engineName) {
            $keys = array_merge($keys, $engineName::getConfigurationParameters());
        }

        return array_values(array_unique($keys));
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
        $metadata->mt_extra = new stdClass();
        $jobMetaDataDao = new MetadataDao($this->getDatabase());

        $myExtraKeys = self::engineConfigurationKeys();

        foreach ($jobMetaDataDao->getByJobIdAndPassword(
            $job->id ?? throw new DomainException('Job ID must not be null'),
            $job->password ?? throw new DomainException('Job password must not be null'),
            60 * 5
        ) as $metadatum) {
            $key = $metadatum->key;

            if (in_array($key, $myExtraKeys, true)) {
                $metadata->mt_extra->$key = $metadatum->value;
            } else {
                $metadata->$key = $metadatum->value;
            }
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