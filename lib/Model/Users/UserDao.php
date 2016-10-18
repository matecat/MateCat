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

    public function createUser( Users_UserStruct $obj ){
        $conn = $this->con->getConnection();

        $obj->create_date = date('Y-m-d H:i:s');

        $stmt = $conn->prepare("INSERT INTO users " .
            " ( uid, email, salt, pass, create_date, first_name, last_name, api_key ) " .
            " VALUES " .
            " ( " .
            " :uid, :email, :salt, :pass, :create_date, " .
            " :first_name, :last_name, :api_key " .
            " )"
        );

        $stmt->execute( $obj->toArray( array(
                'uid', 'email',
                'salt', 'pass',
                'create_date', 'first_name',
                'last_name', 'api_key'
                ))
        );

        return $this->getByUid( $conn->lastInsertId() );
    }

    public function getByUid( $id ) {
        $conn = $this->con->getConnection();
        $stmt = $conn->prepare( " SELECT * FROM users WHERE uid = ?");
        $stmt->execute( array($id )) ;
        $stmt->setFetchMode(PDO::FETCH_CLASS, '\Users_UserStruct');
        return $stmt->fetch();
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

    public function getByUids( $uids_array ) {

        $sanitized_array = array();
        foreach ( $uids_array as $k => $v ) {
            if ( !is_numeric( $v ) ) {
                array_push( $sanitized_array, ( (int)$v[ 'uid' ] ) );
            } else {
                array_push( $sanitized_array, ( (int)$v ) );
            }
        }

        if ( empty( $sanitized_array ) ) {
            return array();
        }

        $query = "SELECT * FROM " . self::TABLE .
                " WHERE uid IN ( " . str_repeat( '?,', count( $sanitized_array ) - 1) . '?' . " ) ";

        $stmt = $this->setCacheTTL( 60 * 10 )->_getStatementForCache( $query );

        return $this->_fetchObject(
                $stmt,
                new Users_UserStruct(),
                $sanitized_array
        );

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
