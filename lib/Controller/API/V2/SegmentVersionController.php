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
use Model\Jobs\JobStruct;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use ReflectionException;
use View\API\V2\Json\SegmentVersion;


class SegmentVersionController extends KleinController {
    use ChunkNotFoundHandlerTrait;
    /**
     * @throws ReflectionException
     * @throws NotFoundException
     */
    protected function afterConstruct() {
        $this->appendValidator( new JobPasswordValidator( $this ) );
        $this->appendValidator( new SegmentValidator( $this ) );
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     */
    public function index() {

        $results = TranslationVersionDao::getVersionsForTranslation(
                $this->request->param( 'id_job' ),
                $this->request->param( 'id_segment' )
        );

        $chunk = ChunkDao::getByIdAndPassword( $this->params[ 'id_job' ], $this->params[ 'password' ] );

        $this->chunk = $chunk;
        $this->return404IfTheJobWasDeleted();

        $formatted = new SegmentVersion( $chunk, $results );

        $this->response->json( [
                'versions' => $formatted->render()
        ] );

    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     */
    public function detail() {

        $results = TranslationVersionDao::getVersionsForTranslation(
                $this->request->param( 'id_job' ),
                $this->request->param( 'id_segment' ),
                $this->request->param( 'version_number' )
        );

        $chunk = ChunkDao::getByIdAndPassword( $this->params[ 'id_job' ], $this->params[ 'password' ] );

        $this->chunk = $chunk;
        $this->return404IfTheJobWasDeleted();

        $formatted = new SegmentVersion( $chunk, $results );

        $this->response->json( [
                'versions' => $formatted->render()
        ] );

        //https://www.matecat.com/api/v2/jobs/4316371/0ef859019079/segments/2017797016/translation-versions/0
    }

    /**
     * To maintain compatibility with JobPasswordValidator
     * (line 36)
     *
     * @param JobStruct $jobs_JobStruct
     */
    public function setChunk( JobStruct $jobs_JobStruct ) {
        $this->chunk = $jobs_JobStruct;
    }
}
