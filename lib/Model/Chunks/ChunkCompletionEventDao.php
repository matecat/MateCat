<?php

use Features\ProjectCompletion\CompletionEventStruct;

class Chunks_ChunkCompletionEventDao extends DataAccess_AbstractDao {

    const REVISE = 'revise' ;
    const TRANSLATE = 'translate';

    public static function validSources() {
        return array(
            'user' => Chunks_ChunkCompletionEventStruct::SOURCE_USER,
            'merge' => Chunks_ChunkCompletionEventStruct::SOURCE_MERGE
        );
    }

    public function deleteEvent( Chunks_ChunkCompletionEventStruct $event ) {
        $sql = "DELETE FROM chunk_completion_events WHERE id = :id_event ";
        $stmt = $this->database->getConnection()->prepare( $sql ) ;

        $stmt->execute( ['id_event' => $event->id ] ) ;
        return $stmt->rowCount();
    }

    public function getByIdAndChunk($id_event, Chunks_ChunkStruct $chunk) {
        $sql = "SELECT * FROM chunk_completion_events WHERE id = :id_event
               AND id_job = :id_job AND password = :password " ;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode(PDO::FETCH_CLASS, '\Chunks_ChunkCompletionEventStruct');

        $stmt->execute(array(
            'id_event' => $id_event,
            'password' => $chunk->password,
            'id_job'   => $chunk->id
        ));

        return $stmt->fetch();
    }

    public function updatePassword($id_job, $password, $old_password) {
        $sql = "UPDATE chunk_completion_events SET password = :new_password
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

    /**
     * @param       $chunk
     * @param array $params
     *
     * @return string
     */
    public static function createFromChunk( $chunk, CompletionEventStruct $params ) {
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
            'source'            => $validSources[ $params->source ],
            'create_date'       => Utils::mysqlTimestamp(time()),
            'remote_ip_address' => $params->remote_ip_address,
            'uid'               => $params->uid,
            'is_review'         => $params->is_review
        ));

        return $conn->lastInsertId() ;
    }


    public function currentPhase(Chunks_ChunkStruct $chunk) {
        $lastTranslate = $this->lastCompletionRecord( $chunk, array('is_review' => false) );
        if ( $lastTranslate ) {
            $lastRevise = $this->lastCompletionRecord( $chunk, array('is_review' => true ));
            if ( $lastRevise && new DateTime($lastTranslate['create_date']) < new DateTime( $lastRevise['create_date']) ) {
                return self::TRANSLATE ;
            } else {
                return self::REVISE ;
            }
        }
        return self::TRANSLATE ;
    }

    /**
     *
     * Returns true or false if the chunk is completed. Requires 'is_review' to be passed
     * as a param.
     *
     * A chunk is completed when there is at least one completion event which is more recent
     * than a record on updates table.
     *
     * chunk_completion_events stores the event of completion. A record there means the job
     * was marked as complete.
     *
     * chunk_completion_updates stores the last time a job was updated and is updated with a
     * timestamp every time an invalidating change is done to the job, like a translation.
     *
     * @param $chunk  chunk to examinate
     * @param $params list of params for query: is_review
     *
     * @return array
     *
     * @throws Exception
     */
    public static function lastCompletionRecord( Chunks_ChunkStruct $chunk, array $params = array() ) {
        $params = Utils::ensure_keys($params, array('is_review'));
        $is_review = $params['is_review'];

        /**
         * This query takes into account the fact that completion records are never deleted.
         * We order by event.create_date DESC and then group by id_job, password, is_review
         * so to only get the most recent event record that matches the condition.
         *
         */
        $sql = "
            SELECT events.id AS id_event, events.id_job, events.password, events.is_review, events.create_date
            FROM chunk_completion_events events 
            LEFT JOIN chunk_completion_updates updates on events.id_job = updates.id_job 
            AND  events.password = updates.password and events.is_review = updates.is_review 
            WHERE events.create_date IS NOT NULL  
            AND ( events.create_date > updates.last_translation_at OR updates.last_translation_at IS NULL ) 
            AND events.is_review = :is_review 
            AND events.id_job = :id_job AND events.password = :password 
            ORDER BY events.create_date DESC 
            LIMIT 1
            " ;

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                        'id_job'    => $chunk->id,
                        'password'  => $chunk->password,
                        'is_review' => $is_review
                ]
        );

        // TODO: change this returned object to be a Struct
        return $stmt->fetch();
    }

    public static function isChunkCompleted( Chunks_ChunkStruct $chunk, array $params = array() ) {
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
