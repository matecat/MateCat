<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/26/16
 * Time: 12:00 PM
 */

namespace API\V2;

use API\V2\Json\SegmentVersion as JsonFormatter;


class ChunkTranslationVersionController extends ProtectedKleinController {

    /**
     * @var Validators\ChunkPasswordValidator
     */
    private $validator;

    public function index() {

        $results = \Translations_TranslationVersionDao::getVersionsForChunk(
                $this->validator->getChunk()
        );

        $formatted = new JsonFormatter( $results );

        $this->response->json( array(
                'versions' => $formatted->render()
        )) ;

    }

    protected function afterConstruct() {
        $this->validator = new Validators\ChunkPasswordValidator( $this->request );
    }

    protected function validateRequest() {
        $this->validator->validate();
    }

}