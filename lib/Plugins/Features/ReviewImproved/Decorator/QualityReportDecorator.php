<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/11/16
 * Time: 6:20 PM
 */

namespace Features\ReviewImproved\Decorator;

use Features\ReviewImproved\Model\QualityReportModel;
use INIT ;


class QualityReportDecorator {

    /**
     * @var QualityReportModel
     */
    public $model;

    public function __construct( QualityReportModel $model ) {
        $this->model = $model ;
    }

    public function decorate(\PHPTAL $template) {
        $template->basepath = INIT::$BASEURL;
        $template->build_number = INIT::$BUILD_NUMBER;

        $template->project = $this->model->getProject();
        $template->job = $this->model->getChunk()->getJob();
        $template->chunk = $this->model->getChunk();

        $template->project_meta = array_merge(
                array(
                        'vendor' => '',
                        'reviewer' => '',
                        'total_source_words' => ''
                ),
                $this->model->getProject()->getMetadataAsKeyValue()
        );

        $template->job_meta = array(
            'percentage_reviewed' => '',
                'pass_fail_string' => '',
                'score' => ''
        );

        $template->translate_url = $this->getTranslateUrl();

        $template->files = $this->model->getSegmentsStructure();
    }

    private function getTranslateUrl() {
        $chunk = $this->model->getChunk();
        $job = $this->model->getChunk()->getJob();
        $project = $job->getProject();

        $base = INIT::$BASEURL ;

        return "{$base}translate/{$project->name}/" .
        "{$job->source}-{$job->target}/{$job->id}-{$chunk->password}" ;

    }

}