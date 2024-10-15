<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use API\V2\Json\SegmentTranslationMismatches;
use Exception;
use Projects_ProjectDao;
use Segments_SegmentDao;

class GetTranslationMismatchesController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function get()
    {
        try {
            $id_job = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
            $id_segment = filter_var( $this->request->param( 'id_segment' ), FILTER_SANITIZE_NUMBER_INT );
            $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );

            $this->featureSet->loadForProject( Projects_ProjectDao::findByJobId( $id_job, 60 * 60 ) );
            $parsedIdSegment = $this->parseIDSegment($id_segment);

            if ( $parsedIdSegment['id_segment'] == '' ) {
                $parsedIdSegment['id_segment'] = 0;
            }

            $sDao                   = new Segments_SegmentDao();
            $Translation_mismatches = $sDao->setCacheTTL( 1 * 60 /* 1 minutes cache */ )->getTranslationsMismatches( $id_job, $password, $parsedIdSegment['id_segment'] );

            return $this->response->json([
                'errors' => [],
                'code' => 1,
                'data' => ( new SegmentTranslationMismatches( $Translation_mismatches, count( $Translation_mismatches ), $this->featureSet ) )->render()
            ]);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }
}