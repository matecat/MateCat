<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 01/04/15
 * Time: 12.54
 */
class Users_UserDao extends DataAccess_AbstractDao {

    const TABLE = "users";
    const STRUCT_TYPE = "Users_UserStruct";

    protected static $auto_increment_fields = array('uid');
    protected static $primary_keys = array('uid');

    protected static $_query_user_by_uid = " SELECT * FROM users WHERE uid = :uid ";

    public function getByUids($uids) {
        $stmt = $this->_getStatementForCache( self::$_query_user_by_uid );
        $userQuery = new Users_UserStruct();
        $userQuery->uids = $uids;
        return $this->_fetchObject( $stmt,
            $userQuery,
            array(
                'uids' => $userQuery->uids,
            )
        ) ;
    }
    /**
     * @param $token
     * @return Users_UserStruct
     */
    public function getByConfirmationToken( $token ) {
        $conn = $this->con->getConnection();
        $stmt = $conn->prepare( " SELECT * FROM users WHERE confirmation_token = ?");
        $stmt->execute( array($token )) ;
        $stmt->setFetchMode(PDO::FETCH_CLASS, '\Users_UserStruct');
        return $stmt->fetch();
    }

    public function createUser( Users_UserStruct $obj ){
        $conn = $this->con->getConnection();
        \Database::obtain()->begin();

        $obj->create_date = date('Y-m-d H:i:s');

        $stmt = $conn->prepare("INSERT INTO users " .
            " ( uid, email, salt, pass, create_date, first_name, last_name, confirmation_token ) " .
            " VALUES " .
            " ( " .
            " :uid, :email, :salt, :pass, :create_date, " .
            " :first_name, :last_name, :confirmation_token " .
            " )"
        );

        $stmt->execute( $obj->toArray( array(
                'uid', 'email',
                'salt', 'pass',
                'create_date', 'first_name',
                'last_name', 'confirmation_token'
                ))
        );

        $record = $this->getByUid( $conn->lastInsertId() );
        $conn->commit() ;

        return $record ;
    }

    /**
     * @param $id
     *
     * @return Users_UserStruct
     */
    public function getByUid( $id ) {
        $stmt = $this->_getStatementForCache( self::$_query_user_by_uid );
        $userQuery = new Users_UserStruct();
        $userQuery->uid = $id;
        return $this->_fetchObject( $stmt,
                $userQuery,
                array(
                        'uid' => $userQuery->uid,
                )
        )[ 0 ];
    }

    public function destroyCacheByUid( $id ){
        $stmt = $this->_getStatementForCache( self::$_query_user_by_uid );
        $userQuery = new Users_UserStruct();
        $userQuery->uid = $id;
        return $this->_destroyObjectCache( $stmt,
                array(
                        'uid' => $userQuery->uid = $id,
                )
        );
    }

    /**
     * @param string $email
     *
     * @return Users_UserStruct
     */
    public function getByEmail( $email ) {
        $conn = $this->con->getConnection();
        $stmt = $conn->prepare( " SELECT * FROM users WHERE email = ? " );
        $stmt->execute( array( $email ) ) ;
        $stmt->setFetchMode(PDO::FETCH_CLASS, '\Users_UserStruct');
        return $stmt->fetch();
    }

    /**
     *
     * This method is not static and used also to cache at Redis level the values for this Job
     *
     * Use when only the metadata are needed
     *
     * @param Users_UserStruct $UserQuery
     *
     * @return DataAccess_IDaoStruct|DataAccess_IDaoStruct[]
     * @throws Exception
     */
    public function read( Users_UserStruct $UserQuery ) {

        $UserQuery = $this->sanitize( $UserQuery );

        $where_conditions = array();
        $where_parameters = array();

        $query            = "SELECT uid,
                                    email,
                                    create_date,
                                    first_name,
                                    last_name
                             FROM " . self::TABLE . " WHERE %s";

        if ( $UserQuery->uid !== null ) {
            $where_conditions[] = "uid = :uid";
            $where_parameters[ 'uid' ] = $UserQuery->uid;
        }

        if ( $UserQuery->email !== null ) {
            $where_conditions[] = "email = :email";
            $where_parameters[ 'email' ] = $UserQuery->email;
        }

        if ( count( $where_conditions ) ) {
            $where_string = implode( " AND ", $where_conditions );
        } else {
            throw new Exception( "Where condition needed." );
        }

        $query = sprintf( $query, $where_string );
        $stmt = $this->_getStatementForCache( $query );

        return $this->_fetchObject( $stmt,
                $UserQuery,
                $where_parameters
        );

    }

    protected function _getStatementForCache( $query ) {

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $query );

        return $stmt;
    }


    public function getProjectOwner( $job_id ) {
        $job_id = (int) $job_id ;

        $query = "SELECT * FROM users " .
            " INNER JOIN jobs ON jobs.owner = users.email "  .
            " WHERE jobs.id = $job_id " .
            " LIMIT 1 " ;

        Log::doLog($query);

        $arr_result = $this->_fetch_array( $query );

        return $this->_buildResult( $arr_result );
    }

    /**
     * @param string[] $email_list
     *
     * @return Users_UserStruct[]
     */
    public function getByEmails( $email_list ) {
        $conn = $this->con->getConnection();
        $stmt = $conn->prepare( " SELECT * FROM users WHERE email IN ( " . str_repeat( '?,', count( $email_list ) - 1) . '?' . " ) ");
        $stmt->execute( $email_list ) ;
        $stmt->setFetchMode( PDO::FETCH_CLASS, '\Users_UserStruct' );
        $res = $stmt->fetchAll();
        $userMap = [];
        foreach ( $res as $user ){
            $userMap[ $user->email ] = $user;
        }
        return $userMap;
    }

    /**
     * @param Users_UserStruct $input
     *
     * @return Users_UserStruct
     * @throws Exception
     */
    public function sanitize( $input ) {
        $con = Database::obtain();
        parent::_sanitizeInput( $input, self::STRUCT_TYPE );

        $input->uid         = ( $input->uid !== null ) ? (int)$input->uid : null;
        $input->email       = ( $input->email !== null ) ? $con->escape( $input->email ) : null;
        $input->create_date = ( $input->create_date !== null ) ? $con->escape( $input->create_date ) : null;
        $input->first_name  = ( $input->first_name !== null ) ? $con->escape( $input->first_name ) : null;
        $input->last_name   = ( $input->last_name !== null ) ? $con->escape( $input->last_name ) : null;

        return $input;
    }

    /**
     * @param $array_result array
     *
     * @return Users_UserStruct|Users_UserStruct[]
     */
    protected function _buildResult( $array_result ) {
        $result = array();

        foreach ( $array_result as $item ) {

            $build_arr = array(
                    'uid'         => (int)$item[ 'uid' ],
                    'email'       => $item[ 'email' ],
                    'create_date' => $item[ 'create_date' ],
                    'first_name'  => $item[ 'first_name' ],
                    'last_name'   => $item[ 'last_name' ],
            );

            $obj = new Users_UserStruct( $build_arr );

            $result[ ] = $obj;
        }

        return $result;
    }


}
