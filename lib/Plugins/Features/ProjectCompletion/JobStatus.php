<?php


namespace Features\ProjectCompletion ;

class JobStatus implements IProjectCompletionStatus  {

    protected $job ;

    public function __construct( \Jobs_JobStruct $job ) {
        $this->job = $job ;
    }


    public function isCompletable()
    {
        // TODO: Implement isCompletable() method.
    }

    public function isReviewable()
    {
        // TODO: Implement isReviewable() method.
    }

    public function isTranslatable()
    {
        // TODO: Implement isTranslatable() method.
    }

    public function notTranslatableMessage()
    {
        // TODO: Implement notTranslatableMessage() method.
    }
    public function notCompletableMessage()
    {
        // TODO: Implement notCompletableMessage() method.
    }
    public function notReviewableMessage()
    {
        // TODO: Implement notReviewableMessage() method.
    }

}