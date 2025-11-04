<?php

namespace Model\LQA;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\Translations\SegmentTranslationDao;
use ReflectionException;

class EntryStruct extends AbstractDaoSilentStruct implements IDaoStruct
{

    public ?int    $id                  = null;
    public ?int    $uid                 = null;
    public int     $id_segment;
    public int     $id_job;
    public int     $id_category;
    public string  $severity;
    public int     $translation_version = 0;
    public ?int    $start_node          = 0;
    public ?int    $start_offset        = 0;
    public ?int    $end_node            = 0;
    public ?int    $end_offset          = 0;
    public ?int    $is_full_segment     = 0;
    public ?float  $penalty_points      = 0.0;
    public ?string $comment             = null;
    public ?string $create_date         = null;
    public ?string $target_text         = null;
    public int     $source_page;
    public ?string $deleted_at          = null;

    protected mixed $_comments;
    protected mixed $_diff;

    /**
     * @var EntryValidator
     */
    private EntryValidator $validator;

    public function __construct(array $array_params = [])
    {
        parent::__construct($array_params);
        $this->validator = new EntryValidator($this);
    }

    /**
     * @throws ReflectionException
     * @throws ValidationError
     * @throws NotFoundException
     */
    public function ensureValid(): void
    {
        $this->validator->ensureValid();
    }

    public function addComments($comments): void
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
        return $this->_diff;
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
     */
    public function setDefaults(): void
    {
        $this->validator->ensureValid();

        // set the translation reading the version number on the
        // segment translation
        $translation               = SegmentTranslationDao::findBySegmentAndJob($this->id_segment, $this->id_job);
        $this->translation_version = $translation->version_number;

        $this->penalty_points = $this->getPenaltyPoints();
        $this->id_category    = $this->validator->category->id;
    }

    /**
     * @return array|null
     */
    private function getPenaltyPoints(): ?float
    {
        $severities = $this->validator->category->getJsonSeverities();

        foreach ($severities as $severity) {
            if ($severity[ 'label' ] == $this->severity) {
                return $severity[ 'penalty' ];
            }
        }

        return null;
    }


}
