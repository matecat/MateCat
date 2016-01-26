<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/26/16
 * Time: 2:32 PM
 */

namespace Features\ReviewImproved\Controller\API;


use API\V2\JobPasswordValidator;
use API\V2\ProtectedKleinController;

class QualityReportController extends ProtectedKleinController
{

    /**
     * @var JobPasswordValidator
     */
    protected $validator;

    public function show() {

        // password combination is valid.
        // Find QA_ChunkReview record for this combination and
        // return the quality status.
        //
        $this->validator->getChunk();


        $this->response->json( $rendered );
    }

    protected function afterConstruct() {
        $this->validator = new JobPasswordValidator( $this->request );
    }

    protected function validateRequest() {
        $this->validator->validate();

    }
}