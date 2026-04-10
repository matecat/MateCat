<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 31/05/2017
 * Time: 14:57
 */


namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\ChunksCompletion\ChunkCompletionEventStruct;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\Hook\Event\Run\AlterChunkReviewStructEvent;
use Model\Jobs\JobStruct;

class CompletionEventController extends KleinController
{

    /**
     * @var JobStruct
     */
    protected JobStruct $chunk;

    /**
     * @var ChunkCompletionEventStruct
     */
    protected ChunkCompletionEventStruct $event;

    /**
     * @throws Exception
     */
    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));

        $Validator = new ChunkPasswordValidator($this);
        $Validator->onSuccess(function () use ($Validator) {
            $event = (new ChunkCompletionEventDao())->getByIdAndChunk($this->getParams()['id_event'], $Validator->getChunk());

            if (!$event) {
                throw new NotFoundException("Event Not Found.", 404);
            }

            $this->chunk = $Validator->getChunk();
            $this->event = $event;
            $this->featureSet->loadForProject($this->chunk->getProject(60 * 60));
        });

        $this->appendValidator($Validator);
    }

    /**
     * @throws Exception
     */
    public function delete(): void
    {
        $this->__performUndo();
        $this->response->code(200);
        $this->response->send();
    }

    /**
     * @throws Exception
     */
    private function __performUndo(): void
    {
        Database::obtain()->begin();

        /**
         * This method means to allow project_completion to work alone, the undo feature belongs to AbstractRevisionFeature
         */
        $this->featureSet->dispatchRun(new AlterChunkReviewStructEvent($this->event));

        (new ChunkCompletionEventDao())->deleteEvent($this->event);
        Database::obtain()->commit();
    }

}
