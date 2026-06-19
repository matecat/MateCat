<?php

namespace Model\Jobs;

use ArrayAccess;
use DomainException;
use Exception;
use Model\Comments\CommentDao;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\ArrayAccessTrait;
use Model\DataAccess\Database;
use Model\DataAccess\IDaoStruct;
use Model\Exceptions\NotFoundException;
use Model\Files\FileDao;
use Model\Files\FileStruct;
use Model\Outsource\ConfirmationDao;
use Model\Outsource\ConfirmationStruct;
use Model\Outsource\TranslatedConfirmationStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentStruct;
use Model\TmKeyManagement\UserKeysModel;
use Model\Translations\WarningDao;
use Model\Translators\JobsTranslatorsDao;
use Model\Translators\JobsTranslatorsStruct;
use Model\Users\UserStruct;
use Model\WordCount\WordCountStruct;
use PDOException;
use ReflectionException;
use RuntimeException;
use TypeError;
use Utils\Constants\JobStatus;
use Utils\TmKeyManagement\ClientTmKeyStruct;
use Utils\Tools\CatUtils;

/**
 * @implements ArrayAccess<string, mixed>
 */
class JobStruct extends AbstractDaoSilentStruct implements IDaoStruct, ArrayAccess
{

    use ArrayAccessTrait;

    public ?int $id = null; // null is an accepted value for MySQL autoincrement
    public ?string $password = null;
    public int $id_project;

    public int $job_first_segment;
    public int $job_last_segment;

    public string $source;
    public string $target;
    public string $tm_keys = '[]';
    public ?string $id_translator = null;
    public ?string $job_type = null;
    public int $total_time_to_edit = 0;
    public float $avg_post_editing_effort = 0.0;
    public int $only_private_tm = 0;
    public ?int $last_opened_segment = null;
    public int $id_tms = 1;
    public int $id_mt_engine = 0;
    public ?string $create_date = null;
    public ?string $last_update = null;
    public int $disabled = 0;
    public string $owner = '';
    public string $status_owner = JobStatus::STATUS_ACTIVE;
    public ?string $status_translator = null;
    public string $status = 'active';
    public int $standard_analysis_wc = 0;

    /**
     * Column 'completed' cannot be null, moreover, it is BIT(1), and
     * PDO does not work well in this case without explicitly
     * tell him that this is an INT.
     * So, we can't set 0 because it will be treated as string, set it to false, it works.
     * @see https://bugs.php.net/bug.php?id=50757
     */
    public bool $completed = false; //Column 'completed' cannot be null
    public float $new_words = 0;
    public float $draft_words = 0;
    public float $translated_words = 0;
    public float $approved_words = 0;
    public float $approved2_words = 0;
    public float $rejected_words = 0;

    public int $new_raw_words = 0;
    public int $draft_raw_words = 0;
    public int $translated_raw_words = 0;
    public int $approved_raw_words = 0;
    public int $approved2_raw_words = 0;
    public int $rejected_raw_words = 0;

    public string $subject = '';
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
     * @return array{project_id: ?int, project_name: string, job_id: ?int}
     */
    public function getTMProps(): array
    {
        $projectData = $this->getProject();

        return [
            'project_id' => $projectData->id,
            'project_name' => $projectData->name,
            'job_id' => $this->id,
        ];
    }

    /**
     * @param ?JobsTranslatorsDao $jTranslatorsDao
     * @return ?JobsTranslatorsStruct
     * @throws TypeError
     */
    public function getTranslator(?JobsTranslatorsDao $jTranslatorsDao = null): ?JobsTranslatorsStruct
    {
        $this->_translator = $this->memoize(__METHOD__, function () use ($jTranslatorsDao) {
            $jTranslatorsDao ??= new JobsTranslatorsDao();

            return $jTranslatorsDao->setCacheTTL(60 * 60)->findByJobsStruct($this)[0] ?? null;
        });

        return $this->_translator;
    }

    /**
     * @param ?ConfirmationDao $outsourceDao
     * @return ConfirmationStruct|null
     * @throws DomainException
     * @throws NotFoundException
     * @throws TypeError
     */
    public function getOutsource(?ConfirmationDao $outsourceDao = null): ?ConfirmationStruct
    {
        $this->_outsource = $this->memoize(__METHOD__, function () use ($outsourceDao) {
            $outsourceDao ??= new ConfirmationDao();

            return $outsourceDao->setCacheTTL(60 * 60)->getConfirmation($this);
        });

        $outsource = $this->_outsource;

        if ($outsource === null || empty($outsource->id_vendor)) {
            return null;
        }

        switch ($outsource->id_vendor) {
            case TranslatedConfirmationStruct::VENDOR_ID:
                //Ok Do Nothing
                break;
            default:
                throw new NotFoundException("Vendor id " . $outsource->id_vendor . " not found.");
        }

        $outsourceArray = (array) $outsource;
        foreach ($outsourceArray as &$value) {
            if (is_numeric($value)) {
                if ($value == (string)(int)$value) {
                    $value = (int)$value;
                } elseif ($value == (string)(float)$value) {
                    $value = (float)$value;
                }
            }
        }
        unset($value);

        foreach ($outsourceArray as $key => $value) {
            $outsource->$key = $value;
        }

        $this->_outsource = $outsource;

        return $outsource;
    }

    /**
     * @param ?CommentDao $dao
     * @throws TypeError
     */
    public function getOpenThreadsCount(?CommentDao $dao = null): int
    {
        $this->_openThreads = $this->memoize(__METHOD__, function () use ($dao) {
            $dao ??= new CommentDao();
            $openThreads = $dao->setCacheTTL(60 * 10)->getOpenThreadsForProjects([$this->id_project]); //ten minutes cache
            foreach ($openThreads as $openThread) {
                if ($openThread->id_job == $this->id && $openThread->password == $this->password) {
                    return $openThread->count;
                }
            }

            return 0;
        });

        return $this->_openThreads;
    }

