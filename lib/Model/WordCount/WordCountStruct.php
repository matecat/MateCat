<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 24/02/14
 * Time: 18.55
 *
 */

namespace Model\WordCount;

use JsonSerializable;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataDao;

class WordCountStruct implements JsonSerializable {

    protected int    $id_job;
    protected string $job_password;
    protected float  $new_words        = 0;
    protected float  $draft_words      = 0;
    protected float  $translated_words = 0;
    protected float  $approved_words   = 0;
    protected float  $rejected_words   = 0;
    protected float  $approved2_words  = 0;

    protected float $new_raw_words        = 0;
    protected float $draft_raw_words      = 0;
    protected float $translated_raw_words = 0;
    protected float $approved_raw_words   = 0;
    protected float $approved2_raw_words  = 0;
    protected float $rejected_raw_words   = 0;

    protected ?int    $id_segment = null;
    protected ?string $old_status = null;
    protected ?string $new_status = null;

    protected float $total = 0;

    /**
     * @param JobStruct $jobOrChunk
     *
     * @return WordCountStruct
     */
    public static function loadFromJob( JobStruct $jobOrChunk ): WordCountStruct {
        $wordCountStruct                       = new WordCountStruct();
        $wordCountStruct->id_job               = (int)$jobOrChunk->id;
        $wordCountStruct->job_password         = $jobOrChunk->password;
        $wordCountStruct->new_words            = (float)$jobOrChunk->new_words;
        $wordCountStruct->draft_words          = (float)$jobOrChunk->draft_words;
        $wordCountStruct->translated_words     = (float)$jobOrChunk->translated_words;
        $wordCountStruct->approved_words       = (float)$jobOrChunk->approved_words;
        $wordCountStruct->rejected_words       = (float)$jobOrChunk->rejected_words;
        $wordCountStruct->approved2_words      = (float)$jobOrChunk->approved2_words;
        $wordCountStruct->new_raw_words        = (float)$jobOrChunk->new_raw_words;
        $wordCountStruct->draft_raw_words      = (float)$jobOrChunk->draft_raw_words;
        $wordCountStruct->translated_raw_words = (float)$jobOrChunk->translated_raw_words;
        $wordCountStruct->approved_raw_words   = (float)$jobOrChunk->approved_raw_words;
        $wordCountStruct->approved2_raw_words  = (float)$jobOrChunk->approved2_raw_words;
        $wordCountStruct->rejected_raw_words   = (float)$jobOrChunk->rejected_raw_words;

        return $wordCountStruct;
    }

    /**
     * @param int|null $id_segment
     *
     * @return $this
     */
    public function setIdSegment( ?int $id_segment = null ): WordCountStruct {
        $this->id_segment = $id_segment;

        return $this;
    }

    /**
     * @return int
     */
    public function getIdSegment(): ?int {
        return $this->id_segment;
    }

    /**
     * @param string $status
     *
     * @return $this
     */
    public function setOldStatus( string $status ): WordCountStruct {
        $this->old_status = $status;

        return $this;
    }

    /**
     * @return string
     */
    public function getOldStatus(): ?string {
        return $this->old_status;
    }

    /**
     * @param string $new_status
     *
     * @return $this
     */
    public function setNewStatus( string $new_status ): WordCountStruct {
        $this->new_status = $new_status;

        return $this;
    }

    /**
     * @return null
     */
    public function getNewStatus(): ?string {
        return $this->new_status;
    }


    /**
     * @param float $approved_words
     *
     * @return $this
     */
    public function setApprovedWords( float $approved_words ): WordCountStruct {
        $this->approved_words = $approved_words;

        return $this;
    }

    /**
     * @return float
     */
    public function getApprovedWords(): float {
        return $this->approved_words;
    }

    /**
     * @param float $draft_words
     *
     * @return $this
     */
    public function setDraftWords( float $draft_words ): WordCountStruct {
        $this->draft_words = $draft_words;

        return $this;
    }

    /**
     * @return float
     */
    public function getDraftWords(): float {
        return $this->draft_words;
    }

    /**
     * @param int $id_job
     *
     * @return $this
     */
    public function setIdJob( int $id_job ): WordCountStruct {
        $this->id_job = $id_job;

        return $this;
    }

    /**
     * @return int
     */
    public function getIdJob(): int {
        return $this->id_job;
    }

    /**
     * @param string $job_password
     *
     * @return $this
     */
    public function setJobPassword( string $job_password ): WordCountStruct {
        $this->job_password = $job_password;

        return $this;
    }

    /**
     * @return string
     */
    public function getJobPassword(): string {
        return $this->job_password;
    }

    /**
     * @param float $new_words
     *
     * @return $this
     */
    public function setNewWords( float $new_words ): WordCountStruct {
        $this->new_words = $new_words;

        return $this;
    }

    /**
     * @return float
     */
    public function getNewWords(): float {
        return $this->new_words;
    }

    /**
     * @param float $rejected_words
     *
     * @return $this
     */
    public function setRejectedWords( float $rejected_words ): WordCountStruct {
        $this->rejected_words = $rejected_words;

        return $this;
    }

