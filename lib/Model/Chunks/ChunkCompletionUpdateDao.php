<?php

use DataAccess\AbstractDao;

class Chunks_ChunkCompletionUpdateDao extends AbstractDao {

    protected function _buildResult( array $array_result ) {
    }

    public function updatePassword( $id_job, $password, $old_password ) {
        $sql = "UPDATE chunk_completion_updates SET password = :new_password
               WHERE id_job = :id_job AND password = :password ";

        $conn = \Database::obtain()->getConnection();
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
                'user'  => Chunks_ChunkCompletionEventStruct::SOURCE_USER,
                'merge' => Chunks_ChunkCompletionEventStruct::SOURCE_MERGE
        ];
    }

    public static function findByChunk( Jobs_JobStruct $chunk, array $params = [] ) {

        $sql = "SELECT * FROM chunk_completion_updates " .
                " WHERE id_project = :id_project AND id_job = :id_job " .
                " AND password = :password AND job_first_segment = :job_first_segment " .
                " AND job_last_segment = :job_last_segment ";

        $data = [
                'id_project'        => $chunk->getProject()->id,
                'id_job'            => $chunk->id,
                'password'          => $chunk->password,
                'job_first_segment' => $chunk->job_first_segment,
                'job_last_segment'  => $chunk->job_last_segment
        ];

        $conditions = [];

        if ( $params[ 'is_review' ] != null ) {
            $conditions[]        = " AND is_review = :is_review ";
            $data[ 'is_review' ] = $params[ 'is_review' ];
        }

        $sql = $sql . implode( $conditions );

        \Log::doJsonLog( $sql );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Chunks_ChunkCompletionUpdateStruct' );
        $stmt->execute( $data );

        return $stmt->fetch();
    }

    public static function createOrUpdateFromStruct(
            Chunks_ChunkCompletionUpdateStruct $struct, array $params = [] ) {

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
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Chunks_ChunkCompletionUpdateStruct' );

        $data = $struct->toArray( [
                'id_project', 'id_job', 'password', 'job_first_segment', 'job_last_segment',
                'source', 'uid', 'is_review', 'last_translation_at'
        ] );

        return $stmt->execute( $data );
    }

}
