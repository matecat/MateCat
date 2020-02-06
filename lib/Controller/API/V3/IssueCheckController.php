<?php

namespace API\V3;

use API\V2\KleinController;
use LQA\EntryDao;

class IssueCheckController extends KleinController {

    public function segments() {

        $result = [
                'total_issue_count' => 0,
                'modified_segments' => [
                        'with_issues'    => [],
                        'without_issues' => [],
                ]
        ];

        // params
        $id_job      = $this->request->param( 'id_job' );
        $password    = $this->request->param( 'password' );
        $source_page = $this->request->param( 'source_page' );

        $issueCount                    = EntryDao::getCountByIdJobAndSourcePage( $id_job, $source_page );
        $result[ 'total_issue_count' ] = $issueCount[ 'count' ];

        // get all modified segment translations by revisor
        $segmentTranslationIds = \Translations_SegmentTranslationDao::getSegmentTranslationIdsModifiedByRevisor( $id_job, $password, $source_page );

        // loop segment translations
        foreach ( $segmentTranslationIds as $segmentTranslationId ) {
            $entries = EntryDao::findByIdSegmentAndSourcePage( $segmentTranslationId->id_segment, $id_job, $source_page );

            if ( count( $entries ) > 0 ) {
                $result[ 'modified_segments' ][ 'with_issues' ][] = $segmentTranslationId->id_segment;
            } else {
                $result[ 'modified_segments' ][ 'without_issues' ][] = $segmentTranslationId->id_segment;
            }
        }

        $this->response->json( $result );
    }
}

