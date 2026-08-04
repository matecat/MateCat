<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/03/2019
 * Time: 12:30
 */

namespace Plugins\Features\ReviewExtended;

use Exception;
use InvalidArgumentException;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ModelStruct;
use ReflectionException;
use Utils\Constants\SourcePages;
use Utils\Constants\TranslationStatus;

class ReviewUtils
{
    private ChunkReviewDao $chunkReviewDao;

    public function __construct(ChunkReviewDao $chunkReviewDao)
    {
        $this->chunkReviewDao = $chunkReviewDao;
    }

    /**
     * @param int|null $number
     *
     * @return string|null
     */
    public static function sourcePageToTranslationStatus(int $number = null): ?string
    {
        $statuses = [
            SourcePages::SOURCE_PAGE_TRANSLATE => TranslationStatus::STATUS_TRANSLATED,
            SourcePages::SOURCE_PAGE_REVISION => TranslationStatus::STATUS_APPROVED,
            SourcePages::SOURCE_PAGE_REVISION_2 => TranslationStatus::STATUS_APPROVED2
        ];

        return empty($number) ? null : ($statuses[$number] ?? null);
    }

    /**
     * Resolve the revision phase from the credential the caller presented instead of from a request
     * parameter. The password is matched against the job's translate password and against each
     * review password of the chunk, so the answer is the phase the caller can prove they are in.
     *
     * An unresolvable pair yields the translate phase: the least privileged answer is the only safe
     * default, since a wrong guess in the other direction would grant a revision phase for free.
     *
     * @param int $id_job
     * @param string $password The password presented for this request, not one echoed by the client
     * @param int $ttl Left at 0 on purpose: a cached answer would keep resolving a rotated review
     *                 password to its old phase for the whole cache lifetime
     *
     * @return int One of the SourcePages constants
     * @throws Exception
     * @throws ReflectionException
     */
    public function sourcePageFromIdJobAndPassword(int $id_job, string $password, int $ttl = 0): int
    {
        if ($password === '') {
            return SourcePages::SOURCE_PAGE_TRANSLATE;
        }

        /** @var ShapelessConcreteStruct|null $roles */
        $roles = $this->chunkReviewDao->isTOrR1OrR2($id_job, $password, $ttl);

        if ($roles === null) {
            return SourcePages::SOURCE_PAGE_TRANSLATE;
        }

        if (!empty($roles->r2)) {
            return SourcePages::SOURCE_PAGE_REVISION_2;
        }

        if (!empty($roles->r1)) {
            return SourcePages::SOURCE_PAGE_REVISION;
        }

        return SourcePages::SOURCE_PAGE_TRANSLATE;
    }

    /**
     * @deprecated Backend should't be instgructed by the front-end about the revision level, this is an internal. It muist be retrieved by the password url.
     *             Use sourcePageFromIdJobAndPassword() instead, which derives the phase from the credential.
     *
     * @param int|null $number
     *
     * @return int
     * @throws InvalidArgumentException When the number does not name an existing revision phase
     */
    public static function revisionNumberToSourcePage(?int $number = null): int
    {
        if (empty($number)) {
            return SourcePages::SOURCE_PAGE_TRANSLATE;
        }

        $sourcePage = $number + 1;

        if ($sourcePage < SourcePages::SOURCE_PAGE_REVISION || $sourcePage > SourcePages::SOURCE_PAGE_REVISION_2) {
            throw new InvalidArgumentException('Invalid revision number ' . $number);
        }

        return $sourcePage;
    }

    /**
     * @param ?int $number
     *
     * @return ?int
     */
    public static function sourcePageToRevisionNumber(int $number = null): ?int
    {
        return (((int)$number - 1) < 1) ? null : $number - 1;
    }

    /**
     * @param ModelStruct $lqaModel
     * @param int $sourcePage
     *
     * @return int
     * @throws Exception
     */
    public static function filterLQAModelLimit(ModelStruct $lqaModel, int $sourcePage): int
    {
        $limit = $lqaModel->getLimit();

        /**
         * Limit array index equals to $source_page -2.
         */
        $value = $limit[$sourcePage - 2] ?? end($limit);

        return (int)$value;
    }

    /**
     * @param JobStruct $chunk
     *
     * @return int[]
     * @throws Exception
     */
    public function validRevisionNumbers(JobStruct $chunk): array
    {
        $chunkReviews = $this->chunkReviewDao->findChunkReviews($chunk);

        return array_values(array_filter(array_map(function ($chunkReview) {
            return self::sourcePageToRevisionNumber($chunkReview->source_page);
        }, $chunkReviews)));
    }
}