<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/11/16
 * Time: 6:20 PM
 */

namespace Features\ReviewImproved\Decorator;

use Features\QaCheckBlacklist;
use Features\QaCheckGlossary;
use Features\ReviewImproved\Model\QualityReportModel;
use INIT;


class QualityReportDecorator extends \AbstractModelViewDecorator {

    /**
     * @var QualityReportModel
     */
    protected $model;

    private $download_uri ;

    public function __construct( QualityReportModel $model ) {
        $this->model = $model;
    }

    public function setDownloadURI( $uri ) {
        $this->download_uri = $uri ;
    }

    /**
     * @param $template \PHPTAL
     */
    public function decorate( $template ) {
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
           $segment['is_rejected'] =  $segment['status'] == \Constants_TranslationStatus::STATUS_REJECTED;
           $segment['is_translated'] =  !in_array($segment['status'], array(
                   \Constants_TranslationStatus::STATUS_APPROVED,
                    \Constants_TranslationStatus::STATUS_REJECTED
           ));

           if ( $segment['qa_checks'] ) {
               foreach( $segment['qa_checks'] as $check ) {
                   $check['human_scope'] = $this->humanizedScope( $check );
                   $check['message'] = $this->humanizedWarningText( $check );
               }
           }
       }
    }

    public function getFilenameForDownload() {
        $filename = $this->model->getProject()->name .
            "-" . $this->model->getChunk()->id .
            ".html";
        return $filename ;
    }

    private function humanizedScope( $check ) {
        if ( $check['scope'] == QaCheckGlossary::GLOSSARY_SCOPE ) {
            return 'Glossary';
        }
        elseif ( $check['scope'] == QaCheckBlacklist::BLACKLIST_SCOPE ) {
            return 'Blacklist' ;
        }
    }

    private function humanizedWarningText( $check ) {
        $data = json_decode($check['data'], true);
        $message = '' ;
        if ( $check['scope'] == QaCheckGlossary::GLOSSARY_SCOPE ) {
            $source = $data['raw_segment'];
            $target = $data['raw_translation'];
            $message = sprintf( "term <i>\"%s\"</i> not found in translation.", $source);

        }
        elseif ( $check['scope'] == QaCheckBlacklist::BLACKLIST_SCOPE ) {
            $term = $data['match'];
            $message = sprintf( "term <i>\"%s\"</i> found in translation.", $term);
        }

        return $message;
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