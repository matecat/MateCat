<?php

namespace LQA ;

use \CatUtils ;

class ChunkReviewDao extends \DataAccess_AbstractDao {

    const TABLE = "qa_chunk_reviews";

    public static $primary_keys = array(
        'id'
    );

    protected function _buildResult( $array_result ) {

    }

    public static function findById( $id ) {
        $sql = "SELECT * FROM qa_chunk_reviews " .
            " WHERE id = :id ";
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute(array('id' => $id ));
        return $stmt->fetch();

    }

    public static function findChunkReviewsByChunkIds( $chunk_ids ) {
        $conditions = array_map( function($ids) {
            return " ( jobs.id = " . $ids[0] .
                " AND jobs.password = '" . $ids[1] . "' ) ";
        }, $chunk_ids );

        $sql = "SELECT qa_chunk_reviews.* " .
            " FROM jobs INNER JOIN qa_chunk_reviews ON " .
            " jobs.id = qa_chunk_reviews.id_job AND " .
            " jobs.password = qa_chunk_reviews.password " .
            " WHERE  " . implode( ' OR ', $conditions ) ;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @return ChunkReviewStruct
     */

    public static function findByProjectId( $id_project ) {
        $sql = "SELECT * FROM qa_chunk_reviews " .
            " WHERE id_project = :id_project ";
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute(array('id_project' => $id_project));
        return $stmt->fetchAll() ;
    }

    /**
     * @return ChunkReviewStruct
     */

    public static function findByReviewPasswordAndJobId( $review_password, $id_job ) {
        $sql = "SELECT * FROM qa_chunk_reviews " .
            " WHERE review_password = :review_password " .
            " AND id_job = :id_job " ;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute(
            array(
                'review_password' => $review_password,
                'id_job'          => $id_job
            )
        );
        return $stmt->fetch() ;

    }

    /**
     * @return ChunkReviewStruct
     * @param $data array of data to use
     */
    public static function createRecord( $data ) {
        $struct = new \LQA\ChunkReviewStruct( $data );

        $struct->ensureValid();
        $struct->review_password = CatUtils::generate_password( 12 );
        $attrs = $struct->attributes(array(
            'id_project', 'id_job', 'password', 'review_password'
        ));

        // TODO: refactor the following two lines
        $sql = "INSERT INTO " . self::TABLE .
            " ( id_project, id_job, password, review_password ) " .
            " VALUES " .
            " ( :id_project, :id_job, :password, :review_password ) ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( $attrs );

        $lastId = $conn->lastInsertId();
        return self::findById( $lastId );
    }

    public static function deleteByJobId($id_job) {
        $sql = "DELETE FROM qa_chunk_reviews " .
            " WHERE id_job = :id_job " ;
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        return $stmt->execute( array('id_job' => $id_job ) ) ;
    }

}
