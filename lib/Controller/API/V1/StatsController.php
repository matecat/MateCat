<?php

namespace API\V1;


use API\V2\ProtectedKleinController;
use API\V2\Validators\ChunkPasswordValidator;

class StatsController extends ProtectedKleinController {

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
        
        $this->response->json(array(
            'stats' => $job_stats
        ));
    }

    protected function validateRequest() {
        $this->validator->validate();
    }

    protected function afterConstruct() {
        $this->validator = new ChunkPasswordValidator( $this->request );
    }

}