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
     *
     * Returns true or false if the chunk is completed. Requires 'is_review' to be passed
     * as a param.
     *
     * A chunk is completed when there is at least one completion event which is more recent
     * than a record un updates table.
     *
     * @param $chunk chunk to examinate
     * @param $params list of params for query: is_review
     *
     * @return true|false
     *
     */

    public static function lastCompletionRecord( Chunks_ChunkStruct $chunk, array $params = array() ) {
        $params = Utils::ensure_keys($params, array('is_review'));
        $is_review = $params['is_review'] || false;

        $sql = "SELECT events.uid, events.create_date, events.is_review, events.id_job, updates.password " .
            " FROM chunk_completion_events events " .
            " LEFT JOIN chunk_completion_updates updates on events.id_job = updates.id_job " .
            " AND  events.password = updates.password and events.is_review = updates.is_review " .
            " WHERE events.create_date IS NOT NULL  " .
            " AND ( events.create_date > updates.last_translation_at OR updates.last_translation_at IS NULL ) " .
            " AND events.is_review = :is_review " .
            " AND events.id_job = :id_job AND events.password = :password " .
            " GROUP BY id_job, password, is_review, create_date" ;

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( array(
                        'id_job'    => $chunk->id,
                        'password'  => $chunk->password,
                        'is_review' => $is_review
                )
        );

        // TODO: change this returned object to be a Struct
        return $stmt->fetch();
    }

    public function isChunkCompleted( Chunks_ChunkStruct $chunk, array $params = array() ) {
        $fetched = self::lastCompletionRecord( $chunk, $params);
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
