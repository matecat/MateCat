<?php

class Files_FileDao extends DataAccess_AbstractDao {

    function getByProjectId( $id_project ) {
        $conn = $this->con->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM files where id_project = ? ");
        $stmt->execute( array( $id_project ) );
        return $stmt->fetchAll();
    }

    function _buildResult( $array_result ) {
        return null;
    }

}
