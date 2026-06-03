<?php

namespace Model\LQA;

use Exception;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\Translations\SegmentTranslationDao;
use ReflectionException;
use TypeError;

class EntryStruct extends AbstractDaoSilentStruct implements IDaoStruct
{

    public ?int $id = null;
    public ?int $uid = null;
    public int $id_segment;
    public int $id_job;
    public int $id_category;
    public string $severity;
    public int $translation_version = 0;
    public ?int $start_node = 0;
    public ?int $start_offset = 0;
    public ?int $end_node = 0;
    public ?int $end_offset = 0;
    public ?int $is_full_segment = 0;
    public ?float $penalty_points = 0.0;
    public ?string $comment = null;
    public ?string $create_date = null;
    public ?string $target_text = null;
    public int $source_page;
    public ?string $deleted_at = null;

    protected mixed $_comments;
    protected mixed $_diff;

    private EntryValidator $validator;
    private SegmentTranslationDao $segmentTranslationDao;

    public function __construct(
        array $array_params = [],
        ?EntryValidator $validator = null,
        ?SegmentTranslationDao $segmentTranslationDao = null
    ) {
        parent::__construct($array_params);
        $this->validator = $validator ?? new EntryValidator($this);
        $this->segmentTranslationDao = $segmentTranslationDao ?? new SegmentTranslationDao();
    }

    /**
     * @throws ReflectionException
     * @throws ValidationError
     * @throws NotFoundException
     * @throws Exception
     */
    public function ensureValid(): void
    {
        $this->validator->ensureValid();
    }

    public function addComments(mixed $comments): void
    {
        $this->_comments = $comments;
    }

    /**
     * @return mixed
     */
    public function getComments(): mixed
    {
        return $this->_comments;
    }

    /**
     * @return mixed
     */
    public function getDiff(): mixed
    {
        return $this->_diff ?? null;
    }

    /**
     * @param mixed $diff
     */
    public function setDiff($diff): EntryStruct
    {
        $this->_diff = $diff;

        return $this;
    }

     /**
      * @throws ValidationError
      * @throws NotFoundException
      * @throws ReflectionException
      * @throws Exception
      * @throws TypeError
      */
     public function setDefaults(): void
    {
        $this->validator->ensureValid();

        // set the translation reading the version number on the
        // segment translation
        $translation = $this->segmentTranslationDao->findBySegmentAndJob($this->id_segment, $this->id_job);
        if ($translation === null) {
            throw new NotFoundException('Segment translation not found');
        }
        $this->translation_version = $translation->version_number ?? 0;

        $this->penalty_points = $this->getPenaltyPoints();

        $category = $this->validator->category;
        if ($category === null) {
            throw new NotFoundException('Category not found after validation');
        }
        $this->id_category = $category->id ?? throw new NotFoundException('Category id is null');
    }

    /**
     * Right-to-left selections may provide nodes/offsets in reverse order; silently reorder so clients don't have to.
     */
    public function ensureStartAndStopPositionAreOrdered(): void
    {

        if ($this->start_node == $this->end_node) {
            if (intval($this->start_offset) > intval($this->end_offset)) {
                $tmp = $this->start_offset;
                $this->start_offset = $this->end_offset;
                $this->end_offset = $tmp;
                unset($tmp);
            }
        } elseif (intval($this->start_node) > intval($this->end_node)) {
            $tmp = $this->start_offset;
            $this->start_offset = $this->end_offset;
            $this->end_offset = $tmp;

            $tmp = $this->start_node;
            $this->start_node = $this->end_node;
            $this->end_node = $tmp;
        } else {
            // in any other case leave everything as is
        }

    }

    /**
     * @return float|null
     */
    private function getPenaltyPoints(): ?float
    {
        $category = $this->validator->category;
        if ($category === null) {
            return null;
        }
        $severities = $category->getJsonSeverities();

        foreach ($severities as $severity) {
            if ($severity['label'] == $this->severity) {
                return $severity['penalty'];
            }
        }

        return null;
    }


}
