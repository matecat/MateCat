<?php 

class Segments_SegmentNoteDao extends DataAccess_AbstractDao {

    public static function getBySegmentId( $id_segment ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM segment_notes " . 
            " WHERE id_segment = ? " ); 
        $stmt->execute( array( $id_segment ) ); 
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'Segments_SegmentNoteStruct');
        return $stmt->fetchAll(); 
    }

    public static function insertRecord( $values ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "INSERT INTO segment_notes " . 
            " ( id_segment, note ) values " . 
            " ( :id_segment, :note ) " 
        ); 

        $stmt->execute( $values ); 
        
    }

    protected function _buildResult( $array_result ) {

    }

}

