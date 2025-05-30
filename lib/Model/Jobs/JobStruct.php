<?php

use DataAccess\AbstractDaoSilentStruct;
use DataAccess\ArrayAccessTrait;
use DataAccess\IDaoStruct;
use Exceptions\NotFoundException;
use Files\FileDao;
use Files\FileStruct;
use Outsource\ConfirmationDao;
use Outsource\ConfirmationStruct;
use Outsource\TranslatedConfirmationStruct;
use TmKeyManagement\UserKeysModel;
use Translations\WarningDao;
use Translators\JobsTranslatorsDao;
use Translators\JobsTranslatorsStruct;
use WordCount\WordCountStruct;

class Jobs_JobStruct extends AbstractDaoSilentStruct implements IDaoStruct, ArrayAccess {

    use ArrayAccessTrait;

    public ?int   $id = null; // null is an accepted value for MySQL autoincrement
    public ?string $password = null;
    public int    $id_project;

    public int $job_first_segment;
    public int $job_last_segment;

    public string  $source;
    public string  $target;
    public string  $tm_keys                 = '[]';
    public ?string $id_translator           = null;
    public ?string $job_type                = null;
    public int     $total_time_to_edit      = 0;
    public int     $avg_post_editing_effort = 0;
    public int     $only_private_tm         = 0;
    public ?int    $last_opened_segment     = null;
    public int     $id_tms                  = 1;
    public int     $id_mt_engine            = 0;
    public ?string $create_date             = null;
    public ?string $last_update             = null;
    public int     $disabled                = 0;
    public string  $owner                   = '';
    public string  $status_owner            = Constants_JobStatus::STATUS_ACTIVE;
    public ?string $status_translator       = null;
    public string  $status                  = 'active';
    public int     $standard_analysis_wc    = 0;

    /**
     * Column 'completed' cannot be null, moreover, it is BIT(1), and
     * PDO does not work well in this case without explicitly
     * tell him that this is an INT.
     * So, we can't set 0 because it will be treated as string, set it to false, it works.
     * @see https://bugs.php.net/bug.php?id=50757
     */
    public bool  $completed        = false; //Column 'completed' cannot be null
    public float $new_words        = 0;
    public float $draft_words      = 0;
    public float $translated_words = 0;
    public float $approved_words   = 0;
    public float $approved2_words  = 0;
    public float $rejected_words   = 0;

    public int $new_raw_words        = 0;
    public int $draft_raw_words      = 0;
    public int $translated_raw_words = 0;
    public int $approved_raw_words   = 0;
    public int $approved2_raw_words  = 0;
    public int $rejected_raw_words   = 0;

    public string $subject       = '';
    public string $payable_rates = '[]';

    public int $total_raw_wc = 0;

    /**
     * @var JobsTranslatorsStruct|null
     */
    protected ?JobsTranslatorsStruct $_translator = null;

    /**
     * @var ?ConfirmationStruct
     */
    protected ?ConfirmationStruct $_outsource = null;

    /**
     * @var int
     */
    protected int $_openThreads = 0;

    protected bool $is_review = false;


    protected int $_sourcePage;

    /**
     *
     * @return array
     */
    public function getTMProps(): array {
        $projectData = $this->getProject();

        return [
            'project_id'   => $projectData->id,
            'project_name' => $projectData->name,
            'job_id'       => $this->id,
        ];
    }

    /**
     * @return ?JobsTranslatorsStruct
     */
    public function getTranslator(): ?JobsTranslatorsStruct {

        $this->_translator = $this->cachable( __METHOD__, $this, function ( Jobs_JobStruct $jobStruct ) {
            $jTranslatorsDao = new JobsTranslatorsDao();

            return $jTranslatorsDao->setCacheTTL( 60 * 60 )->findByJobsStruct( $jobStruct )[ 0 ] ?? null;
        } );

        return $this->_translator;

    }

    /**
     * @return ConfirmationStruct
     * @throws NotFoundException
     */
    public function getOutsource(): ?ConfirmationStruct {

        $this->_outsource = $this->cachable( __METHOD__, $this, function ( Jobs_JobStruct $jobStruct ) {
            $outsourceDao = new ConfirmationDao();

            return $outsourceDao->setCacheTTL( 60 * 60 )->getConfirmation( $jobStruct );
        } );

        if ( empty( $this->_outsource->id_vendor ) ) {
            return null;
        }

        switch ( $this->_outsource->id_vendor ) {
            case TranslatedConfirmationStruct::VENDOR_ID:
                //Ok Do Nothing
                break;
            default:
                throw new NotFoundException( "Vendor id " . $this->_outsource->id_vendor . " not found." );
        }

        foreach ( $this->_outsource as &$value ) {
            if ( is_numeric( $value ) ) {
                if ( $value == (string)(int)$value ) {
                    $value = (int)$value;
                } elseif ( $value == (string)(float)$value ) {
                    $value = (float)$value;
                }
            }
        }

        return $this->_outsource;

    }

    public function getOpenThreadsCount() {

        $this->_openThreads = $this->cachable( __METHOD__, $this, function ( Jobs_JobStruct $jobStruct ) {

            $dao         = new Comments_CommentDao();
            $openThreads = $dao->setCacheTTL( 60 * 10 )->getOpenThreadsForProjects( [ $jobStruct->id_project ] ); //ten minutes cache
            foreach ( $openThreads as $openThread ) {
                if ( $openThread->id_job == $jobStruct->id && $openThread->password == $jobStruct->password ) {
                    return $openThread->count;
                }
            }

            return 0;

        } );

        return $this->_openThreads;

    }

