<?php

namespace API\V3;

use API\V2\BaseChunkController;
use API\V2\Exceptions\NotFoundException;
use API\V2\KleinController;
use Translations_SegmentTranslationDao;

class IssueCheckController extends BaseChunkController {

    public function segments() {

        $result = [
                'modified_segments_count' => 0,
                'issue_count'             => 0,
                'modified_segments'       => []
        ];

        // params
        $id_job      = $this->request->param( 'id_job' );
        $password    = $this->request->param( 'password' );
        $source_page = $this->request->param( 'source_page', 2 );

        // find a job
        $job = $this->getJob( $id_job, $password );

        if ( null === $job ) {
            throw new NotFoundException( 'Job not found.' );
        }

        $this->chunk = $job;
        $this->return404IfTheJobWasDeleted();

        $modifiedSegments = (new Translations_SegmentTranslationDao())
                ->setCacheTTL( 60 * 5 )
                ->getSegmentTranslationsModifiedByRevisorWithIssueCount( $id_job, $password, $source_page );

        $result[ 'modified_segments_count' ] = count( $modifiedSegments );

        foreach ( $modifiedSegments as $modifiedSegment ) {

            $result[ 'modified_segments' ][] = [
                    'id_segment' => (int)$modifiedSegment->id_segment,
                    'issue_count' => (int)$modifiedSegment->q_count,
            ];

            $result[ 'issue_count' ] = (int)$result[ 'issue_count' ] + (int)$modifiedSegment->q_count;
        }

        $this->response->json( $result );
    }
}

