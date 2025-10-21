<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/04/16
 * Time: 17.59
 *
 */

namespace Utils\Contribution;

use Model\Analysis\Constants\InternalMatchesConstants;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;
use Model\Jobs\JobStruct;
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
     * @var string
     */
    public string $translation_origin = InternalMatchesConstants::TM;

    /**
     * @var JobStruct
     */
    public JobStruct $jobStruct;

    /**
     * Retrieves the `JobStruct` object associated with this instance.
     *
     * This method provides access to the `JobStruct` object, which contains
     * detailed information about the job related to this contribution request.
     *
     * @return JobStruct The `JobStruct` instance associated with this object.
     */
    public function getJobStruct(): JobStruct {
        return $this->jobStruct;
    }

    /**
     * @return array
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