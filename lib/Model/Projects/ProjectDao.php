<?php

class Projects_ProjectDao extends DataAccess_AbstractDao {
    const TABLE = "projects";

    /**
     *
     */

    public function updateField( $project, $field, $value ) {
        $sql = "UPDATE projects SET $field = :value " .
            " WHERE id = :id ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        return $stmt->execute( array(
            'value' => $value,
            'id' => $project->id
        ));
    }

    /**
     * @param int $id_job
     * @return Projects_ProjectStruct
     */
    static function findByJobId( $id_job ) {
        $conn = Database::obtain()->getConnection();
        $sql = "SELECT projects.* FROM projects " .
            " INNER JOIN jobs ON projects.id = jobs.id_project " .
            " WHERE jobs.id = :id_job " .
            " LIMIT 1 " ;
        $stmt = $conn->prepare( $sql );
        $stmt->execute( array('id_job' => $id_job ) );
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Projects_ProjectStruct' );
        return $stmt->fetch();
    }

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

    /**
     * Returns uncompleted chunks by project ID. Requires 'is_review' to be passed
     * as a param to filter the query.
     *
     * @return Chunks_ChunkStruct[]
     *
     */
    static function uncompletedChunksByProjectId( $id_project, $params=array() ) {
        $params = Utils::ensure_keys($params, array('is_review'));
        $is_review = $params['is_review'] || false;

        $sql = "SELECT j.* FROM jobs j LEFT JOIN ( " .
                " SELECT c.is_review, c.id_job, cc.password " .
                " FROM chunk_completion_events c " .
                " LEFT JOIN chunk_completion_updates cc on c.id_job = cc.id_job " .
                    " AND  c.password = cc.password AND cc.is_review = c.is_review " .
                " WHERE ( c.create_date > cc.last_translation_at OR cc.last_translation_at IS NULL ) " .
                " AND c.is_review = :is_review " .
                " AND c.id_project = :id_project " .
                " GROUP BY id_job, password, is_review " .
                " ) jj ON jj.id_job = j.id AND jj.password = j.password " .
            " WHERE j.id_project = :id_project AND jj.id_job IS NULL ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( array(
            'is_review' => $is_review,
            'id_project' => $id_project
        ) );

        $stmt->setFetchMode(PDO::FETCH_CLASS, 'Chunks_ChunkStruct');

        return $stmt->fetchAll();

    }

    protected function _buildResult( $array_result ) {

    }
}
