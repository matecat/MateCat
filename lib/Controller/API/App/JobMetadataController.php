<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\TeamAccessValidator;
use Exception;
use InvalidArgumentException;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use Model\Projects\ProjectDao;
use ReflectionException;
use TypeError;
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

        return $project->id_team ?? throw new AuthorizationError('Not authorized', 401);
    }

    /**
     * Delete metadata by key
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
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
     * @throws InvalidArgumentException when a payload sets both Intento keys at once
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

        $items = $jsonValidatorObject->getValue(true);

        $this->assertIntentoKeysAreExclusive($items);

        $return = [];
        foreach ($items as $item) {
            $struct = $dao->set(
                (int)$params['id_job'],
                (string)$params['password'],
                $item['key'],
                self::marshallValue($item)
            );

            // set() answers with the row as stored, and the column is a string. Un-marshalling it
            // here is what MetadataDao::getByJobIdAndPassword() does for the read endpoint, so a
            // client gets the same types back from this response as from GET /metadata and can
            // reuse what it just sent without a second round trip.
            if ($struct !== null) {
                $struct->value = JobsMetadataMarshaller::unMarshall($struct);
            }

            $return[] = $struct;
        }

        $this->response->json($return);
    }

    /**
     * The column is a string, so every value is stored as one.
     *
     * Three keys are stored as the empty string, which is how the job scope says "explicitly none"
     * for a setting whose project row can never be unwritten. The two Intento branches take it as a
     * null — or as a missing value, since every other branch still requires one — and
     * `deepl_id_glossary` takes it either way, including as the empty string the client read back
     * from GET /metadata. {@see \Utils\Engines\Intento::get()} and {@see \Utils\Engines\DeepL::get()}
     * both read it with !empty().
     *
     * @param array<string, mixed> $item
     */
    private static function marshallValue(array $item): string
    {
        $value = $item['value'] ?? null;

        return match (true) {
            is_array($value) => (string)json_encode($value),
            $value === null => '',
            default => (string)$value,
        };
    }

    /**
     * Intento takes a provider or a routing, never both: {@see \Utils\Engines\Intento::get()} picks
     * the provider whenever it is set, so a job holding both would silently ignore the routing.
     *
     * {@see \Utils\Engines\Validators\IntentoEngineOptionsValidator} enforces the same rule at
     * project creation and cannot be reused here — it wants an engine struct, and its else branch
     * also rejects a payload that sets neither key, which is every payload that is not about
     * Intento.
     *
     * @param array<int, array<string, mixed>> $items
     *
     * @throws InvalidArgumentException
     */
    private function assertIntentoKeysAreExclusive(array $items): void
    {
        $set = [];

        foreach ($items as $item) {
            $key = $item['key'] ?? null;

            if ($key === JobsMetadataMarshaller::INTENTO_PROVIDER->value
                || $key === JobsMetadataMarshaller::INTENTO_ROUTING->value) {
                // A cleared key is not a set one, and it is the whole point of accepting null here.
                if (self::marshallValue($item) !== '') {
                    $set[$key] = true;
                }
            }
        }

        if (count($set) > 1) {
            throw new InvalidArgumentException('Intento provider and routing cannot be set at the same time.');
        }
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