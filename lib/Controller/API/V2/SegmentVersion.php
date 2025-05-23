<?php

namespace API\V2;

use API\Commons\Validators\JobPasswordValidator;
use API\Commons\Validators\LoginValidator;
use API\Commons\Validators\SegmentValidator;
use API\V2\Json\SegmentVersion as JsonFormatter;
use Chunks_ChunkDao;
use Exceptions\NotFoundException;
use Features\TranslationVersions\Model\TranslationVersionDao;
use ReflectionException;


class SegmentVersion extends BaseChunkController {

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
     */
    public function index() {

        $results = TranslationVersionDao::getVersionsForTranslation(
                $this->request->param( 'id_job' ),
                $this->request->param( 'id_segment' )
        );

        $chunk = Chunks_ChunkDao::getByIdAndPassword($this->params[ 'id_job' ], $this->params[ 'password' ]);

        $this->chunk = $chunk;
        $this->return404IfTheJobWasDeleted();

        $formatted = new JsonFormatter( $chunk, $results );

        $this->response->json( [
                'versions' => $formatted->render()
        ] );

    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     */
    public function detail() {

        $results = TranslationVersionDao::getVersionsForTranslation(
                $this->request->param( 'id_job' ),
                $this->request->param( 'id_segment' ),
                $this->request->param( 'version_number' )
        );

        $chunk = Chunks_ChunkDao::getByIdAndPassword($this->params[ 'id_job' ], $this->params[ 'password' ]);

        $this->chunk = $chunk;
        $this->return404IfTheJobWasDeleted();

        $formatted = new JsonFormatter( $chunk, $results );

        $this->response->json( [
                'versions' => $formatted->render()
        ] );

        //https://www.matecat.com/api/v2/jobs/4316371/0ef859019079/segments/2017797016/translation-versions/0
    }

    /**
     * To maintain compatibility with JobPasswordValidator
     * (line 36)
     *
     * @param \Jobs_JobStruct $jobs_JobStruct
     */
    public function setChunk(\Jobs_JobStruct $jobs_JobStruct) {
        $this->chunk = $jobs_JobStruct;
    }
}
