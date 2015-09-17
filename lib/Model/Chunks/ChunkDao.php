<?php

class Chunks_ChunkDao extends DataAccess_AbstractDao {

    function getByProjectID( $id_project ) {
        $conn = $this->con->getConnection();
        $stmt = $conn->prepare("SELECT * FROM " .
            "jobs WHERE id_project = ?");

        $stmt->execute(array( $id_project ));
        return $stmt->fetchAll( PDO::FETCH_CLASS, 'Chunks_ChunkStruct' ) ;
    }

    protected function _buildResult( $array_result ) {
        // TODO
        // $result = array();
        // foreach($array_result as $record) {
        //     $result[] = new Chunks_ChunkStruct( $record );
        // }

        // return $result;
    }

}
