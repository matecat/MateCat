<?php


namespace Translations ;

class WarningDao extends \DataAccess_AbstractDao {

    public static $primary_keys = array();

    public static $TABLE = 'translation_warnings' ;

    public static function deleteByScope($id_job, $id_segment, $scope) {
        $sql = "DELETE FROM translation_warnings " .
                " WHERE id_job = :id_job " .
                " AND id_segment = :id_segment " .
                " AND scope = :scope " ;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute(
                array(
                        'id_job'     => $id_job,
                        'id_segment' => $id_segment,
                        'scope'      => $scope
                )
        );

        return $stmt->rowCount();
    }

    public static function insertWarning( WarningStruct $warning ) {
        $sql = self::buildInsertStatement( $warning->toArray(), array() ) ;
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        return $stmt->execute( $warning->toArray() )  ;
    }


    protected function _buildResult( $array_result ) {
        // TODO: Implement _buildResult() method.
    }


}