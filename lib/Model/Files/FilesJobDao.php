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
        $sql = "SELECT MIN(st.id_segment) AS MIN, MAX(st.id_segment) as MAX
          FROM files_job
            JOIN jobs
              ON jobs.id = files_job.id_job
                AND files_job.id_file = :id_file
                AND password = :password

            JOIN segment_translations st
              ON st.id_segment
                BETWEEN jobs.job_first_segment AND jobs.job_last_segment
                AND jobs.id  = :id_job
          ";

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