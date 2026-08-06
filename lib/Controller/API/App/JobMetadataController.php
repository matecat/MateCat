<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\TeamAccessValidator;
use Exception;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use Model\Projects\ProjectDao;
use ReflectionException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class JobMetadataController extends KleinController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));

        // The job password is a capability to work on the job, not permission to change how it
        // behaves: these settings alter tag parsing, TM prioritisation and the issues a reviewer is
        // required to fill in, for everyone on the job. The editor only offers them to the team that
        // owns the project, so the same rule has to hold here rather than in the browser alone.
        $teamValidator = new TeamAccessValidator($this);

        $chunkValidator = new ChunkPasswordValidator($this);
        $chunkValidator->onSuccess(function () use ($chunkValidator, $teamValidator) {
            // The chunk is already in hand from the validator that just resolved it, so the job is not
            // read a second time.
            $teamValidator->setIdTeam($this->resolveTeamId($chunkValidator->getChunk()));
        });

        // Registration order is execution order, so the team check runs after the callback above has
        // supplied the id_team. Appending it from inside the callback would not work: validateRequest()
        // iterates a copy of the list and would never reach it.
        $this->appendValidator($chunkValidator);
        $this->appendValidator($teamValidator);
    }

    /**
     * Reads the owning team from projects.id_team for the job the caller addressed.
     *
     * @throws AuthorizationError if the project has no team, since a project without a team has no
     *                            members and there is nobody the membership check could match
     * @throws ReflectionException
     * @throws Exception
     */
    protected function resolveTeamId(JobStruct $chunk): int
    {
        $project = $chunk->getProject(new ProjectDao($this->getDatabase()));

        return $project->id_team ?? throw new AuthorizationError('Not Authorized', 401);
    }

    /**
     * Delete metadata by key
     * @throws ReflectionException
     * @throws Exception
     */
    public function delete(): void
    {
        $params = $this->sanitizeRequestParams();
        $dao = new MetadataDao($this->getDatabase());

        $struct = $dao->get((int)$params['id_job'], (string)$params['password'], (string)$params['key']);

        if (empty($struct)) {
            throw new NotFoundException('Metadata not found', 404);
        }

        $dao->delete((int)$params['id_job'], (string)$params['password'], (string)$params['key']);
        $this->response->json([
            'id' => $struct->id
        ]);
    }

    /**
     * Upsert metadata
     * @throws Exception
     */
    public function save(): void
    {
        $dao = new MetadataDao($this->getDatabase());

        // accept only JSON
        if (!$this->isJsonRequest()) {
            throw new Exception('Bad request', 400);
        }

        $params = $this->sanitizeRequestParams();

        $jsonValidatorObject = new JSONValidatorObject($this->request->body());
        $jsonValidator = new JSONValidator('job_metadata.json', true);
        $jsonValidator->validate($jsonValidatorObject);

        $return = [];
        foreach ($jsonValidatorObject->getValue(true) as $item) {
            $struct = $dao->set(
                (int)$params['id_job'],
                (string)$params['password'],
                $item['key'],
                is_array($item['value']) ? json_encode($item['value']) : $item['value'] ?? 'null'
            );
            $return[] = $struct;
        }

        $this->response->json($return);
    }

    /**
     * @return array{id_job: string|false|null, password: string|false|null, key: string|false|null}
     */
    private function sanitizeRequestParams(): array
    {
        return filter_var_array($this->request->params(), [
            'id_job' => FILTER_SANITIZE_SPECIAL_CHARS,
            'password' => FILTER_SANITIZE_SPECIAL_CHARS,
            'key' => FILTER_SANITIZE_SPECIAL_CHARS,
        ]);
    }
}