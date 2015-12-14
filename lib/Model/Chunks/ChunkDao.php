<?php

class Chunks_ChunkDao extends DataAccess_AbstractDao {

    public static function getByIdAndPassword( $id, $password ) {
        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare(
                "SELECT * FROM jobs WHERE id = :id_job " .
                " AND password = :password "
        );

        $params = array(
                ':id_job'   => $id,
                ':password' => $password
        );

        $stmt->execute( $params );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Chunks_ChunkStruct' );
        $fetched = $stmt->fetch();

        if ( $fetched == false ) {
            throw new Exceptions_RecordNotFound();
        } else {
            return $fetched;
        }

    }

    public function getByProjectID( $id_project ) {
        $conn = $this->con->getConnection();
        $stmt = $conn->prepare("SELECT * FROM " .
            "jobs WHERE id_project = ?");

        $stmt->execute(array( $id_project ));
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'Chunks_ChunkStruct');
        return $stmt->fetchAll( ) ;
    }

    protected function _buildResult( $array_result ) {
        // TODO
         $result = array();
         foreach($array_result as $record) {
             $result[] = new Chunks_ChunkStruct( $record );
         }

        // return $result;
    }

}
