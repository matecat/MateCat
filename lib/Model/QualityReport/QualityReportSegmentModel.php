<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 07/09/2018
 * Time: 11:21
 */

class QualityReport_QualityReportSegmentModel {

    public function getSegmentsIdForQR( $jid, $password, $step = 10, $ref_segment, $where = "after", $options = [] ) {

        $segmentsDao = new \Segments_SegmentDao;
        $segments_id = $segmentsDao->getSegmentsIdForQR( $jid, $password, $step, $ref_segment, $where );

        return $segments_id;
    }

    public function getSegmentsForQR( $segments_id, $features ) {
        $segmentsDao = new \Segments_SegmentDao;
        $data        = $segmentsDao->getSegmentsForQr( $segments_id );

        $codes = $features->getCodes();
        if ( in_array( Features\ReviewExtended::FEATURE_CODE, $codes ) OR in_array( Features\ReviewImproved::FEATURE_CODE, $codes ) ) {
            $issues = \Features\ReviewImproved\Model\QualityReportDao::getIssuesBySegments( $segments_id );
        } else {
            $reviseDao = new \Revise_ReviseDAO();
            $issues    = $reviseDao->readBySegments( $segments_id );
        }

        $commentsDao = new \Comments_CommentDao;
        $comments    = $commentsDao->getThreadsBySegments( $segments_id );

        $segments = [];
        foreach ( $data as $i => $seg ) {

            $seg->warnings      = $seg->getLocalWarning();
            $seg->pee           = $seg->getPEE();
            $seg->ice_modified  = $seg->isICEModified();
            $seg->secs_per_word = $seg->getSecsPerWord();

            $seg->parsed_time_to_edit = CatUtils::parse_time_to_edit( $seg->time_to_edit );

            $seg->segment = CatUtils::rawxliff2view( $seg->segment );

            $seg->translation = CatUtils::rawxliff2view( $seg->translation );

            foreach ( $issues as $issue ) {
                if ( $issue->id_segment == $seg->sid ) {
                    $seg->issues[] = $issue;
                }
            }

            foreach ( $comments as $comment ) {
                $comment->templateMessage();
                if ( $comment->id_segment == $seg->sid ) {
                    $seg->comments[] = $comment;
                }
            }

            $segments[] = $seg;
        }

        return $segments;

    }


}
