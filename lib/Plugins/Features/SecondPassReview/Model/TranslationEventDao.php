<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 02/05/2019
 * Time: 11:19
 */

namespace Features\SecondPassReview\Model;


class TranslationEventDao extends \Features\TranslationVersions\Model\TranslationEventDao {

    public function unsetFinalRevisionFlag($id_job, $id_segments, $source_pages) {

        $sql = " UPDATE segment_translation_events SET final_revision = 0 " .
                " WHERE id_job = :id_job " .
                " AND id_segment IN ( " . implode(',', $id_segments ) . " ) " .
                " AND source_page IN ( " . implode(',', $source_pages ) . " ) " ;

        $conn = $this->getDatabaseHandler()->getConnection() ;
        $stmt = $conn->prepare( $sql );

        $stmt->execute( [
                'id_job' => $id_job,
        ] ) ;

        return $stmt->rowCount() ;
    }

}