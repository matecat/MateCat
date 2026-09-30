<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use DomainException;
use Exception;
use InvalidArgumentException;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\MetadataDao;
use ReflectionException;
use TypeError;
use Utils\Constants\SourcePages;
use Utils\TmKeyManagement\ClientTmKeyStruct;
use Utils\TmKeyManagement\Filter;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\TmKeyManagement\TmKeyStruct;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class UpdateJobKeysController extends KleinController
{
    protected JobStruct $chunk;

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));

        // Resolve the job and its revision phase from the presented credential (password), not from a
        // spoofable Referer. ChunkPasswordValidator stamps source_page onto the chunk from whichever
        // password (translate or review) matched.
        $chunkValidator = new ChunkPasswordValidator($this);
        $chunkValidator->onSuccess(function () use ($chunkValidator) {
            $this->chunk = $chunkValidator->getChunk();
        });
        $this->appendValidator($chunkValidator);
    }

    /**
     * The revision phase is derived from the credential-resolved source_page stamped on the chunk
     * (see registerValidators), never from the request Referer.
     */
    private function isRevision(): bool
    {
        return ($this->chunk->getSourcePage() ?: SourcePages::SOURCE_PAGE_TRANSLATE) !== SourcePages::SOURCE_PAGE_TRANSLATE;
    }

    /**
     * @throws ReflectionException
     * @throws AuthenticationError
     * @throws Exception
     * @throws TypeError
     */
    public function update(): void
    {
        $request = $this->validateTheRequest();

        // Credential-resolved job (ChunkPasswordValidator, see registerValidators).
        $jobData = $this->chunk;

        if ($this->user->email == $jobData['owner']) {
            $userRole = Filter::OWNER;
        } elseif ($this->isRevision()) {
            $userRole = Filter::ROLE_REVISOR;
        } else {
            $userRole = Filter::ROLE_TRANSLATOR;
        }

        /*
         * The client send data as structured json, for now take it as a plain structure
         *
         *   $clientDecodedJson = Array
         *       (
         *           [owner] => Array
         *               (
         *                   [0] => Array
         *                       (
         *                           [tm] => 1
         *                           [glos] => 1
         *                           [owner] => 1
         *                           [key] => ***************da9a9
         *                           [name] =>
         *                           [r] => 1
         *                           [w] => 1
         *                       )
         *
         *               )
         *
         *           [mine] => Array
         *               (
         *                   [0] => Array
         *                       (
         *                           [tm] => 1
         *                           [glos] => 1
         *                           [owner] => 0
         *                           [key] => 952681baffb9c147b346
         *                           [name] => cgjhkmfgdcjkfh
         *                           [r] => 1
         *                           [w] => 1
         *                       )
         *
         *               )
         *
         *           [anonymous] => Array
         *               (
         *                   [0] => Array
         *                       (
         *                           [tm] => 1
         *                           [glos] => 1
         *                           [owner] => 0
         *                           [key] => ***************882eb
         *                           [name] => Chiave di anonimo
         *                           [r] => 0
         *                           [w] => 0
         *                       )
         *
         *               )
         *
         *       )
         *
         */
        $tm_keys = json_decode($request['tm_keys'], true);
        $clientKeys = $jobData->getClientKeys($this->user, $userRole, $this->getDatabase());

        /*
         * sanitize owner role key type
         */
        foreach ($tm_keys['mine'] as $k => $val) {
            // check if logged user is owner of $val['key']
            $check = array_filter($clientKeys['job_keys'], function (ClientTmKeyStruct $element) use ($val) {
                if ($element->isEncryptedKey()) {
                    return false;
                }

                return $val['key'] === $element->key;
            });

            $tm_keys['mine'][$k]['owner'] = !empty($check);
        }

        $tm_keys = array_merge($tm_keys['ownergroup'], $tm_keys['mine'], $tm_keys['anonymous']);
        $tm_keys = json_encode($tm_keys);


        $totalTmKeys = TmKeyManager::mergeJsonKeys((string)$tm_keys, $jobData['tm_keys'], $this->getDatabase(), $userRole, $this->user->uid);

        $this->logger->debug('Before: ' . $jobData['tm_keys']);
        $this->logger->debug('After: ' . json_encode($totalTmKeys));

        if ($this->jobOwnerIsMe($jobData['owner'])) {
            $jobData['only_private_tm'] = $request['only_private'];
        }

        /** @var TmKeyStruct $totalTmKey */
        foreach ($totalTmKeys as $totalTmKey) {
            $totalTmKey->complete_format = true;
        }

        $jobData->tm_keys = (string)json_encode($totalTmKeys);
        $jobData->last_update = date("Y-m-d H:i:s");

        $jobDao = new JobDao($this->getDatabase());
        $jobDao->updateStruct($jobData, ['fields' => ['only_private_tm', 'tm_keys', 'last_update']]);
        $jobDao->destroyCache($jobData);

        $jobsMetadataDao = new MetadataDao($this->getDatabase());

        // update character_counter_mode job metadata
        if ($request['public_tm_penalty'] !== null) {
            $jobsMetadataDao->set((int)$jobData->id, (string)$jobData->password, JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value, $request['public_tm_penalty']);
        }

        $this->response->json([
            'data' => 'OK'
        ]);
    }

    /**
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws DomainException
     * @throws Exception
     * @throws TypeError
     */
    private function validateTheRequest(): array
    {
        $public_tm_penalty = ($this->request->param('public_tm_penalty') !== null) ? filter_var($this->request->param('public_tm_penalty'), FILTER_VALIDATE_INT) : null;
        $get_public_matches = filter_var($this->request->param('get_public_matches'), FILTER_VALIDATE_BOOLEAN);
        $tm_keys = filter_var($this->request->param('data'), FILTER_UNSAFE_RAW, ['flags' => FILTER_FLAG_STRIP_LOW]);

        if ($public_tm_penalty < 0 || $public_tm_penalty > 100) {
            throw new InvalidArgumentException("Invalid public_tm_penalty value (must be between 0 and 100)", -6);
        }

        // validate $tm_keys
        try {
            $this->validateTMKeysArray((string)$tm_keys);
        } catch (Exception $exception) {
            throw new DomainException($exception->getMessage());
        }

        return [
            'public_tm_penalty' => $public_tm_penalty,
            'get_public_matches' => $get_public_matches,
            'tm_keys' => $tm_keys, // this will be filtered inside the TmKeyManagement class
            'only_private' => !$get_public_matches,
        ];
    }

    /**
     * @param string|null $owner
     *
     * @return bool
     */
    private function jobOwnerIsMe(?string $owner): bool
    {
        return $this->userIsLogged && $owner == $this->user->email;
    }

    /**
     * @param string $tm_keys
     *
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws Exception
     */
    private function validateTMKeysArray(string $tm_keys): void
    {
        $validatorObject = new JSONValidatorObject($tm_keys);
        $validator = new JSONValidator('job_keys.json', true);
        $validator->validate($validatorObject);
    }
}
