<?php

namespace Model\ChunksCompletion;

use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use PDO;

class ChunkCompletionUpdateDao extends AbstractDao {

    protected function _buildResult( array $array_result ) {
    }

    public function updatePassword( $id_job, $password, $old_password ) {
        $sql = "UPDATE chunk_completion_updates SET password = :new_password
               WHERE id_job = :id_job AND password = :password ";

        $conn = \Model\DataAccess\Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_job'       => $id_job,
                'password'     => $old_password,
                'new_password' => $password
        ] );

        return $stmt->rowCount();
    }

    public static function validSources() {
        return [
                'user'  => ChunkCompletionEventStruct::SOURCE_USER,
                'merge' => ChunkCompletionEventStruct::SOURCE_MERGE
        ];
    }

    public static function createOrUpdateFromStruct(
            ChunkCompletionUpdateStruct $struct, array $params = [] ) {

        $sql_update = "  " .
                " last_update = CURRENT_TIMESTAMP, source = :source, uid = :uid, " .
                " is_review = :is_review, last_translation_at = :last_translation_at ";

        $sql = "INSERT INTO chunk_completion_updates " .
                " ( " .
                " id_project, id_job, password, job_first_segment, job_last_segment, " .
                " source, uid, is_review, last_translation_at, create_date " .
                " ) " .
                " VALUES " .
                " ( " .
                " :id_project, :id_job, :password, :job_first_segment, :job_last_segment, " .
                " :source, :uid, :is_review, :last_translation_at, CURRENT_TIMESTAMP " .
                " ) " .
                " ON DUPLICATE KEY UPDATE $sql_update ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, ChunkCompletionUpdateStruct::class );

        $data = $struct->toArray( [
                'id_project', 'id_job', 'password', 'job_first_segment', 'job_last_segment',
                'source', 'uid', 'is_review', 'last_translation_at'
        ] );

        return $stmt->execute( $data );
    }

}
