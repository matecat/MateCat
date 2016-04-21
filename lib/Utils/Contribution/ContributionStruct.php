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

use \Jobs_JobStruct,
        \Database,
        \Exceptions\ValidationError,
        \CatUtils
    ;

/**
 * Class ContributionStruct
 * @package Contribution
 */
class ContributionStruct extends DataAccess_AbstractDaoObjectStruct {

    protected $cached_results = array();

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
     * \INIT::$MYMEMORY_API_KEY
     * @var string
     */
    public $email = "";

    /**
     * @var int
     */
    public $id_job = null;

    /**
     * @var string
     */
    public $job_password = "";

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

    public function getJobStruct( ){

        if( empty( $this->id_job ) ){
            throw new ValidationError( "Property " . get_class( $this ) . "::id_job required." );
        }

        return $this->cachable( '_jobStruct', $this, function ( $contributionStruct ) {
            $JobDao = new \Jobs_JobDao( Database::obtain() );
            $jobStruct = new \Jobs_JobStruct();
            $jobStruct->id = $contributionStruct->id_job;
            $jobStruct->password = $contributionStruct->job_password;
            return $JobDao->setCacheTTL( 60 * 60 )->read( $jobStruct );
        } );

    }

    public function getProp(){
        $jobStruct = $this->getJobStruct();
        return CatUtils::getTMProps( $jobStruct[ 0 ] );
    }

    /**
     * @return string
     */
    public function __toString() {
        return json_encode( $this->toArray() );
    }

}