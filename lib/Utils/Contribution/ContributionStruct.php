<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/04/16
 * Time: 17.59
 *
 */

namespace Contribution;

use \DataAccess_AbstractDaoObjectStruct;

use \DataAccess_IDaoStruct;
use \Jobs_JobStruct,
        \Database,
        \Exceptions\ValidationError,
        \CatUtils,
        \Constants_TranslationStatus
    ;

/**
 * Class ContributionStruct
 * @package Contribution
 */
class ContributionStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct {

    protected $cached_results = array();

    /**
     * @var int
     */
    public $id_segment = null;

    /**
     * @var bool
     */
    public $fromRevision = false;

    /**
     * @var string
     */
    public $segment = "";

    /**
     * @var string
     */
    public $translation = "";

    /**
     * @var string
     */
    public $oldSegment = "";

    /**
     * @var string
     */
    public $oldTranslation = "";

    /**
     * \INIT::$MYMEMORY_API_KEY
     * @var string
     */
    public $api_key = "";

    /**
     * @var int
     */
    public $id_job = null;

    /**
     * @var string
     */
    public $job_password = "";

    /**
     * User login info needed to get information about the tm keys of the job
     * @var int
     */
    public $uid = 0;

    /**
     * @var string
     */
    public $oldTranslationStatus = Constants_TranslationStatus::STATUS_NEW;

    /**
     * @var bool
     */
    public $propagationRequest =true;

    /**
     * @param $name
     *
     * @return mixed
     */
    public function __get( $name ) {

        if ( !property_exists( $this, $name ) ) {
            throw new \DomainException( 'Trying to get an undefined property ' . $name );
        }

        return $this->$name;
    }

    /**
     * Global Cached record for jobs metadata
     *
     * WARNING these values are cached only globally and not locally by the "cachable" method ( in the running process )
     * because we want control the cache eviction from other entrypoints.
     *
     * @return mixed
     *
     * @throws ValidationError
     */
    public function getJobStruct( ){

        if( empty( $this->id_job ) ){
            throw new ValidationError( "Property " . get_class( $this ) . "::id_job required." );
        }

        $JobDao = new \Jobs_JobDao( Database::obtain() );
        $jobStruct = new \Jobs_JobStruct();
        $jobStruct->id = $this->id_job;
        $jobStruct->password = $this->job_password;
        return $JobDao->setCacheTTL( 60 * 60 )->read( $jobStruct );

    }

    public function getProp(){
        $jobStruct = $this->getJobStruct();
        return CatUtils::getTMProps( $jobStruct[ 0 ] );
    }

    /**
     * Global and Local Cached record for user metadata
     *
     * WARNING these values are cached
     *
     * @return mixed
     *
     * @throws ValidationError
     */
    public function getUserInfo(){

        if( empty( $this->uid ) ){
            throw new ValidationError( "Property " . get_class( $this ) . "::uid required." );
        }

        return $this->cachable( '_userCredentials', $this, function ( $contributionStruct ) {
            $userDao = new \Users_UserDao( Database::obtain() );
            $userCredentials = new \Users_UserStruct();
            $userCredentials->uid = $contributionStruct->uid;
            return $userDao->setCacheTTL( 60 * 60 )->read( $userCredentials );
        } );
        
    }

    /**
     * @return string
     */
    public function __toString() {
        return json_encode( $this->toArray() );
    }

}