<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/11/16
 * Time: 6:20 PM
 */

namespace Features\ReviewImproved\Decorator;

use Features\ReviewImproved\Model\QualityReportModel;
use INIT;


class QualityReportDecorator {

    /**
     * @var QualityReportModel
     */
    private $model;

    private $download_uri ;

    public function __construct( QualityReportModel $model ) {
        $this->model = $model;
    }

    public function setDownloadURI( $uri ) {
        $this->download_uri = $uri ;
    }

    public function decorate( \PHPTAL $template ) {
        $template->current_date = strftime('%c', strtotime('now')) ;

        $template->basepath     = INIT::$BASEURL;
        $template->build_number = INIT::$BUILD_NUMBER;

        $template->project = $this->model->getProject();
        $template->job     = $this->model->getChunk()->getJob();
        $template->chunk   = $this->model->getChunk();

        $template->download_uri = $this->download_uri ;

        $template->project_meta = array_merge(
                array(
                        'vendor'             => '',
                        'reviewer'           => '',
                        'total_source_words' => ''
                ),
                $this->model->getProject()->getMetadataAsKeyValue()
        );

        $template->chunk_meta = array(
                'percentage_reviewed' => '',
                'pass_fail_string'    => '',
                'score'               => ''
        );

        $template->translate_url = $this->getTranslateUrl();

        $template->model = $this->model->getStructure();

       foreach($this->model->getAllSegments() as $segment ) {
           $segment['translate_url'] = $this->getTranslateUrl() . '#' . $segment['id'];
           $segment['is_approved'] =  $segment['status'] == \Constants_TranslationStatus::STATUS_APPROVED;
       }
    }

    public function getFilenameForDownload() {
        $filename = $this->model->getProject()->name .
            "-" . $this->model->getChunk()->id .
            ".html";
        return $filename ;
    }

    private function getTranslateUrl() {
        $chunk   = $this->model->getChunk();
        $job     = $this->model->getChunk()->getJob();
        $project = $job->getProject();

        $base = INIT::$BASEURL;

        return "{$base}translate/{$project->name}/" .
        "{$job->source}-{$job->target}/{$job->id}-{$chunk->password}";

    }

}