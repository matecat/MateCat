<?php

class Chunks_ChunkCompletionUpdateDao extends DataAccess_AbstractDao {

    protected function _buildResult( $array_result ) { }

    public function updatePassword($id_job, $password, $old_password) {
        $sql = "UPDATE chunk_completion_updates SET password = :new_password
               WHERE id_job = :id_job AND password = :password " ;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute(array(
                'id_job'       => $id_job,
                'password'     => $old_password,
                'new_password' => $password
        ));

        return $stmt->rowCount();
    }

    public static function validSources() {
        return array(
            'user' => Chunks_ChunkCompletionEventStruct::SOURCE_USER,
            'merge' => Chunks_ChunkCompletionEventStruct::SOURCE_MERGE
        );
    }

    public static function findByChunk( Chunks_ChunkStruct $chunk, array $params=array()) {

        $sql = "SELECT * FROM chunk_completion_updates " .
            " WHERE id_project = :id_project AND id_job = :id_job " .
            " AND password = :password AND job_first_segment = :job_first_segment " .
            " AND job_last_segment = :job_last_segment " ;

        $data = array(
            'id_project'          => $chunk->getProject()->id,
            'id_job'              => $chunk->id,
            'password'            => $chunk->password,
            'job_first_segment'   => $chunk->job_first_segment,
            'job_last_segment'    => $chunk->job_last_segment
        );

        $conditions = array();

        if ( $params['is_review'] != null ) {
            $conditions[] = " AND is_review = :is_review ";
            $data['is_review'] = $params['is_review'];
        }

        $sql = $sql . implode( $conditions );

        \Log::doJsonLog( $sql );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'Chunks_ChunkCompletionUpdateStruct');
        $stmt->execute( $data );
        return $stmt->fetch();
    }

    public static function createOrUpdateFromStruct(
        Chunks_ChunkCompletionUpdateStruct $struct, array $params=array() ) {

        $sql_update = "  ".
            " last_update = CURRENT_TIMESTAMP, source = :source, uid = :uid, " .
            " is_review = :is_review, last_translation_at = :last_translation_at " ;

        $sql = "INSERT INTO chunk_completion_updates " .
            " ( " .
            " id_project, id_job, password, job_first_segment, job_last_segment, " .
            " source, uid, is_review, last_translation_at, create_date " .
            " ) " .
            " VALUES " .
            " ( " .
            " :id_project, :id_job, :password, :job_first_segment, :job_last_segment, " .
            " :source, :uid, :is_review, :last_translation_at, CURRENT_TIMESTAMP "  .
            " ) " .
            " ON DUPLICATE KEY UPDATE $sql_update " ;

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'Chunks_ChunkCompletionUpdateStruct');

        $data = $struct->toArray(array(
            'id_project', 'id_job', 'password', 'job_first_segment', 'job_last_segment',
            'source', 'uid', 'is_review', 'last_translation_at'
        ));

        return $stmt->execute( $data ) ;
    }

}
