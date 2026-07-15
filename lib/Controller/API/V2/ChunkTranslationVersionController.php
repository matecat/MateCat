<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/26/16
 * Time: 12:00 PM
 */

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exception;
use Model\Projects\ProjectDao;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use View\API\V2\Json\SegmentVersion;


class ChunkTranslationVersionController extends KleinController
{
    use ChunkNotFoundHandlerTrait;

    /**
     * @throws Exception
     */
    public function index(): void
    {
        $this->return404IfTheJobWasDeleted();

        $results = (new TranslationVersionDao($this->getDatabase()))->getVersionsForChunk($this->chunk);

        $this->featureSet->loadForProject($this->chunk->getProject(new ProjectDao($this->getDatabase())));

        $formatted = new SegmentVersion($this->chunk, $results, false, $this->featureSet);

        $this->response->json([
            'versions' => $formatted->render()
        ]);
    }

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $Validator = new ChunkPasswordValidator($this);
        $Validator->onSuccess(function () use ($Validator) {
            $this->chunk = $Validator->getChunk();
        });
        $this->appendValidator($Validator);
    }

}