    /**
     * @return null|Projects_MetadataStruct[]
     */
    public function getProjectMetadata(): ?array {

        return $this->cachable( __function__, $this, function ( $job ) {
            $mDao = new Projects_MetadataDao();

            return $mDao->setCacheTTL( 60 * 60 * 24 * 30 )->allByProjectId( $job->id_project );
        } );

    }

    public function getWarningsCount(): object {

        return $this->cachable( __function__, $this, function ( $jobStruct ) {
            $dao                     = new WarningDao();
            $warningsCount           = $dao->setCacheTTL( 60 * 10 )->getWarningsByProjectIds( [ $jobStruct->id_project ] ) ?? [];
            $ret                     = [];
            $ret[ 'warnings_count' ] = 0;
            foreach ( $warningsCount as $count ) {
                if ( $count->id_job == $jobStruct->id && $count->password == $jobStruct->password ) {
                    $ret[ 'warnings_count' ]   = (int)$count->count;
                    $ret[ 'warning_segments' ] = array_map( function ( $id_segment ) {
                        return (int)$id_segment;
                    }, explode( ",", $count->segment_list ) );
                }
            }

            return (object)$ret;
        } );

    }

    /**
     * @return FileStruct[]
     * @throws ReflectionException
     */
    public function getFiles(): array {
        return FileDao::getByJobId( $this->id );
    }

    /**
     * getProject
     *
     * Returns the project struct, caching the result on the instance to avoid
     * unnecessary queries.
     *
     * @param int $ttl
     *
     * @return Projects_ProjectStruct
     */
    public function getProject( int $ttl = 86400 ): Projects_ProjectStruct {
        return $this->cachable( __function__, $this, function ( $job ) use ( $ttl ) {
            return Projects_ProjectDao::findById( $job->id_project, $ttl );
        } );
    }

    /**
     * @return Jobs_JobStruct[]
     */
    public function getChunks(): array {
        return $this->cachable( __METHOD__, $this, function ( $obj ) {
            return Chunks_ChunkDao::getByJobID( $obj->id );
        } );
    }

    /**
     * @return bool
     */
    public function isSplitted(): bool {

        return count( $this->getChunks() ) > 1;
    }

    /**
     * @param Users_UserStruct $user
     * @param                  $role
     *
     * @return array
     */
    public function getClientKeys( Users_UserStruct $user, $role ): array {
        $uKModel = new UserKeysModel( $user, $role );

        return $uKModel->getKeys( $this->tm_keys, 60 * 10 );
    }

    /**
     * @throws ReflectionException
     */
    public function getPeeForTranslatedSegments(): ?float {
        $pee = round( ( new Jobs_JobDao() )->setCacheTTL( 60 * 15 )->getPeeStats( $this->id, $this->password )->avg_pee, 2 );
        if ( $pee >= 100 ) {
            $pee = null;
        }

        return $pee;
    }

    /**
     *
     * @return float
     */
    public function totalWordsCount(): float {
        return WordCountStruct::loadFromJob( $this )->getRawTotal();
    }

    /**
     * @return bool
     */
    public function isCanceled(): bool {
        return $this->status_owner == Constants_JobStatus::STATUS_CANCELLED;
    }

    /**
     * @return bool
     */
    public function isArchived(): bool {
        return $this->status_owner == Constants_JobStatus::STATUS_ARCHIVED;
    }

    /**
     * @param bool|null $is_review
     *
     * @return $this
     */
    public function setIsReview( ?bool $is_review = false ): Jobs_JobStruct {
        $this->is_review = $is_review;

        return $this;
    }

    /**
     * @param $_revisionNumber
     */
    public function setSourcePage( $_revisionNumber ) {
        $this->_sourcePage = $_revisionNumber;
    }

    /**
     * @return bool
     */
    public function getIsReview(): bool {
        return $this->is_review;
    }

    /**
     * @return bool
     */
    public function isSecondPassReview(): bool {
        return $this->is_review && $this->_sourcePage == 3;
    }

    /**
     * @return bool
     */
    public function isDeleted(): bool {
        return $this->status_owner === Constants_JobStatus::STATUS_DELETED;
    }

    /** @return Segments_SegmentStruct[]
     *
     */
    public function getSegments(): array {
        $dao = new Segments_SegmentDao( Database::obtain() );

        return $dao->getByChunkId( $this->id, $this->password );
    }

    /**
     * @throws Exception
     */
    public function isMarkedComplete( $params ): bool {
        $params = Utils::ensure_keys( $params, [ 'is_review' ] );

        return Chunks_ChunkCompletionEventDao::isCompleted( $this, [ 'is_review' => $params[ 'is_review' ] ] );
    }

    /**
     * @throws ReflectionException
     */
    public function getQualityOverall( array $chunkReviews = [] ): ?string {
        return CatUtils::getQualityOverallFromJobStruct( $this, $chunkReviews );
    }

    public function getErrorsCount(): int {
        $dao = new WarningDao();

        return $dao->getErrorsByChunk( $this );
    }

}
