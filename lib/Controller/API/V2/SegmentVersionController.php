<?php

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\JobPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\SegmentValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Jobs\ChunkDao;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use ReflectionException;
use View\API\V2\Json\SegmentVersion;


class SegmentVersionController extends KleinController
{
    use ChunkNotFoundHandlerTrait;

    /**
     */
    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $this->appendValidator(new SegmentValidator($this));
        $validator = new JobPasswordValidator($this);
        $validator->onSuccess(function () use ($validator) {
            $this->chunk = $validator->getJob();
        });
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     */
    public function index(): void
    {
        $results = TranslationVersionDao::getVersionsForTranslation(
            $this->request->param('id_job'),
            $this->request->param('id_segment')
        );

        $chunk = ChunkDao::getByIdAndPassword($this->params['id_job'], $this->params['password']);

        $this->chunk = $chunk;
        $this->return404IfTheJobWasDeleted();

        $formatted = new SegmentVersion($chunk, $results);

        $this->response->json([
            'versions' => $formatted->render()
        ]);
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     */
    public function detail(): void
    {
        $results = TranslationVersionDao::getVersionsForTranslation(
            $this->request->param('id_job'),
            $this->request->param('id_segment'),
            $this->request->param('version_number')
        );

        $chunk = ChunkDao::getByIdAndPassword($this->params['id_job'], $this->params['password']);

        $this->chunk = $chunk;
        $this->return404IfTheJobWasDeleted();

        $formatted = new SegmentVersion($chunk, $results);

        $this->response->json([
            'versions' => $formatted->render()
        ]);
    }

}
