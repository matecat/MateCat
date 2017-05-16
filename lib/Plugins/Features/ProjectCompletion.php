<?php

namespace Features;

use Utils ;
use Chunks_ChunkCompletionUpdateDao;
use Chunks_ChunkCompletionUpdateStruct ;
use Chunks_ChunkCompletionEventDao ;

class ProjectCompletion extends BaseFeature {

    public function postAddSegmentTranslation( $params ) {
        $params = Utils::ensure_keys($params, array('is_review', 'chunk') );

        // Here we need to find or update the corresponding record,
        // to register the event of the segment translation being updated
        // from a review page or a translate page.

        $chunk = $params['chunk'];
        $chunk_completion_update_struct = new Chunks_ChunkCompletionUpdateStruct( $chunk->attributes() );
        $chunk_completion_update_struct->is_review = $params['is_review'];
        $chunk_completion_update_struct->source = 'user' ;
        $chunk_completion_update_struct->uid = $params['uid'];
        $chunk_completion_update_struct->id_job = $chunk->id ;
        $chunk_completion_update_struct->setTimestamp('last_translation_at', strtotime('now'));

        $dao = new Chunks_ChunkCompletionEventDao();
        $current_phase = $dao->currentPhase( $chunk );

        /**
         * Only save the record if current phase is compatible
         */
        if (
                ( $current_phase == Chunks_ChunkCompletionEventDao::REVISE && $chunk_completion_update_struct->is_review ) ||
                ( $current_phase == Chunks_ChunkCompletionEventDao::TRANSLATE && !$chunk_completion_update_struct->is_review )
        ) {
            Chunks_ChunkCompletionUpdateDao::createOrUpdateFromStruct( $chunk_completion_update_struct );
        }

    }
}
