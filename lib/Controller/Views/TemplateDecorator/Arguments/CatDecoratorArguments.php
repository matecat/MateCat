<?php

namespace Controller\Views\TemplateDecorator\Arguments;

use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewStruct;
use Model\WordCount\WordCountStruct;

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 18/06/25
 * Time: 16:37
 *
 */
class CatDecoratorArguments implements ArgumentInterface {

    private JobStruct          $job;
    private ?ChunkReviewStruct $chunkReview;
    private bool               $isRevision;
    private WordCountStruct    $wordCountStruct;

    /**
     * @param JobStruct              $job
     * @param bool                   $isRevision
     * @param WordCountStruct        $wordCountStruct
     * @param ChunkReviewStruct|null $chunkReview
     */
    public function __construct( JobStruct $job, bool $isRevision, WordCountStruct $wordCountStruct, ?ChunkReviewStruct $chunkReview = null ) {
        $this->job             = $job;
        $this->chunkReview     = $chunkReview;
        $this->isRevision      = $isRevision;
        $this->wordCountStruct = $wordCountStruct;
    }

    public function getJob(): JobStruct {
        return $this->job;
    }

    public function getChunkReview(): ?ChunkReviewStruct {
        return $this->chunkReview;
    }

    public function isRevision(): bool {
        return $this->isRevision;
    }

    public function getWordCountStruct(): WordCountStruct {
        return $this->wordCountStruct;
    }

}