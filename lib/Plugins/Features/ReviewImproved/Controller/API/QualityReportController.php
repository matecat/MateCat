<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/26/16
 * Time: 2:32 PM
 */

namespace Features\ReviewImproved\Controller\API;

use Features\ReviewImproved\View\Json\QualityReportJSONFormatter as JsonFormatter ;

use API\V2\JobPasswordValidator;
use API\V2\ProtectedKleinController;
use LQA\ChunkReviewDao;

class QualityReportController extends ProtectedKleinController
{

    /**
     * @var JobPasswordValidator
     */
    protected $validator;

    public function show() {

        $chunk = $this->validator->getChunk();

        $chunk_reviews = ChunkReviewDao::findChunkReviewsByChunkIds(
            array( array( $chunk->id, $chunk->password) )
        );
        $struct = $chunk_reviews[0];

        $json = new JsonFormatter();
        $rendered = $json->renderItem( $struct );

        $this->response->json( $rendered );
    }

    protected function afterConstruct() {
        $this->validator = new JobPasswordValidator( $this->request );
    }

    protected function validateRequest() {
        $this->validator->validate();

    }
}