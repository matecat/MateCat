<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/04/16
 * Time: 17.59
 *
 */

namespace Utils\Contribution;

use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\Database;
use Model\DataAccess\IDaoStruct;
use Model\Exceptions\ValidationError;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use Utils\Constants\TranslationStatus;
use Utils\TaskRunner\Commons\Params;

/**
 * Class SetContributionRequest
 * @package Contribution
 */
class SetContributionRequest extends AbstractDaoObjectStruct implements IDaoStruct {

    protected array $cached_results = [];

    /**
     * @var int
     */
    public int $id_file;

    /**
     * @var int
     */
    public int $id_segment;

    /**
     * @var bool
     */
    public bool $fromRevision = false;

    /**
     * @var string
     */
    public string $segment = "";

    /**
     * @var string
     */
    public string $translation = "";

    /**
     * @var string
     */
    public string $oldSegment = "";

    /**
     * @var string
     */
    public string $oldTranslation = "";

    /**
     * @var string
     */
    public string $context_before = "";

    /**
     * @var string
     */
    public string $context_after = "";

    /**
     * \AppConfig::$MYMEMORY_API_KEY
     * @var string
     */
    public string $api_key = "";

    /**
     * @var int
     */
    public int $id_job;

    /**
     * @var string
     */
    public string $job_password = "";

    /**
     * User login info needed to get information about the tm keys of the job
     * @var int
     */
    public int $uid = 0;

    /**
     * @var string
     */
    public string $oldTranslationStatus = TranslationStatus::STATUS_NEW;

    /**
     * @var bool
     */
    public bool $propagationRequest = true;

    /**
     * @var array|string
     */
    public $props = [];

    /**
     * @var integer
     */
    public int $id_mt;

    public bool $contextIsSpice = false;

    /**
     * Global Cached record for jobs metadata
     *
     * WARNING these values are cached only globally and not locally by the "cachable" method (in the running process)
     * because we want to control the cache eviction from other entrypoints.
     *
     * @return JobStruct
     *
     * @throws ValidationError
     */
    public function getJobStruct(): ?JobStruct {

        if ( empty( $this->id_job ) ) {
            throw new ValidationError( "Property " . get_class( $this ) . "::id_job required." );
        }

        return $this->cachable( __METHOD__, function () {
            $JobDao              = new JobDao( Database::obtain() );
            $jobStruct           = new JobStruct();
            $jobStruct->id       = $this->id_job;
            $jobStruct->password = $this->job_password;

            return $JobDao->setCacheTTL( 60 * 60 )->read( $jobStruct )[ 0 ];
        } );

    }

    /**
     * @return array
     * @throws ValidationError
     */
    public function getProp(): array {
        $jobStruct = $this->getJobStruct();
        $props     = $this->props;
        if ( !is_array( $props ) ) {
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
    public function getUserInfo() {

        if ( empty( $this->uid ) ) {
            throw new ValidationError( "Property " . get_class( $this ) . "::uid required." );
        }

        return $this->cachable( __METHOD__, function () {
            $userDao              = new UserDao( Database::obtain() );
            $userCredentials      = new UserStruct();
            $userCredentials->uid = $this->uid;

            return $userDao->setCacheTTL( 60 * 60 * 24 * 30 )->read( $userCredentials );
        } );

    }

    public function getProject() {

        return $this->cachable( __METHOD__, function () {
            $jobStruct = $this->getJobStruct();

            return $jobStruct->getProject( 60 * 60 * 24 );
        } );

    }

    /**
     * @return string
     */
    public function getSessionId(): string {
        return md5( $this->id_file . '-' . $this->id_job . '-' . $this->job_password );
    }

    /**
     * @return string
     */
    public function __toString() {
        return json_encode( $this->toArray() );
    }

}