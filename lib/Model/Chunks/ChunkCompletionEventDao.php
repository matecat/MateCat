<?php

class Chunks_ChunkCompletionEventDao extends DataAccess_AbstractDao {

    public static function validSources() {
        return array(
            'user' => Chunks_ChunkCompletionEventStruct::SOURCE_USER,
            'merge' => Chunks_ChunkCompletionEventStruct::SOURCE_MERGE
        );
    }

    public static function createFromChunk( $chunk, $params ) {
        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare("INSERT INTO chunk_completion_events " .
            " ( " .
            " id_job, password, job_first_segment, job_last_segment, " .
            " source, create_date, remote_ip_address, uid " .
            " ) VALUES ( " .
            " :id_job, :password, :job_first_segment, :job_last_segment, " .
            " :source, :create_date, :remote_ip_address, :uid " .
            " ); ");

        $stmt->execute( array(
            'id_job'            => $chunk->id,
            'password'          => $chunk->password,
            'job_first_segment' => $chunk->job_first_segment,
            'job_last_segment'  => $chunk->job_last_segment,
            'source'            => self::validSources()[ $params['source'] ],
            'create_date'       => date('Y-m-d H:i:s'),
            'remote_ip_address' => $params['remote_ip_address'],
            'uid'               => $params['uid']
        ));
    }

    public static function isCompleted( $chunk ) {
        // find the latest translation date for this chunk
        // if no date is returned then the chunk cannot be completed.
        $dao = new Translations_SegmentTranslationDao( Database::obtain() );
        $latestTranslation =  $dao->lastTranslationByJobOrChunk( $chunk );
        Log::doLog('latestTranslation', $latestTranslation);

        if ( $latestTranslation === false ) return false;

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare("SELECT * FROM chunk_completion_events " .
            " WHERE id_job = :id_job AND password = :password " .
            " AND job_first_segment = :job_first_segment " .
            " AND job_last_segment = :job_last_segment " .
            " AND create_date >= :latest_translation_at " );

        $stmt->setFetchMode(PDO::FETCH_CLASS, 'Chunks_ChunkCompletionEventStruct');

        $stmt->execute( array(
            'id_job' => $chunk->id,
            'password' => $chunk->password,
            'job_first_segment' => $chunk->job_first_segment,
            'job_last_segment' => $chunk->job_last_segment,
            'latest_translation_at' => $latestTranslation->translation_date
        ));

        $fetched = $stmt->fetch();
        return $fetched != false ;
    }

    protected function _buildResult( $array_result ) {
    }
}
