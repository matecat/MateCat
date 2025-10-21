<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 10/04/17
 * Time: 20.14
 *
 */

namespace Model\Translators;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;

class JobsTranslatorsStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public int    $id_job;
    public string $job_password;
    public ?int   $id_translator_profile = null;
    public string $email;
    public int    $added_by;
    public string $delivery_date;
    public float  $job_owner_timezone    = 0;
    public string $source;
    public string $target;

    /**
     * @return UserStruct
     * @throws ReflectionException
     */
    public function getUser(): UserStruct {
        if ( !empty( $this->id_translator_profile ) ) {
            return ( new UserDao() )->setCacheTTL( 60 * 60 )->getByEmail( $this->email );
        }

        return new UserStruct();
    }

}