<?php

namespace Features\ReviewImproved ;

class CatDecorator extends \AbstractDecorator {

    /**
     * decorate
     *
     * Adds properties to the view based on the input controller.
     */
    public function decorate() {

        $project = $this->controller->getJob()->getProject();
        $model = $project->getLqaModel() ;

        $this->template->lqa_categories = $model->getSerializedCategories();

        $this->template->review_type = 'improved';
        $this->template->review_improved = true;

        $this->template->footer_show_revise_link = false;

    }
}
