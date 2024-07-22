<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/04/16
 * Time: 17.59
 *
 */

namespace Contribution;

use Constants_TranslationStatus;
use DataAccess_AbstractDaoObjectStruct;
use DataAccess_IDaoStruct;
use Database;
use Exceptions\ValidationError;
use Jobs_JobDao;
use Jobs_JobStruct;
use Projects_MetadataDao;
use Projects_MetadataStruct;
use TaskRunner\Commons\Params;

/**
 * Class ContributionSetStruct
 * @package Contribution
 */
class ContributionSetStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct {

    protected $cached_results = array();

    /**
     * @var int
     */
    public $id_file = null;

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
     * @var string
     */
    public $context_before = "";

    /**
     * @var string
     */
    public $context_after = "";

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
     * @var array
     */
    public $props = [];

    /**
     * @var integer
     */
    public $id_mt;

    /**
     * Global Cached record for jobs metadata
     *
     * WARNING these values are cached only globally and not locally by the "cachable" method ( in the running process )
     * because we want control the cache eviction from other entrypoints.
     *
     * @return Jobs_JobStruct
     *
     * @throws ValidationError
     */
    public function getJobStruct( ){

        if( empty( $this->id_job ) ){
            throw new ValidationError( "Property " . get_class( $this ) . "::id_job required." );
        }

        return $this->cachable( '_contributionJob', $this, function () {
            $JobDao = new Jobs_JobDao( Database::obtain() );
            $jobStruct = new Jobs_JobStruct();
            $jobStruct->id = $this->id_job;
            $jobStruct->password = $this->job_password;
            return @$JobDao->setCacheTTL( 60 * 60 )->read( $jobStruct )[ 0 ];
        } );

    }

    /**
     * @return array
     * @throws ValidationError
     */
    public function getProp(){
        $jobStruct = $this->getJobStruct();
        $props = $this->props;
        if( !is_array( $props ) ) {
            /**
             * @var $props Params
             */
            $props = $props->toArray();
        }
        return array_merge( $jobStruct->getTMProps(), $props );
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
            return $userDao->setCacheTTL( 60 * 60 * 24 * 30 )->read( $userCredentials );
        } );
        
    }

    public function getProject(){

        return $this->cachable( '_projectStruct', $this, function ( $contributionStruct ) {
            $jobStruct = $this->getJobStruct();
            return $jobStruct->getProject( 60 * 60 * 24 );
        } );

    }

    /**
     * Get all project Metadata not related to features
     *
     * @return Projects_MetadataStruct[]
     * @throws ValidationError
     */
    public function getProjectMetaData(){
        $jobStruct = $this->getJobStruct();
        $projectMeta = array_filter( $jobStruct->getProjectMetadata(), function( $metadataStruct ){
            return $metadataStruct->key != Projects_MetadataDao::FEATURES_KEY;
        } );
        return $projectMeta;
    }

    public function getSegmentNotes(){
        return $this->cachable( '_segmentNote', $this, function () {
            return \Segments_SegmentNoteDao::getBySegmentId( $this->id_segment );
        } );
    }

    /**
     * @return string
     */
    public function getSessionId()
    {
        return md5($this->id_file. '-' . $this->id_job . '-' . $this->job_password);
    }

    /**
     * @return string
     * @throws \ReflectionException
     */
    public function __toString() {
        return json_encode( $this->toArray() );
    }

}