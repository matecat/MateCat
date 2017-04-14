<?php

namespace API\V1;


use API\V2\KleinController;
use API\V2\Validators\ChunkPasswordValidator;

class StatsController extends KleinController {

    /**
     * @var ChunkPasswordValidator
     */
    protected $validator ;

    public function stats() {

        $job = $this->validator->getChunk();

        $wStruct = new \WordCount_Struct();

        $wStruct->setIdJob( $job->id );
        $wStruct->setJobPassword( $job->password );
        $wStruct->setNewWords( $job->new_words );
        $wStruct->setDraftWords( $job->draft_words );
        $wStruct->setTranslatedWords( $job->translated_words );
        $wStruct->setApprovedWords( $job->approved_words );
        $wStruct->setRejectedWords( $job->rejected_words );

        $job_stats = \CatUtils::getFastStatsForJob( $wStruct );

        $job_stats['ANALYSIS_COMPLETE'] = $job->getProject()->analysisComplete() ;


        $response = array( 'stats' => $job_stats );

        $featureSet = new \FeatureSet();
        $featureSet->loadForProject( $job->getProject() )  ;
        $response = $featureSet->filter('filterStatsControllerResponse', $response, array(
            'chunk' => $job ) );

        $this->response->json( $response ) ;
    }

    protected function validateRequest() {
        $this->validator->validate();
    }

    protected function afterConstruct() {
        $this->validator = new ChunkPasswordValidator( $this->request );
    }

}