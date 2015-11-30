<?php

class Jobs_JobDao extends DataAccess_AbstractDao {

    public static function getChunks() {
    }

    public static function getByProjectId( $id_project ) {
        $conn = Database::obtain()->getConnection();
        // TODO: this query should return the minimal data
        // required for the JobStruct class, to not be confused
        // with the ChunkStruct class.
        // Ideally it should return:
        // - id_project
        // - id
        // - source
        // - target
        //
        // about job_first_segment and job_last_segment it should return
        // the whole segments interval.
        // Extend this query considering that fields not defined in the
        // JobStruct class will be ignored.
        $stmt = $conn->prepare(
                "SELECT * FROM ( " .
                " SELECT * FROM jobs " .
                " WHERE id_project = ? " .
                " ORDER BY id DESC ) t GROUP BY id ; " );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Jobs_JobStruct' );
        $stmt->execute( array( $id_project ) );

        return $stmt->fetchAll();
    }

    protected function _buildResult( $array_result ) {

    }

}
