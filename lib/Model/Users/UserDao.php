<?php

use DataAccess\AbstractDao;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 01/04/15
 * Time: 12.54
 */
class Users_UserDao extends AbstractDao {

    const TABLE       = "users";
    const STRUCT_TYPE = "Users_UserStruct";

    protected static array $auto_increment_field = [ 'uid' ];
    protected static array $primary_keys         = [ 'uid' ];

    protected static string $_query_user_by_uid            = " SELECT * FROM users WHERE uid = :uid ";
    protected static string $_query_user_by_email          = " SELECT * FROM users WHERE email = :email ";
    protected static string $_query_assignee_by_project_id = "SELECT * FROM users 
        INNER JOIN projects ON projects.id_assignee = users.uid 
        WHERE projects.id = :id_project
        LIMIT 1 ";

    protected static string $_query_owner_by_job_id = "SELECT * FROM users 
        INNER JOIN jobs ON jobs.owner = users.email
        WHERE jobs.id = :job_id
        LIMIT 1 ";

    /**
     * @param Users_UserStruct $userStruct
     *
     * @return int
     */
    public function delete( Users_UserStruct $userStruct ): int {

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare( " DELETE FROM users WHERE uid = ?" );
        $stmt->execute( [ $userStruct->uid ] );

        return $stmt->rowCount();
    }

    /**
     * @param array $uids_array
     *
     * @return Users_UserStruct[]
     * @throws ReflectionException
     */
    public function getByUids( array $uids_array ): array {

        $sanitized_array = [];

        foreach ( $uids_array as $v ) {
            if ( !is_numeric( $v ) ) {
                $sanitized_array[] = (int)$v[ 'uid' ];
            } else {
                $sanitized_array[] = (int)$v;
            }
        }

        if ( empty( $sanitized_array ) ) {
            return [];
        }

        $query = "SELECT * FROM " . self::TABLE .
                " WHERE uid IN ( " . str_repeat( '?,', count( $sanitized_array ) - 1 ) . '?' . " ) ";

        $stmt = $this->_getStatementForQuery( $query );

        /**
         * @var $__resultSet Users_UserStruct[]
         */
        $__resultSet = $this->_fetchObject(
                $stmt,
                new Users_UserStruct(),
                $sanitized_array
        );

        $resultSet = [];
        foreach ( $__resultSet as $user ) {
            $resultSet[ $user->uid ] = $user;
        }

        return $resultSet;

    }

    /**
     * @param $token
     *
     * @return ?Users_UserStruct
     */
    public function getByConfirmationToken( $token ): ?Users_UserStruct {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare( " SELECT * FROM users WHERE confirmation_token = ?" );
        $stmt->execute( [ $token ] );
        $stmt->setFetchMode( PDO::FETCH_CLASS, Users_UserStruct::class );

        return $stmt->fetch() ?: null;
    }

    /**
     * @param Users_UserStruct $obj
     *
     * @return Users_UserStruct
     * @throws ReflectionException
     * @throws Exception
     */
    public function createUser( Users_UserStruct $obj ): ?Users_UserStruct {
        $conn = $this->database->getConnection();
        Database::obtain()->begin();

        $obj->create_date = date( 'Y-m-d H:i:s' );
        $stmt = $conn->prepare( "INSERT INTO users " .
                " ( uid, email, salt, pass, create_date, first_name, last_name, confirmation_token ) " .
                " VALUES " .
                " ( " .
                " :uid, :email, :salt, :pass, :create_date, " .
                " :first_name, :last_name, :confirmation_token " .
                " )"
        );

        $stmt->execute( $obj->toArray( [
                'uid', 'email',
                'salt', 'pass',
                'create_date', 'first_name',
                'last_name', 'confirmation_token'
        ] )
        );

        $record = $this->getByUid( $conn->lastInsertId() );
        $conn->commit();

        return $record;
    }

