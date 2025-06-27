<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/03/17
 * Time: 17.10
 *
 */

namespace Model\Outsource;

use Database;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\IDaoStruct;
use Model\Jobs\JobStruct;
use PDO;
use ReflectionException;

class ConfirmationDao extends AbstractDao {

    const TABLE       = "outsource_confirmation";
    const STRUCT_TYPE = "ConfirmationStruct";

    protected static array $auto_increment_field = [ 'id' ];
    protected static array $primary_keys         = [ 'id' ];

    protected static $_query_update_job_password    = "UPDATE outsource_confirmation SET password = :new_password WHERE id_job = :id_job AND password = :old_password LIMIT 1";
    protected static $_query_get_by_job_id_password = "SELECT * FROM outsource_confirmation WHERE id_job = :id_job AND password = :password LIMIT 1";

    public function updatePassword( $jid, $old_password, $new_password ) {

        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( self::$_query_update_job_password );
        $stmt->bindValue( ':id_job', $jid, PDO::PARAM_INT );
        $stmt->bindValue( ':new_password', $new_password, PDO::PARAM_STR );
        $stmt->bindValue( ':old_password', $old_password, PDO::PARAM_STR );
        $stmt->execute();

        return $stmt->rowCount();

    }

    /**
     * @param JobStruct $jobStruct
     *
     * @return IDaoStruct|TranslatedConfirmationStruct
     * @throws ReflectionException
     */
    public function getConfirmation( JobStruct $jobStruct ) {

        $query = self::$_query_get_by_job_id_password;
        $data  = [ 'id_job' => $jobStruct->id, 'password' => $jobStruct->password ];

        $stmt               = $this->_getStatementForQuery( $query );
        $confirmationStruct = new TranslatedConfirmationStruct();

        return $this->_fetchObject( $stmt,
                $confirmationStruct,
                $data
        )[ 0 ] ?? null;

    }

    public function destroyConfirmationCache( JobStruct $jobStruct ) {
        $query = self::$_query_get_by_job_id_password;
        $stmt  = $this->_getStatementForQuery( $query );

        return $this->_destroyObjectCache( $stmt,
                TranslatedConfirmationStruct::class,
                [
                        'id_job'   => $jobStruct->id,
                        'password' => $jobStruct->password
                ]
        );
    }

}