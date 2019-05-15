<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 03/04/2019
 * Time: 17:59
 */

namespace Features\SecondPassReview\Model;

use Constants;

class ChunkReviewDao extends \Features\ReviewExtended\Model\ChunkReviewDao {

    /**
     * @param array $chunk_ids
     *
     * @return \LQA\ChunkReviewStruct[]
     */
    public static function findSecondRevisionsChunkReviewsByChunkIds( array $chunk_ids ) {
        $source_page = Constants::SOURCE_PAGE_REVISION ;

        $sql_condition = " WHERE source_page > $source_page " ;

        if ( count($chunk_ids)  > 0 ) {
            $conditions = array_map( function($ids) {
                return " ( jobs.id = " . $ids[0] .
                        " AND jobs.password = '" . $ids[1] . "' ) ";
            }, $chunk_ids );
            $sql_condition .=  " AND " . implode( ' OR ', $conditions ) ;
        }

        $sql = "SELECT qa_chunk_reviews.* " .
                " FROM jobs INNER JOIN qa_chunk_reviews ON " .
                " jobs.id = qa_chunk_reviews.id_job AND " .
                " jobs.password = qa_chunk_reviews.password " .
                $sql_condition ;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute();

        return $stmt->fetchAll();
    }


}