    /**
     * @param Users_UserStruct $obj
     *
     * @return Users_UserStruct
     * @throws ReflectionException
     */
    public function updateUser( Users_UserStruct $obj ): Users_UserStruct {
        $conn = $this->database->getConnection();
        Database::obtain()->begin();

        $stmt = $conn->prepare( "UPDATE users
            SET 
                uid = :uid, 
                email = :email, 
                salt = :salt, 
                pass = :pass, 
                create_date = :create_date, 
                first_name = :first_name, 
                last_name = :last_name, 
                confirmation_token = :confirmation_token, 
                oauth_access_token = :oauth_access_token 
            WHERE uid = :uid 
        " );

        $stmt->execute( $obj->toArray( [
                'uid',
                'email',
                'salt',
                'pass',
                'create_date',
                'first_name',
                'last_name',
                'confirmation_token',
                'oauth_access_token'
        ] )
        );

        $record = $this->getByUid( $obj->uid );
        $conn->commit();

        return $record;
    }

    /**
     * @param $id
     *
     * @return ?Users_UserStruct
     * @throws ReflectionException
     */
    public function getByUid( $id ): ?Users_UserStruct {
        $stmt = $this->_getStatementForQuery( self::$_query_user_by_uid );

        /**
         * @var $res ?Users_UserStruct
         */
        $res = $this->_fetchObject( $stmt,
                new Users_UserStruct(),
                [
                        'uid' => $id,
                ]
        )[ 0 ] ?? null;

        return $res;
    }

    /**
     * @throws ReflectionException
     */
    public function destroyCacheByUid( $uid ): bool {
        $stmt = $this->_getStatementForQuery( self::$_query_user_by_uid );

        return $this->_destroyObjectCache( $stmt,
                Users_UserStruct::class,
                [
                        'uid' => $uid,
                ]
        );
    }

    /**
     * @param string $email
     *
     * @return ?Users_UserStruct
     * @throws ReflectionException
     */
    public function getByEmail( string $email ): ?Users_UserStruct {
        $stmt = $this->_getStatementForQuery( self::$_query_user_by_email );

        /**
         * @var $res ?Users_UserStruct
         */
        $res = $this->_fetchObject( $stmt,
                new Users_UserStruct(),
                [ 'email' => $email ]
        )[ 0 ] ?? null;

        return $res;
    }

    /**
     * @param $email
     *
     * @return bool
     * @throws ReflectionException
     */
    public function destroyCacheByEmail( $email ): bool {
        $stmt             = $this->_getStatementForQuery( self::$_query_user_by_email );
        $userQuery        = new Users_UserStruct();
        $userQuery->email = $email;

        return $this->_destroyObjectCache( $stmt, Users_UserStruct::class, [ 'email' => $userQuery->email ] );
    }


    /**
     *
     * This method is not static and used also to cache at Redis level the values for this Job
     *
     * Use when only the metadata is necessary
     *
     * @param Users_UserStruct $UserQuery
     *
     * @return Users_UserStruct[]
     * @throws Exception
     */
    public function read( \DataAccess\IDaoStruct $UserQuery ): array {

        [ $query, $where_parameters ] = $this->_buildReadQuery( $UserQuery );
        $stmt = $this->_getStatementForQuery( $query );

        /** @var Users_UserStruct[] */
        return $this->_fetchObject( $stmt,
                $UserQuery,
                $where_parameters
        );

    }

    /**
     * @throws Exception
     */
    protected function _buildReadQuery( \DataAccess\IDaoStruct $UserQuery ): array {
        $UserQuery = $this->sanitize( $UserQuery );

        $where_conditions = [];
        $where_parameters = [];

        $query = "SELECT uid,
                                    email,
                                    create_date,
                                    first_name,
                                    last_name
                             FROM " . self::TABLE . " WHERE %s";

        if ( $UserQuery->uid !== null ) {
            $where_conditions[]        = "uid = :uid";
            $where_parameters[ 'uid' ] = $UserQuery->uid;
        }

        if ( $UserQuery->email !== null ) {
            $where_conditions[]          = "email = :email";
            $where_parameters[ 'email' ] = $UserQuery->email;
        }

        if ( count( $where_conditions ) ) {
            $where_string = implode( " AND ", $where_conditions );
        } else {
            throw new Exception( "Where condition needed." );
        }

        return [ sprintf( $query, $where_string ), $where_parameters ];

    }

    /***
     * @param int $job_id
     *
     * @return ?Users_UserStruct
     * @throws ReflectionException
     */
    public function getProjectOwner( int $job_id ): ?Users_UserStruct {

        $stmt = $this->_getStatementForQuery( self::$_query_owner_by_job_id );

        /**
         * @var $res Users_UserStruct
         */
        $res = $this->_fetchObject( $stmt,
                new Users_UserStruct(),
                [ 'job_id' => $job_id ]
        )[ 0 ] ?? null;

        return $res;
    }

    /**
     * @throws ReflectionException
     */
    public function getProjectAssignee( int $project_id ): ?Users_UserStruct {

        $stmt = $this->_getStatementForQuery( self::$_query_assignee_by_project_id );

        /** @var $res Users_UserStruct */
        $res = $this->_fetchObject( $stmt,
                new Users_UserStruct(),
                [ 'id_project' => $project_id ]
        )[ 0 ] ?? null;

        return $res;

    }

    /**
     * @param string[] $email_list
     *
     * @return Users_UserStruct[]
     */
    public function getByEmails( array $email_list ): array {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare( " SELECT * FROM users WHERE email IN ( " . str_repeat( '?,', count( $email_list ) - 1 ) . '?' . " ) " );
        $stmt->execute( $email_list );
        $stmt->setFetchMode( PDO::FETCH_CLASS, Users_UserStruct::class );
        $res     = $stmt->fetchAll();
        $userMap = [];
        foreach ( $res as $user ) {
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
    public function sanitize( \DataAccess\IDaoStruct $input ) {
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
     * @return Users_UserStruct[]
     */
    protected function _buildResult( array $array_result ): array {
        $result = [];

        foreach ( $array_result as $item ) {

            $build_arr = [
                    'uid'         => (int)$item[ 'uid' ],
                    'email'       => $item[ 'email' ],
                    'create_date' => $item[ 'create_date' ],
                    'first_name'  => $item[ 'first_name' ],
                    'last_name'   => $item[ 'last_name' ],
            ];

            $obj = new Users_UserStruct( $build_arr );

            $result[] = $obj;
        }

        return $result;
    }


}
