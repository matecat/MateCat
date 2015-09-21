<?php

class Projects_ProjectDao extends DataAccess_AbstractDao {
    const TABLE = "projects";

    static function findById( $id ) {
       $conn = Database::obtain()->getConnection();
       $stmt = $conn->prepare( "SELECT * FROM projects WHERE id = ?");
       $stmt->execute( array( $id ) );
       $stmt->setFetchMode(PDO::FETCH_CLASS, 'Projects_ProjectStruct');
       return $stmt->fetch();
    }

    public function getChunks( ) {
        // TODO
    }

    protected function _buildResult( $array_result ) {

    }
}
