<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 13/09/2018
 * Time: 16:16
 */

namespace View\API\V3\Json;

use Exception;
use DomainException;
use Matecat\Locales\LanguageDomains;
use Matecat\Locales\Languages;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;
use Model\WordCount\WordCountStruct;
use Plugins\Features\ReviewExtended\ReviewUtils;
use ReflectionException;
use Utils\Constants\SourcePages;
use Utils\Tools\Utils;
use View\API\App\Json\OutsourceConfirmation;
use View\API\V2\Json\JobTranslator;

class Chunk extends \View\API\V2\Json\Chunk
{

    /**
     * @var ChunkReviewStruct[]
     */
    protected array $chunk_reviews = [];
    protected JobStruct $chunk;

    private JobDao $jobDao;
    private ChunkReviewDao $chunkReviewDao;

    public function __construct(?JobDao $jobDao = null, ?ChunkReviewDao $chunkReviewDao = null)
    {
        $this->jobDao = $jobDao ?? new JobDao();
        $this->chunkReviewDao = $chunkReviewDao ?? new ChunkReviewDao();
    }

    /**
     * @param JobStruct $chunk
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     * @throws NotFoundException
     * @throws \TypeError
     */
    public function renderOne(JobStruct $chunk): array
    {
        $project = $chunk->getProject();
        $featureSet = $project->getFeaturesSet();

        return [
            'job' => [
                'id' => (int)$chunk->id,
                'chunks' => [$this->renderItem($chunk, $project, $featureSet)]
            ]
        ];
    }

    /**
     * @param JobStruct      $chunk
     * @param ProjectStruct  $project
     * @param FeatureSet     $featureSet
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     * @throws \TypeError
     */
    public function renderItem(JobStruct $chunk, ProjectStruct $project, FeatureSet $featureSet): array
    {
        $this->chunk = $chunk;
        $outsourceInfo = $chunk->getOutsource();
        $tStruct = $chunk->getTranslator();
        $outsource = null;
        $translator = null;
        if (!empty($outsourceInfo)) {
            $outsource = (new OutsourceConfirmation($outsourceInfo))->render();
        } else {
            $translator = (!empty($tStruct) ? (new JobTranslator($tStruct))->renderItem() : null);
        }

        $jobStats = WordCountStruct::loadFromJob($chunk);

        $lang_handler = Languages::getInstance();

        $subject_handler = LanguageDomains::getInstance();
        $subjectsHashMap = $subject_handler->getEnabledHashMap();

        $warningsCount = $chunk->getWarningsCount();

        $result = [
            'id' => (int)$chunk->id,
            'password' => $chunk->password,
            'source' => $chunk->source,
            'target' => $chunk->target,
            'sourceTxt' => $lang_handler->getLocalizedName($chunk->source),
            'targetTxt' => $lang_handler->getLocalizedName($chunk->target),
            'status' => $chunk->status_owner,
            'subject' => $chunk->subject,
            'subject_printable' => $subjectsHashMap[$chunk->subject],
            'owner' => $chunk->owner,
            'time_to_edit' => $this->getTimeToEditArray($chunk->id),
            'total_time_to_edit' => $chunk->total_time_to_edit,
            'avg_post_editing_effort' => (float)$chunk->avg_post_editing_effort,
            'open_threads_count' => (int)$chunk->getOpenThreadsCount(),
            'created_at' => Utils::api_timestamp($chunk->create_date),
            'pee' => $chunk->getPeeForTranslatedSegments(),
            'private_tm_key' => $this->getKeyList($chunk),
            'warnings_count' => $warningsCount->warnings_count,
            'warning_segments' => ($warningsCount->warning_segments ?? []),
            'stats' => $jobStats,
            'outsource' => $outsource,
            'translator' => $translator,
            'total_raw_wc' => $chunk->total_raw_wc,
            'standard_wc' => (float)$chunk->standard_analysis_wc,
        ];


        $chunkReviewsList = $this->getChunkReviews();

        $result = array_merge($result, $this->renderQualitySummary($chunk, $project, $chunkReviewsList));

        foreach ($chunkReviewsList as $chunkReview) {
            $result = static::populateRevisePasswords($chunkReview, $result);
        }

        return $this->fillUrls($result, $chunk, $project, $featureSet);
    }

    /**
     * @return ChunkReviewStruct[]
     *
     * @throws Exception
     * @throws ReflectionException
     */
    protected function getChunkReviews(): array
    {
        if (empty($this->chunk_reviews)) {
            $this->chunk_reviews = $this->chunkReviewDao->findChunkReviews($this->chunk);
        }

        return $this->chunk_reviews;
    }

    /**
     * @param ChunkReviewStruct[] $chunk_reviews
     *
     * @return Chunk
     */
    public function setChunkReviews(array $chunk_reviews): Chunk
    {
        $this->chunk_reviews = $chunk_reviews;

        return $this;
    }

    /**
     * @param JobStruct           $chunk
     * @param ProjectStruct       $project
     * @param ChunkReviewStruct[] $chunkReviewsList
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    protected function renderQualitySummary(JobStruct $chunk, ProjectStruct $project, array $chunkReviewsList): array
    {
        return (new QualitySummary($chunk, $project))->render($chunkReviewsList);
    }

    /**
     * @param int $chunk_id
     *
     * @return array{total: int, t: int, r1: int, r2: int}
     *
     * @throws DomainException
     * @throws Exception
     * @throws ReflectionException
     */
    protected function getTimeToEditArray(int $chunk_id): array
    {
        $tteT = (int)$this->jobDao->getTimeToEdit($chunk_id, 1)['tte'];
        $tteR1 = (int)$this->jobDao->getTimeToEdit($chunk_id, 2)['tte'];
        $tteR2 = (int)$this->jobDao->getTimeToEdit($chunk_id, 3)['tte'];
        $tteTotal = $tteT + $tteR1 + $tteR2;

        return [
            'total' => $tteTotal,
            't' => $tteT,
            'r1' => $tteR1,
            'r2' => $tteR2,
        ];
    }

    /**
     * @param ChunkReviewStruct    $chunk_review
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    protected static function populateRevisePasswords(ChunkReviewStruct $chunk_review, array $result): array
    {
        if (!isset($result['revise_passwords'])) {
            $result['revise_passwords'] = [];
        }

        if ($chunk_review->source_page <= SourcePages::SOURCE_PAGE_REVISION) {
            $result['revise_passwords'][] = [
                'revision_number' => 1,
                'password' => $chunk_review->review_password
            ];
        } else {
            $result['revise_passwords'][] = [
                'revision_number' => ReviewUtils::sourcePageToRevisionNumber($chunk_review->source_page),
                'password' => $chunk_review->review_password
            ];
        }

        return $result;
    }

}