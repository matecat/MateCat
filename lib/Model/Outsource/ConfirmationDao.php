<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/03/17
 * Time: 17.10
 *
 */

namespace Outsource;

use Database;
use PDO;

class ConfirmationDao extends \DataAccess_AbstractDao {

    const TABLE       = "outsource_confirmation";
    const STRUCT_TYPE = "ConfirmationStruct";

    protected static $auto_increment_field = array( 'id' );
    protected static $primary_keys         = array( 'id' );

    protected static $_query_update_job_password    = "UPDATE outsource_confirmation SET password = :new_password WHERE id_job = :id_job AND password = :old_password LIMIT 1";
    protected static $_query_get_by_job_id_password = "SELECT * FROM outsource_confirmation WHERE id_job = :id_job AND password = :password LIMIT 1";

    public function updatePassword( $jid, $old_password, $new_password ){

        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( self::$_query_update_job_password );
        $stmt->bindValue( ':id_job', $jid, PDO::PARAM_INT );
        $stmt->bindValue( ':new_password', $new_password, PDO::PARAM_STR );
        $stmt->bindValue( ':old_password', $old_password, PDO::PARAM_STR );
        $stmt->execute();

        return $stmt->rowCount();

    }

    /**
     * @param \Jobs_JobStruct $jobStruct
     *
     * @return \DataAccess_IDaoStruct|TranslatedConfirmationStruct
     */
    public function getConfirmation( \Jobs_JobStruct $jobStruct ){

        $query = self::$_query_get_by_job_id_password;
        $data = [ 'id_job' => $jobStruct->id, 'password' => $jobStruct->password ];

        $stmt                     = $this->_getStatementForCache( $query );
        $confirmationStruct     = new TranslatedConfirmationStruct();

        return @$this->_fetchObject( $stmt,
                $confirmationStruct,
                $data
        )[0];

    }

    public function destroyConfirmationCache( \Jobs_JobStruct $jobStruct ) {
        $query = self::$_query_get_by_job_id_password;
        $stmt  = $this->_getStatementForCache( $query );
        return $this->_destroyObjectCache( $stmt,
                array(
                        'id_job'   => $jobStruct->id,
                        'password' => $jobStruct->password
                )
        );
    }

}