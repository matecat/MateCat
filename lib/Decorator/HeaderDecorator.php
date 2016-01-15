<?php

/**
 * TODO: so far it's clear that this decorator is based on the job, so the job should
 * so the job should be the argument to the constructor.
 */

class HeaderDecorator {

    private $controller ;
    private $job ;
    private $job_stats ;

    public $mainButtonLabel ;
    public $mainButtonLabelReverse ;
    public $mainButtonId ;
    public $mainButtonClass ;
    public $mainButtonEnabled = true;

    const COMPLETED = 'SENT';
    const MARK_AS_COMPLETE = 'SEND';

    const DOWNLOAD_TRANSLATION = 'DOWNLOAD TRANSLATION';
    const PREVIEW = 'PREVIEW';

    public function __construct( $controller ) {

      $this->controller = $controller;
      $this->job_stats = $controller->getJobStats();
      $this->job = $controller->getJob();

      $this->evalProperties();
    }

    private function evalProperties() {
    }

    private function decorate() {
    }

    private function evalForDefault() {
      $this->mainButtonId = 'downloadProject';
      $this->mainButtonClass = 'downloadtr-button ' . $this->downloadStatus() ;

    }

    // TODO the job itself should know about this

}
