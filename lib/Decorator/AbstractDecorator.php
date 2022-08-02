<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 05/11/15
 * Time: 11.56
 */

use AbstractControllers\IController;

/**
 * Class AbstractDecorator
 *
 * This class represents the first attempt to move some view logic from the controller.
 * A model was generally not present in controllers, so this class takes the controller
 * itself as parameter, and assigns instance variables to the view as needed.
 *
 * Newer controller implementation make use of a model, making this implementation obsolete.
 *
 * @see AbstractViewModelDecorator
 *
 */
abstract class AbstractDecorator {

    protected $controller;

    /**
     * @var PHPTAL
     */
    protected $template;

    public function __construct( IController $controller, PHPTAL $template = null ) {
        $this->controller = $controller;
        $this->template   = $template;
    }

    protected function assignCatDecorator(){
        if ( $this->controller->isRevision() ) {
            $this->decorateForRevision();
        } else {
            $this->decorateForTranslate();
        }
    }

    protected function decorateForRevision() {
        $this->template->footer_show_revise_link    = false;
        $this->template->footer_show_translate_link = true;
        $this->template->footer_show_editlog_link = false;
        $this->template->review_class               = 'review';
        $this->template->review_type                = 'simple';

        // TODO: move this logic in javascript QualityReportButton component
        if ( $this->controller->getQaOverall() == 'fail' ||
                $this->controller->getQaOverall() == 'poor'
        ) {
            $this->template->header_quality_report_item_class = 'hide';
        }

        $this->template->password        = $this->controller->getPassword();
        $this->template->review_password = $this->controller->getReviewPassword();
        $this->template->job_is_splitted = var_export($this->controller->isJobSplitted(), true);

    }

    protected function decorateForTranslate() {

        $this->template->footer_show_revise_link    = true;
        $this->template->footer_show_translate_link = false;
        $this->template->footer_show_editlog_link = false;
        $this->template->review_class               = '';
        $this->template->review_type                = 'simple';

        $this->template->password        = $this->controller->getPassword();
        $this->template->review_password = $this->controller->getPassword();
        $this->template->job_is_splitted = var_export($this->controller->isJobSplitted(), true);
    }

    public abstract function decorate();
}