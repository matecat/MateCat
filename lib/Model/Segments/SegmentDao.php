<?php

class Segments_SegmentDao extends DataAccess_AbstractDao {


    /**
     * @param Chunks_ChunkStruct $chunk
     * @return mixed
     */
    function countByChunk( Chunks_ChunkStruct $chunk) {
        $conn = $this->con->getConnection();
        $query = "SELECT COUNT(1) FROM segments s
            JOIN segment_translations st ON s.id = st.id_segment
            JOIN jobs ON st.id_job = jobs.id
            WHERE jobs.id = :id_job
            AND jobs.password = :password
            AND s.show_in_cattool ;
            "  ;
        $stmt = $conn->prepare( $query ) ;
        $stmt->execute( array( 'id_job' => $chunk->id, 'password' => $chunk->password ) ) ;
        $result = $stmt->fetch() ;
        return (int) $result[ 0 ] ;
    }

    /**
     * @param $id_job
     * @param $password
     * @param $id_segment
     * @return \Segments_SegmentStruct
     */
    function getByChunkIdAndSegmentId( $id_job, $password, $id_segment) {
        $conn = $this->con->getConnection();

        $query = " SELECT segments.* FROM segments " .
                " INNER JOIN files_job fj USING (id_file) " .
                " INNER JOIN jobs ON jobs.id = fj.id_job " .
                " INNER JOIN files f ON f.id = fj.id_file " .
                " WHERE jobs.id = :id_job AND jobs.password = :password" .
                " AND segments.id_file = f.id " .
                " AND segments.id = :id_segment " ;

        $stmt = $conn->prepare( $query );

        $stmt->execute( array(
                'id_job'   => $id_job,
                'password' => $password,
                'id_segment'=> $id_segment
        ) );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Segments_SegmentStruct' );

        return $stmt->fetch();
    }

    /**
     * @param $id_job
     * @param $password
     *
     * @return Segments_SegmentStruct[]
     */
    function getByChunkId( $id_job, $password ) {
        $conn = $this->con->getConnection();

        $query = "SELECT segments.* FROM segments
                 INNER JOIN files_job fj USING (id_file)
                 INNER JOIN jobs ON jobs.id = fj.id_job
                 AND jobs.id = :id_job AND jobs.password = :password
                 INNER JOIN files f ON f.id = fj.id_file
                 WHERE jobs.id = :id_job AND jobs.password = :password
                 AND segments.id_file = f.id
                 AND segments.id BETWEEN jobs.job_first_segment AND jobs.job_last_segment
                 ";

        $stmt = $conn->prepare( $query );

        $stmt->execute( array(
                'id_job'   => $id_job,
                'password' => $password
        ) );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Segments_SegmentStruct' );

        return $stmt->fetchAll();
    }

    /**
     * @param $id_segment
     *
     * @return Segments_SegmentStruct
     */
    public function getById( $id_segment ) {
        $conn = $this->con->getConnection();

        $query = "select * from segments where id = :id";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( array( 'id' => (int)$id_segment ) );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Segments_SegmentStruct' );

        return $stmt->fetch();
    }

    protected function _buildResult( $array_result ) {
        // XXX: deprecated?

    }

}
