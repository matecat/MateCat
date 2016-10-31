<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 11/10/16
 * Time: 18.45
 *
 */

class Users_ClientUserFacade extends stdClass {

    public $uid;
    public $email;
    public $first_name;
    public $last_name;

    /**
     * ClientUserFacade constructor.
     *
     * @param Users_UserStruct $userStruct
     */
    public function __construct( Users_UserStruct $userStruct ) {

        foreach ( $userStruct as $property => $value ) {
            if ( property_exists( $this, $property ) ) {
                $this->$property = $value;
            }
        }

    }

    public function __toString() {
        return json_encode( $this );
    }

}