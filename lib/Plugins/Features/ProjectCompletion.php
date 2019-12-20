<?php

namespace Features;

use Chunks_ChunkCompletionEventDao;
use Chunks_ChunkCompletionUpdateDao;
use Chunks_ChunkCompletionUpdateStruct;
use Chunks_ChunkStruct;
use Jobs_JobStruct;
use Utils;

class ProjectCompletion extends BaseFeature {

    const FEATURE_CODE = 'project_completion';

    public function postAddSegmentTranslation( $params ) {
        $params = Utils::ensure_keys( $params, [ 'is_review', 'chunk' ] );

        // Here we need to find or update the corresponding record,
        // to register the event of the segment translation being updated
        // from a review page or a translate page.

        /** @var Chunks_ChunkStruct $chunk */
        $chunk                                     = $params[ 'chunk' ];
        $chunk_completion_update_struct            = new Chunks_ChunkCompletionUpdateStruct( $chunk->toArray() );
        $chunk_completion_update_struct->is_review = $params[ 'is_review' ];
        $chunk_completion_update_struct->source    = 'user';
        $chunk_completion_update_struct->id_job    = $chunk->id;

        if ( isset( $params[ 'logged_user' ] ) && $params[ 'logged_user' ]->uid ) {
            $chunk_completion_update_struct->uid = $params[ 'logged_user' ]->uid;
        }

        $chunk_completion_update_struct->setTimestamp( 'last_translation_at', strtotime( 'now' ) );

        $dao           = new Chunks_ChunkCompletionEventDao();
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

    public function job_password_changed( Jobs_JobStruct $job, $old_password ) {
        $dao = new Chunks_ChunkCompletionUpdateDao();
        $dao->updatePassword( $job->id, $job->password, $old_password );

        $dao = new Chunks_ChunkCompletionEventDao();
        $dao->updatePassword( $job->id, $job->password, $old_password );
    }

}
