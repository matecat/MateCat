<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Features\ProjectCompletion\CompletionEventStruct;
use Exception;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\ChunksCompletion\ChunkCompletionEventStruct;
use Model\Jobs\JobStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Projects\ProjectDao;
use Plugins\Features\ProjectCompletion\Model\EventModel;
use ReflectionException;
use TypeError;
use Utils\Constants\SourcePages;
use Utils\Tools\Utils;

class SetChunkCompletedController extends KleinController
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
     * @throws Exception
     * @throws TypeError
     */
    public function complete(): void
    {
        $struct = new CompletionEventStruct([
            'uid' => $this->user->getUid(),
            'remote_ip_address' => Utils::getRealIpAddr() ?? '',
            'source' => ChunkCompletionEventStruct::SOURCE_USER,
            'is_review' => $this->isRevision()
        ]);

        $database = $this->getDatabase();
        $model = new EventModel(
            $this->chunk,
            $struct,
            new ChunkCompletionEventDao($database),
            new ProjectDao($database),
            new FeatureSet($database),
            $database,
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
}