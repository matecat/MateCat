<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/24/16
 * Time: 11:56 AM
 */

namespace API\V2;

use API\V2\Json\SegmentTranslationIssue as JsonFormatter;

class ChunkTranslationIssueController extends ProtectedKleinController {

    /**
     * @var Validators\ChunkPasswordValidator
     */
    private $validator;

    public function index() {

        // find all issues by chunk and return the json representation.
        $result = \LQA\EntryDao::findAllByChunk( $this->validator->getChunk() );

        $json     = new JsonFormatter();
        $rendered = $json->renderArray( $result );

        $this->response->json( array( 'issues' => $rendered ) );
    }

    protected function afterConstruct() {
        $this->validator = new Validators\ChunkPasswordValidator( $this->request );
    }

    protected function validateRequest() {
        $this->validator->validate();
    }

}