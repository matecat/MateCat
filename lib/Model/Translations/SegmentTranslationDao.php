<?php

class Translations_SegmentTranslationDao extends DataAccess_AbstractDao {

    public function getByJobId($id_job) {
        $conn = $this->con->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM segment_translations " .
            " WHERE id_job = ? " );

        $stmt->execute( array( $id_job ) );

        return $stmt->fetchAll( PDO::FETCH_CLASS,
            'Translations_SegmentTranslationStruct'
        ) ;
    }

    protected function _buildResult( $array_result ) {

    }

}
