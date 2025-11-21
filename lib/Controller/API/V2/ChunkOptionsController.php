<?php

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exception;
use Model\Jobs\ChunkOptionsModel;

class ChunkOptionsController extends KleinController
{
    use ChunkNotFoundHandlerTrait;

    /**
     * @throws Exception
     */
    public function update(): void
    {
        $this->return404IfTheJobWasDeleted();

        $chunk_options_model = new ChunkOptionsModel($this->chunk);

        $chunk_options_model->setOptions($this->filteredParams());
        $chunk_options_model->save();

        $this->response->json(['options' => $chunk_options_model->toArray()]);
    }

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $Validator = new ChunkPasswordValidator($this);
        $Validator->onSuccess(function () use ($Validator) {
            $this->chunk = $Validator->getChunk();
        });
        $this->appendValidator($Validator);
    }

    protected function filteredParams(): false|array|null
    {
        $args = [
                'speech2text'    => ['filter' => FILTER_VALIDATE_BOOLEAN],
                'lexiqa'         => ['filter' => FILTER_VALIDATE_BOOLEAN],
                'tag_projection' => ['filter' => FILTER_VALIDATE_BOOLEAN],
        ];

        $args = array_intersect_key($args, $this->request->params());

        return filter_var_array($this->request->params(), $args);
    }
}
