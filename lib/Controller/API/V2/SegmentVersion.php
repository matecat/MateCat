<?php

namespace API\V2;

use API\V2\Json\SegmentVersion as JsonFormatter;
use API\V2\Validators\JobPasswordValidator;
use API\V2\Validators\SegmentValidator;


class SegmentVersion extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new JobPasswordValidator( $this ) );
        $this->appendValidator( new SegmentValidator( $this ) );
    }

    public function index() {

        $results = \Translations_TranslationVersionDao::getVersionsForTranslation(
                $this->request->id_job,
                $this->request->id_segment
        );

        $formatted = new JsonFormatter( $results );

        $this->response->json( [
                'versions' => $formatted->render()
        ] );

    }

}
