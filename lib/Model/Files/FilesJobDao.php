<?php

namespace Model\Files;

use Database;
use Model\DataAccess\AbstractDao;
use Model\Jobs\JobStruct;
use PDO;

class FilesJobDao extends AbstractDao {
    const TABLE = 'files_job';

    /**
     * @param FileStruct            $file
     * @param \Model\Jobs\JobStruct $chunk
     *
     * @return array
     */
    public function getSegmentBoundariesForChunk( FileStruct $file, JobStruct $chunk ) {
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

        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( $sql );

        $stmt->setFetchMode( PDO::FETCH_ASSOC );

        $stmt->execute( [
                'id_job'   => $chunk->id,
                'password' => $chunk->password,
                'id_file'  => $file->id
        ] );

        $record = $stmt->fetch();

        return [ $record[ 'MIN' ], $record[ 'MAX' ] ];
    }

}