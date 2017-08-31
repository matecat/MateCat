<?php

namespace Files ;

use Chunks_ChunkStruct;
use DataAccess_AbstractDao;
use Database;
use Files_FileStruct;
use PDO;

class FilesJobDao extends  DataAccess_AbstractDao {
    const TABLE = 'files_job';

    /**
     * @param Files_FileStruct   $file
     * @param Chunks_ChunkStruct $chunk
     *
     * @return array
     */
    public function getSegmentBoundariesForChunk( Files_FileStruct $file, Chunks_ChunkStruct $chunk ) {
        // TODO: if the conditions on this query are efficient enough

        $sql = "SELECT MIN(segments.id) AS MIN, MAX(segments.id) as MAX
          FROM files_job
            JOIN jobs
              ON jobs.id = files_job.id_job
                AND files_job.id_file = :id_file
                AND password = :password
            JOIN segments
              ON segments.id_file = files_job.id_file
                AND id_job = :id_job " ;

        $conn = Database::obtain()->getConnection() ;

        $stmt = $conn->prepare( $sql ) ;

        $stmt->setFetchMode( PDO::FETCH_ASSOC ) ;

        $stmt->execute([
            'id_job'   => $chunk->id,
            'password' => $chunk->password,
            'id_file'  => $file->id
        ]) ;

        $record = $stmt->fetch() ;

        return [ $record['MIN'], $record['MAX'] ] ;
    }

}