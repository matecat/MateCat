<?php

class Projects_ProjectDao extends DataAccess_AbstractDao {
    const TABLE = "projects";

    static function findById( $id ) {
       $conn = Database::obtain()->getConnection();
       $stmt = $conn->prepare( "SELECT * FROM projects WHERE id = ?");
       $stmt->execute( array( $id ) );
       return $stmt->fetchAll( PDO::FETCH_CLASS, 'Projects_ProjectStruct')[0];
    }

    public function getChunks( ) {
        // TODO
    }

    protected function _buildResult( $array_result ) {

    }
}
