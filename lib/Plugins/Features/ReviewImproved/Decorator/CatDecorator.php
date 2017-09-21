<?php

namespace Features\ReviewImproved\Decorator ;

use LQA\ChunkReviewDao;
use LQA\ModelStruct;

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

        $project = $this->controller->getChunk()->getProject();

        $model = $project->getLqaModel() ;

        /**
         * TODO: remove this lqa_categories here, this serialization work should be done
         * on the client starting from raw category records.
         */
        $this->template->lqa_categories = $model->getSerializedCategories();

        $this->template->lqa_flat_categories = $this->getCategoriesAsJson($model);
        $this->template->review_type = 'improved';
        $this->template->review_improved = true;
        $this->template->project_type = null;
        $this->template->segmentFilterEnabled = true;

        $this->template->footer_show_revise_link = false;


        // TODO: complete this with the actual URL
        $this->template->quality_report_href = \Routes::pluginsBase() .
            "/review_improved/quality_report/" .
            "{$this->controller->getChunk()->id}/" .
            "{$this->controller->getChunk()->password}";

        if ( $this->controller->isRevision() ) {
            $this->template->showReplaceOptionsInSearch = false ;
        }

        $this->template->overall_quality_class = $this->getOverallQualityClass();
    }

    private function getCategoriesAsJson(ModelStruct $model) {
        $categories = $model->getCategories();
        $out = array();

        foreach($categories as $category) {
            $out[] = $category->toArrayWithJsonDecoded();
        }

        return json_encode( $out );
    }

    private function getOverallQualityClass() {
        $reviews = ChunkReviewDao::findChunkReviewsByChunkIds( array(
            array(
                $this->controller->getChunk()->id,
                $this->controller->getChunk()->password
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
