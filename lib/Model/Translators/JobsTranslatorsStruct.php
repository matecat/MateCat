<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 10/04/17
 * Time: 20.14
 *
 */

namespace Translators;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;
use Users_UserDao;
use Users_UserStruct;

class JobsTranslatorsStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id_job;
    public $job_password;
    public $id_translator_profile;
    public $email;
    public $added_by;
    public $delivery_date;
    public $job_owner_timezone = 0;
    public $source;
    public $target;

    /**
     * @return Users_UserStruct
     *
     */
    public function getUser(){
        if( !empty( $this->id_translator_profile ) ){
            $existentUser = ( new Users_UserDao() )->setCacheTTL( 60 * 60 )->getByEmail( $this->email );
            return $existentUser;
        }
        return new Users_UserStruct();
    }

}