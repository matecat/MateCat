<?php

namespace API\V3;

use API\V2\KleinController;
use DomainException;
use Teams\MembershipDao;
use Translations_SegmentTranslationDao;

class IssueCheckController extends KleinController {

    public function segments() {

        $result = [
                'modified_segments_count' => 0,
                'issue_count'             => 0,
                'modified_segments'       => [
                        'with_issues'    => [],
                        'without_issues' => [],
                ]
        ];

        // params
        $id_job      = $this->request->param( 'id_job' );
        $password    = $this->request->param( 'password' );
        $source_page = $this->request->param( 'source_page', 2 );

        $modifiedSegments = (new Translations_SegmentTranslationDao())->setCacheTTL( 60 * 5 )->getSegmentTranslationsModifiedByRevisorWithIssueCount( $id_job,
                        $password, $source_page );

        $result[ 'modified_segments_count' ] = count( $modifiedSegments );

        foreach ( $modifiedSegments as $modifiedSegment ) {
            if ( $modifiedSegment[ 'q_count' ] > 0 ) {
                $result[ 'modified_segments' ][ 'with_issues' ][] = $modifiedSegment[ 'id_segment' ];
                $result[ 'issue_count' ]                          = $result[ 'issue_count' ] + $modifiedSegment[ 'q_count' ];
            } else {
                $result[ 'modified_segments' ][ 'without_issues' ][] = $modifiedSegment[ 'id_segment' ];
            }
        }

        $this->response->json( $result );
    }
}