    /**
     * @return float
     */
    public function getRejectedWords(): float {
        return $this->rejected_words;
    }

    /**
     * @param float $translated_words
     *
     * @return $this
     */
    public function setTranslatedWords( float $translated_words ): WordCountStruct {
        $this->translated_words = $translated_words;

        return $this;
    }

    /**
     * @return float
     */
    public function getTranslatedWords(): float {
        return $this->translated_words;
    }

    /**
     * @return float
     */
    public function getApproved2Words(): float {
        return $this->approved2_words;
    }

    /**
     * @param float $approved2_words
     *
     * @return $this
     */
    public function setApproved2Words( float $approved2_words ): WordCountStruct {
        $this->approved2_words = $approved2_words;

        return $this;
    }

    /**
     * @return float
     */
    public function getNewRawWords(): float {
        return $this->new_raw_words;
    }

    /**
     * @param float $new_raw_words
     *
     * @return $this
     */
    public function setNewRawWords( float $new_raw_words ): WordCountStruct {
        $this->new_raw_words = $new_raw_words;

        return $this;
    }

    /**
     * @return float
     */
    public function getDraftRawWords(): float {
        return $this->draft_raw_words;
    }

    /**
     * @param float $draft_raw_words
     *
     * @return $this
     */
    public function setDraftRawWords( float $draft_raw_words ): WordCountStruct {
        $this->draft_raw_words = $draft_raw_words;

        return $this;
    }

    /**
     * @return float
     */
    public function getTranslatedRawWords(): float {
        return $this->translated_raw_words;
    }

    /**
     * @param float $translated_raw_words
     *
     * @return $this
     */
    public function setTranslatedRawWords( float $translated_raw_words ): WordCountStruct {
        $this->translated_raw_words = $translated_raw_words;

        return $this;
    }

    /**
     * @return float
     */
    public function getApprovedRawWords(): float {
        return $this->approved_raw_words;
    }

    /**
     * @param float $approved_raw_words
     *
     * @return $this
     */
    public function setApprovedRawWords( float $approved_raw_words ): WordCountStruct {
        $this->approved_raw_words = $approved_raw_words;

        return $this;
    }

    /**
     * @return float
     */
    public function getApproved2RawWords(): float {
        return $this->approved2_raw_words;
    }

    /**
     * @param float $approved2_raw_words
     *
     * @return $this
     */
    public function setApproved2RawWords( float $approved2_raw_words ): WordCountStruct {
        $this->approved2_raw_words = $approved2_raw_words;

        return $this;
    }

    /**
     * @return float
     */
    public function getRejectedRawWords(): float {
        return $this->rejected_raw_words;
    }

    /**
     * @param float $rejected_raw_words
     *
     * @return $this
     */
    public function setRejectedRawWords( float $rejected_raw_words ): WordCountStruct {
        $this->rejected_raw_words = $rejected_raw_words;

        return $this;
    }


    /**
     * @return float
     */
    public function getTotal(): float {

        $this->total = (
                $this->new_words +
                $this->draft_words +
                $this->translated_words +
                $this->rejected_words +
                $this->approved_words +
                $this->approved2_words
        );

        return $this->total;
    }

    public function getRawTotal(): float {

        $this->total = (
                $this->new_raw_words +
                $this->draft_raw_words +
                $this->translated_raw_words +
                $this->rejected_raw_words +
                $this->approved_raw_words +
                $this->approved2_raw_words
        );

        return $this->total;
    }

    /**
     * @return array
     */
    public function jsonSerialize() {

        return [
                MetadataDao::WORD_COUNT_EQUIVALENT => [
                        'new'        => $this->new_words,
                        'draft'      => $this->draft_words,
                        'translated' => $this->translated_words,
                        'approved'   => $this->approved_words,
                        'approved2'  => $this->approved2_words,
                        'total'      => $this->getTotal()
                ],
                MetadataDao::WORD_COUNT_RAW        => [
                        'new'        => $this->new_raw_words,
                        'draft'      => $this->draft_raw_words,
                        'translated' => $this->translated_raw_words,
                        'approved'   => $this->approved_raw_words,
                        'approved2'  => $this->approved2_raw_words,
                        'total'      => $this->getRawTotal()
                ]
        ];
    }

    /**
     * @return array
     */
    public function toArray() {
        return [
                MetadataDao::WORD_COUNT_EQUIVALENT => [
                        'new'        => $this->new_words,
                        'draft'      => $this->draft_words,
                        'translated' => $this->translated_words,
                        'approved'   => $this->approved_words,
                        'approved2'  => $this->approved2_words,
                        'total'      => $this->getTotal()
                ],
                MetadataDao::WORD_COUNT_RAW        => [
                        'new'        => $this->new_raw_words,
                        'draft'      => $this->draft_raw_words,
                        'translated' => $this->translated_raw_words,
                        'approved'   => $this->approved_raw_words,
                        'approved2'  => $this->approved2_raw_words,
                        'total'      => $this->getRawTotal()
                ]
        ];
    }

} 