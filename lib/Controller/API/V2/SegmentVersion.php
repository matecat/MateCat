<?php

namespace API\V2;

use API\V2\Json\SegmentVersion as JsonFormatter;
use API\V2\Validators\JobPasswordValidator;
use API\V2\Validators\SegmentValidator;
use Features\TranslationVersions\Model\TranslationVersionDao;


class SegmentVersion extends BaseChunkController {

    protected function afterConstruct() {
        $this->appendValidator( new JobPasswordValidator( $this ) );
        $this->appendValidator( new SegmentValidator( $this ) );
    }

    public function index() {

        $results = TranslationVersionDao::getVersionsForTranslation(
                $this->request->id_job,
                $this->request->id_segment
        );

        $chunk = \Chunks_ChunkDao::getByIdAndPassword($this->params[ 'id_job' ], $this->params[ 'password' ]);

        $this->chunk = $chunk;
        $this->return404IfTheJobWasDeleted();

        $formatted = new JsonFormatter( $chunk, $results );

        $this->response->json( [
                'versions' => $formatted->render()
        ] );

    }

}
