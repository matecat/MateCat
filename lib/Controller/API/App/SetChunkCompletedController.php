<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Features\ProjectCompletion\CompletionEventStruct;
use Controller\Traits\APISourcePageGuesserTrait;
use Exception;
use InvalidArgumentException;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\ChunksCompletion\ChunkCompletionEventStruct;
use Model\Jobs\JobDao;
use Model\FeaturesBase\FeatureSet;
use Model\Projects\ProjectDao;
use Plugins\Features\ProjectCompletion\Model\EventModel;
use ReflectionException;
use TypeError;
use Utils\Tools\Utils;

class SetChunkCompletedController extends KleinController
{

    use APISourcePageGuesserTrait;

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function complete(): void
    {
        $request = $this->validateTheRequest();

        $struct = new CompletionEventStruct([
            'uid' => $this->user->getUid(),
            'remote_ip_address' => Utils::getRealIpAddr() ?? '',
            'source' => ChunkCompletionEventStruct::SOURCE_USER,
            'is_review' => $this->isRevision()
        ]);

        $database = $this->getDatabase();
        $model = new EventModel(
            $request['job'],
            $struct,
            new ChunkCompletionEventDao($database),
            new ProjectDao($database),
            new FeatureSet(null, $database),
        );
        $model->save();

        $this->response->json([
            'data' => [
                'event' => [
                    'id' => (int)$model->getChunkCompletionEventId()
                ]
            ]
        ]);
    }

    /**
     * @return array<string, mixed>
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws TypeError
     */
    private function validateTheRequest(): array
    {
        $id_job = filter_var($this->request->param('id_job'), FILTER_SANITIZE_NUMBER_INT);
        $password = filter_var($this->request->param('password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $received_password = filter_var($this->request->param('current_password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);

        if (empty($id_job)) {
            throw new InvalidArgumentException("Missing id job", -1);
        }

        if (empty($password)) {
            throw new InvalidArgumentException("Missing id password", -2);
        }

        $job = (new JobDao($this->getDatabase()))->getByIdAndPassword((int)$id_job, (string)$password);

        if (empty($job)) {
            throw new InvalidArgumentException("wrong password", -10);
        }

        $this->id_job = (int)$id_job;
        $this->request_password = (string)$received_password;

        return [
            'id_job' => $id_job,
            'password' => $password,
            'received_password' => $received_password,
            'job' => $job,
        ];
    }
}