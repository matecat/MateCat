<?php

namespace TemplateDecorator;

use Jobs_JobStruct;
use LQA\ChunkReviewStruct;
use WordCount\WordCountStruct;

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 18/06/25
 * Time: 16:37
 *
 */
class CatDecoratorArguments implements ArgumentInterface {

    private Jobs_JobStruct     $job;
    private ?ChunkReviewStruct $chunkReview;
    private bool               $isRevision;

    private WordCountStruct $wordCountStruct;

    /**
     * @param Jobs_JobStruct         $job
     * @param ChunkReviewStruct|null $chunkReview
     * @param bool                   $isRevision
     */
    public function __construct( Jobs_JobStruct $job, bool $isRevision, WordCountStruct $wordCountStruct, ?ChunkReviewStruct $chunkReview = null ) {
        $this->job             = $job;
        $this->chunkReview     = $chunkReview;
        $this->isRevision      = $isRevision;
        $this->wordCountStruct = $wordCountStruct;
    }

    public function getJob(): Jobs_JobStruct {
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