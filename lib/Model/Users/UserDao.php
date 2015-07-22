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

    public function read( Users_UserStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $where_conditions = array();
        $query            = "SELECT uid,
                                    email,
                                    create_date,
                                    first_name,
                                    last_name
                             FROM " . self::TABLE . " WHERE %s";

        if ( $obj->uid !== null ) {
            $where_conditions[ ] = "uid = " . $obj->uid;
        }

        if ( $obj->email !== null ) {
            $where_conditions[ ] = "email = '" . $obj->email . "'";
        }

        if ( count( $where_conditions ) ) {
            $where_string = implode( " AND ", $where_conditions );
        }
        else {
            throw new Exception( "Where condition needed." );
        }

        $query = sprintf( $query, $where_string );

        $arr_result = $this->con->fetch_array( $query );

        return $this->_buildResult( $arr_result );
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
        foreach($uids_array as $k => $v) {
            array_push($sanitized_array, ( (int) $v['uid']) );
        }

        if (empty($sanitized_array)) {
            return array();
        }

        $query = "SELECT * FROM " . self::TABLE .
            " WHERE uid IN ( " . implode(', ', $sanitized_array) . " ) " ;

        Log::doLog($query);
        $arr_result = $this->con->fetch_array( $query );

        $this->_checkForErrors();

        return $this->_buildResult( $arr_result );
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