    /**
     * @param ?WarningDao $dao
     * @return object{warnings_count: int, warning_segments?: int[]}
     */
    public function getWarningsCount(?WarningDao $dao = null): object
    {
        return $this->memoize(__METHOD__, function () use ($dao) {
            $dao ??= new WarningDao();
            $warningsCount = $dao->setCacheTTL(60 * 10)->getWarningsByProjectIds([$this->id_project]);
            $ret = [];
            $ret['warnings_count'] = 0;
            foreach ($warningsCount as $count) {
                if ($count->id_job == $this->id && $count->password == $this->password) {
                    $ret['warnings_count'] = (int)$count->count;
                    $ret['warning_segments'] = array_map(function ($id_segment) {
                        return (int)$id_segment;
                    }, explode(",", $count->segment_list));
                }
            }

            return (object)$ret;
        });
    }

      /**
       * @return FileStruct[]
       * @throws Exception
       * @throws ReflectionException
       * @throws RuntimeException
       */
      public function getFiles(): array
     {
         return (new FileDao())->getByJobId($this->id ?? throw new RuntimeException('Missing job id'));
     }

    /**
     * getProject
     *
     * Returns the project struct, caching the result on the instance to avoid
     * unnecessary queries.
     *
     * @param int $ttl
     *
     * @return ProjectStruct
     */
    public function getProject(int $ttl = 86400): ProjectStruct
    {
        return $this->memoize(__METHOD__, function () use ($ttl) {
            return (new ProjectDao())->findById($this->id_project, $ttl);
        });
    }

    /**
     * @param ?JobDao $dao
     * @return JobStruct[]
     * @throws Exception
     */
    public function getChunks(?JobDao $dao = null): array
    {
        $id = $this->id ?? throw new DomainException("Job ID must not be null");

        return $this->memoize(__METHOD__, function () use ($id, $dao) {
            $dao ??= new JobDao();
            return $dao->getNotDeletedById($id);
        });
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isSplitted(): bool
    {
        return count($this->getChunks()) > 1;
    }

    /**
     * @param UserStruct $user
     * @param string     $role
     * @param ?UserKeysModel $uKModel
     *
     * @return array<string, array<int, ClientTmKeyStruct>>
     * @throws Exception
     * @throws TypeError
     */
    public function getClientKeys(UserStruct $user, string $role, ?UserKeysModel $uKModel = null): array
    {
        $uKModel ??= new UserKeysModel($user, $role);

        return $uKModel->getKeys($this->tm_keys, 60 * 10);
    }

    /**
     * @param ?JobDao $dao
     * @return float|null
     * @throws ReflectionException
     * @throws DomainException
     * @throws PDOException
     * @throws Exception
     */
    public function getPeeForTranslatedSegments(?JobDao $dao = null): ?float
    {
        $dao ??= new JobDao();
        $id = $this->id ?? throw new DomainException("Job ID must not be null");
        $password = $this->password ?? throw new DomainException("Job password must not be null");
        $pee = round($dao->setCacheTTL(60 * 15)->getPeeStats($id, $password)->avg_pee ?? 0, 2);
        if ($pee >= 100) {
            $pee = null;
        }

        return $pee;
    }

    /**
     *
     * @return float
     * @throws TypeError
     */
    public function totalWordsCount(): float
    {
        return WordCountStruct::loadFromJob($this)->getRawTotal();
    }

    /**
     * @return bool
     */
    public function isCanceled(): bool
    {
        return $this->status_owner == JobStatus::STATUS_CANCELLED;
    }

    /**
     * @return bool
     */
    public function isArchived(): bool
    {
        return $this->status_owner == JobStatus::STATUS_ARCHIVED;
    }

    /**
     * @param bool $is_review
     *
     * @return $this
     * @throws TypeError
     */
    public function setIsReview(bool $is_review = false): JobStruct
    {
        $this->is_review = $is_review;

        return $this;
    }

    /**
     * @param int $_revisionNumber
     * @throws TypeError
     */
    public function setSourcePage(int $_revisionNumber): void
    {
        $this->_sourcePage = $_revisionNumber;
    }

    /**
     * @return bool
     */
    public function getIsReview(): bool
    {
        return $this->is_review;
    }

    /**
     * @return bool
     */
    public function isSecondPassReview(): bool
    {
        return $this->is_review && $this->_sourcePage == 3;
    }

    /**
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->status_owner === JobStatus::STATUS_DELETED;
    }

    /**
     * @param ?SegmentDao $dao
     * @return SegmentStruct[]
     * @throws PDOException
     * @throws DomainException
     */
    public function getSegments(?SegmentDao $dao = null): array
    {
        $dao ??= new SegmentDao(Database::obtain());
        $id = $this->id ?? throw new DomainException("Job ID must not be null");
        $password = $this->password ?? throw new DomainException("Job password must not be null");

        return $dao->getByChunkId($id, $password);
    }

    /**
     * @param array<int, mixed> $chunkReviews
     * @throws ReflectionException
     * @throws Exception
     */
    public function getQualityOverall(array $chunkReviews = [], ?CatUtils $catUtils = null): ?string
    {
        return ($catUtils ?? new CatUtils())->getQualityOverallFromJobStruct($this, $chunkReviews);
    }

    /**
     * @param ?WarningDao $dao
     * @throws PDOException
     */
    public function getErrorsCount(?WarningDao $dao = null): int
    {
        $dao ??= new WarningDao();

        return $dao->getErrorsByChunk($this);
    }

}
