<?php

namespace Features\ReviewImproved\Decorator ;

use LQA\ChunkReviewDao;

class CatDecorator extends \AbstractDecorator {

    /**
     * @var \catController
     */
    protected $controller ;

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

        if ( $this->controller->isRevision() ) {
            // TODO: complete this with the actual URL
            $this->template->quality_report_href = "javascript:void()";
        }

        $this->template->overall_quality_class = $this->getOverallQualityClass();

    }

    private function getOverallQualityClass() {
        $reviews = ChunkReviewDao::findChunkReviewsByChunkIds( array(
            array(
                $this->controller->getJob()->id,
                $this->controller->getJob()->password
            )
        ));

        if ( $reviews[0]->is_pass ) {
            return 'excellent';
        }
        else {
            return 'fail';
        }
    }
}
