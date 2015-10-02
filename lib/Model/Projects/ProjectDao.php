<?php

class Projects_ProjectDao extends DataAccess_AbstractDao {
    const TABLE = "projects";

    /**
     * @param $id
     * @return Projects_ProjectStruct
     */
    static function findById( $id ) {
       $conn = Database::obtain()->getConnection();
       $stmt = $conn->prepare( "SELECT * FROM projects WHERE id = ?");
       $stmt->execute( array( $id ) );
       $stmt->setFetchMode(PDO::FETCH_CLASS, 'Projects_ProjectStruct');
       return $stmt->fetch();
    }

    static function getFilesByProjectId( $id_project ) {
        $conn = Database::obtain()->getConnection();
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'Files_FileStruct');
    }

    static function uncompletedChunksByProjectId( $id_project ) {
        // for each project you can have several jobs, one per targert language.
        // for each job you can have one or more chunks.
        // jobs are identified by id_job and target language.
        // chunks are identified by id_job and password.
        //
        // translations have a reference to job, not to the chunk.
        // in order to associate the segment_translation to the chunk we need to
        // refer to the start and stop segment stored on the job.
        //
        // I would be great if we could have a chunk identifier on the segment_translation.
        // segments don't have a reference to the job neither, since they are linked to the file.
        //
        $query_most_recent_completion_events_for_chunk = " " .
            " SELECT * FROM ( " .
            " SELECT * FROM chunk_completion_events WHERE id_project = :id_project "  .
            " ORDER BY create_date DESC ) t " .
            " GROUP BY id_project, id_job, password " ;

        // This query should return no records, meaning all submitted events have
        // create_date greater than the chunk's latest translation date.
        $query_for_event_submitted_at_least_once = "SELECT ch.id_job, ch.password " .
            " FROM segment_translations st INNER JOIN " .
            "( $query_most_recent_completion_events_for_chunk ) ch ON " .
            " st.id_segment BETWEEN ch.job_first_segment AND ch.job_last_segment " .
            " AND st.id_job = ch.id_job AND ch.id_project = :id_project " .
            " AND ch.create_date < st.translation_date" ;

        // This query should return no records, meaning all jobs have at least one
        // submitted chunk completion event.
        $query_to_return_unsubmitted_chunks = "SELECT jobs.id as id_job, jobs.password " .
            " FROM jobs LEFT JOIN chunk_completion_events ch ON " .
            " jobs.id = ch.id_job AND " .
            " jobs.password = ch.password AND " .
            " jobs.job_first_segment = ch.job_first_segment AND " .
            " jobs.job_last_segment = ch.job_last_segment AND " .
            " jobs.id_project = ch.id_project " .
            " WHERE jobs.id_project = :id_project " .
            " AND ch.id IS NULL " ;

        $union_query = "SELECT * FROM ( $query_to_return_unsubmitted_chunks " .
            " UNION ALL $query_for_event_submitted_at_least_once ) t1 " .
            " GROUP BY id_job, password " ;

        $query_to_return_chunks = "SELECT * from jobs INNER JOIN ( $union_query ) filtered " .
            " ON jobs.id = filtered.id_job AND jobs.password = filtered.password " ;

        $conn = Database::obtain()->getConnection();
        Log::doLog( $query_to_return_chunks );
        $stmt = $conn->prepare( $query_to_return_chunks ) ;
        $stmt->execute( array('id_project' => $id_project ) );
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'Chunks_ChunkStruct');

        return $stmt->fetchAll();
    }

    protected function _buildResult( $array_result ) {

    }
}
