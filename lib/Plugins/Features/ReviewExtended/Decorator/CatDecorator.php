<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/11/2017
 * Time: 15:33
 */

namespace Features\ReviewExtended\Decorator;

use LQA\ChunkReviewDao;
use LQA\ModelStruct;

class CatDecorator extends \AbstractDecorator {

    /**
     * @var \catController
     */
    protected $controller;

    /**
     * decorate
     *
     * Adds properties to the view based on the input controller.
     */
    public function decorate() {

        $project = $this->controller->getChunk()->getProject();

        $model = $project->getLqaModel();

        /**
         * TODO: remove this lqa_categories here, this serialization work should be done
         * on the client starting from raw category records.
         */
        $this->template->lqa_categories = (null !== $model ) ? $model->getSerializedCategories() : null;
        $this->template->lqa_flat_categories  = (null !== $model ) ? $this->getCategoriesAsJson( $model ) : '';

        $this->template->review_type          = 'extended';
        $this->template->review_extended      = true;
        $this->template->project_type         = null;
        $this->template->segmentFilterEnabled = true;

        $this->template->quality_report_href = \INIT::$BASEURL . "revise-summary/{$this->controller->getChunk()->id}-{$this->controller->getChunk()->password}";

        $this->template->showReplaceOptionsInSearch = true;

        $this->template->overall_quality_class = $this->getOverallQualityClass();

        $this->assignCatDecorator();
    }

    /**
     * Empty method because it's not necessery to do again what is written into the parent
     */
    protected function decorateForRevision() {
    }

    protected function decorateForTranslate() {
        /**
         * override review_password
         */
        $chunk_review                    = ( new ChunkReviewDao() )->findChunkReviewsForSourcePage( $this->controller->getChunk() )[ 0 ];
        $this->template->review_password = $chunk_review->review_password;

    }

    /**
     * @param ModelStruct $model
     *
     * @return false|string
     */
    private function getCategoriesAsJson( ModelStruct $model ) {
        $categories = $model->getCategories();
        $out        = [];

        foreach ( $categories as $category ) {
            $out[] = $category->toArrayWithJsonDecoded();
        }

        return json_encode( $out, JSON_HEX_APOS );
    }

    private function getOverallQualityClass() {

        $review = ( new ChunkReviewDao() )->findChunkReviewsForSourcePage( $this->controller->getChunk() )[ 0 ];

        if ( $review->is_pass === null ) {
            return '';
        } else {
            if ( $review->is_pass ) {
                return 'excellent';
            } else {
                return 'fail';
            }
        }

    }

}