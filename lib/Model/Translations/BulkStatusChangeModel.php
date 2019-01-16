<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/01/2019
 * Time: 10:46
 */

namespace Translations ;

use Constants_TranslationStatus;
use Jobs_JobStruct;
use Translations_SegmentTranslationDao;

class BulkStatusChangeModel {

    protected $segment_ids ;
    protected $unchangeble_segments ;
    protected $stats ;
    protected $changeable_segments ;
    protected $destination_status ;

    protected $job ;

    public function __construct( Jobs_JobStruct $job, $segment_ids ) {
        $this->segment_ids = $segment_ids ;
        $this->job = $job ;
    }

    public function changeStatusTo( $status ) {
        $this->destination_status = strtoupper($status);

        if ( $this->_statusIsValid() && $this->anyChangeableSegmentStatus() ) {
            $this->_processChangeStatus() ;
            $this->_processReviewedWordsCount() ;
        }
    }

    protected function _processChangeStatus() {
        $this->stats = \Translations_SegmentTranslationDao::changeStatusBySegmentsIds(
                $this->job,
                $this->changeable_segments, $this->destination_status ) ;
    }

    protected function _processReviewedWordsCount() {
        // XXX: this check reflects the current behaviour of the bulk update, likely to change in the future
        if ( $this->destination_status != Constants_TranslationStatus::STATUS_APPROVED ) {
            return ;
        }

        $count = Translations_SegmentTranslationDao::getCountForReviwedWordsBySegmentId(
                $this->job->id, $this->changeable_segments ) ;

        if ( $count ) {
            // If for  any case we are getting out of a revisioned state, invert the count.
            if ( !Constants_TranslationStatus::isReviewedStatus( $this->destination_status ) ) {
                $count = -1 * abs( $count ) ;
            }

            $this->job->getProject()->getFeatures()->run('updateReviewedWordsCount', $count, $this->job );
        }
    }

    public function getStats() {
        return $this->stats ;
    }

    public function getUnchangebleSegments() {
        return $this->unchangeble_segments ;
    }

    public function anyChangeableSegmentStatus() {
        if ( $this->changeable_segments === null ) {
            $this->unchangeble_segments = Translations_SegmentTranslationDao::getUnchangebleStatus( $this->segment_ids, $this->destination_status  );
            $this->changeable_segments = array_diff( $this->segment_ids, $this->unchangeble_segments  );
        }
        return !empty( $this->changeable_segments ) ;
    }

    protected function _statusIsValid() {
        return in_array(
                $this->destination_status, [
                        Constants_TranslationStatus::STATUS_TRANSLATED,
                        Constants_TranslationStatus::STATUS_APPROVED
                ] );
    }
}