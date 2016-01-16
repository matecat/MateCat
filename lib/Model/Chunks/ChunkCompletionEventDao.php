<?php

class Chunks_ChunkCompletionEventDao extends DataAccess_AbstractDao {

    public static function validSources() {
        return array(
            'user' => Chunks_ChunkCompletionEventStruct::SOURCE_USER,
            'merge' => Chunks_ChunkCompletionEventStruct::SOURCE_MERGE
        );
    }

    public static function createFromChunk( $chunk, array $params ) {
        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare("INSERT INTO chunk_completion_events " .
            " ( " .
            " id_project, id_job, password, job_first_segment, job_last_segment, " .
            " source, create_date, remote_ip_address, uid, is_review " .
            " ) VALUES ( " .
            " :id_project, :id_job, :password, :job_first_segment, :job_last_segment, " .
            " :source, :create_date, :remote_ip_address, :uid, :is_review " .
            " ); ");

        $validSources = self::validSources() ;
        $stmt->execute( array(
            'id_project'        => $chunk->getProject()->id,
            'id_job'            => $chunk->id,
            'password'          => $chunk->password,
            'job_first_segment' => $chunk->job_first_segment,
            'job_last_segment'  => $chunk->job_last_segment,
            'source'            => $validSources[ $params['source'] ],
            'create_date'       => date('Y-m-d H:i:s'),
            'remote_ip_address' => $params['remote_ip_address'],
            'uid'               => $params['uid'],
            'is_review'         => $params['is_review']
        ));
    }

    /**
     * @return true|false
     *
     */

    public static function isChunkCompleted( Chunks_ChunkStruct $chunk, array $params = array() ) {
        $is_review = $params['is_review'] || false;

        $sql = "SELECT c.is_review, c.id_job, cc.password " .
            " FROM chunk_completion_events c " .
            " LEFT JOIN chunk_completion_updates cc on c.id_job = cc.id_job " .
            " AND  c.password = cc.password and cc.is_review = c.is_review " .
            " WHERE ( c.create_date > cc.last_translation_at OR cc.last_translation_at IS NULL ) " .
            " AND c.is_review = :is_review " .
            " GROUP BY id_job, password, is_review " ;

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( array( 'is_review' => $is_review ) );

        $fetched = $stmt->fetch();
        return $fetched != false ;
    }

    public static function isProjectCompleted( Projects_ProjectStruct $proj ) {
        $uncompletedChunksByProjectId = Projects_ProjectDao::uncompletedChunksByProjectId( $proj->id );
        return $uncompletedChunksByProjectId == false;
    }

    public static function isCompleted( $obj, array $params = array() ) {
        if ( $obj instanceof Chunks_ChunkStruct ) {
            return self::isChunkCompleted( $obj, $params );
        } elseif ($obj instanceof Projects_ProjectStruct) {
            return self::isProjectCompleted( $obj ) ;
        } else {
            throw new Exception( "Not a supported type" );
        }
    }

    protected function _buildResult( $array_result ) { }
}
