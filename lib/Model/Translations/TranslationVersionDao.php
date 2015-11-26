<?php

class Translations_TranslationVersionDao extends DataAccess_AbstractDao {
    public $source_page ;
    public $uid ;

    protected function _buildResult( $array_result ) {
    }

    public static function getVersionsForJob($id_job) {
        $sql = "SELECT * FROM segment_translation_versions " .
            " WHERE id_job = :id_job " .
            " ORDER BY creation_date DESC ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql );

        $stmt->execute(
            array( 'id_job' => $id_job )
        );

        $stmt->setFetchMode(
            PDO::FETCH_CLASS,
            'Segments_SegmentTranslationVersionStruct'
        );

        return $stmt->fetchAll();
    }


    public static function getVersionsForTranslation($id_job, $id_segment) {
        $sql = "SELECT * FROM segment_translation_versions " .
            " WHERE id_job = :id_job AND id_segment = :id_segment " .
            " ORDER BY creation_date DESC ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql );

        $stmt->execute(
            array( 'id_job' => $id_job, 'id_segment' => $id_segment )
        );

        $stmt->setFetchMode(
            PDO::FETCH_CLASS,
            'Segments_SegmentTranslationVersionStruct'
        );

        return $stmt->fetchAll();
    }

    public function savePropagation($propagation, $id_segment, $job_data, $propagateToTranslated) {

        $st_approved   = Constants_TranslationStatus::STATUS_APPROVED;
        $st_rejected   = Constants_TranslationStatus::STATUS_REJECTED;
        $st_translated = Constants_TranslationStatus::STATUS_TRANSLATED;
        $st_new        = Constants_TranslationStatus::STATUS_NEW;
        $st_draft      = Constants_TranslationStatus::STATUS_DRAFT;

        if ( $propagateToTranslated ) {
            $status_condition = "AND status IN (
                '$st_draft',
                '$st_new',
                '$st_translated',
                '$st_approved',
                '$st_rejected' ) " ;
        } else {
            $status_condition = '';
        }

        /**
         * This query makes and insert while reading from segment_translations.
         * This is done to avoid roundtrips between MySQL and PHP.
         */

        $sql = "INSERT INTO segment_translation_versions " .
            " ( " .
            " id_job, id_segment, replaced_translation, uid, source_page, " .
            " propagated_from " .
            " ) " .
            " SELECT id_job, id_segment, translation, :uid, :source_page, " .
            " :propagated_from " .
            " FROM segment_translations " .
            " WHERE " .
            " id_job = :id_job AND " .
            " segment_hash = :segment_hash AND " .
            " id_segment != :id_segment AND " .
            " id_segment BETWEEN :first_segment AND :last_segment " .
            " $status_condition " ;

            Log::doLog( $sql );

            $options =  array(
                'id_job'        => $job_data['id'],
                'id_segment'    => $id_segment,
                'uid'           => $this->uid,
                'source_page'   => $this->source_page,
                'first_segment' => $job_data['job_first_segment'],
                'last_segment'  => $job_data['job_last_segment'],
                'segment_hash'  => $propagation['segment_hash'],
                'propagated_from' => $propagation['autopropagated_from']
            );

            Log::doLog( $options ) ;

            Log::doLog( $job_data );
            Log::doLog( $propagation );

            $conn = Database::obtain()->getConnection();
            $stmt = $conn->prepare( $sql );

            $stmt->execute(  $options );

    }

    public function saveVersion($old_translation) {

        $sql = "INSERT INTO segment_translation_versions " .
            " ( id_job, id_segment, replaced_translation, uid, source_page ) " .
            " VALUES " .
            " (:id_job, :id_segment, :replaced_translation, :uid, :source_page) ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql );
        $stmt->execute( array(
            'id_job'               => $old_translation['id_job'],
            'id_segment'           => $old_translation['id_segment'] ,
            'replaced_translation' => $old_translation['translation'],
            'uid'                  => $this->uid,
            'source_page'          => $this->source_page
        ));
    }


}
