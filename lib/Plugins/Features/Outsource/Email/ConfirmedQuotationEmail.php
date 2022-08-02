<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 11/04/2018
 * Time: 18:41
 */

namespace Features\Outsource\Email;

use Email\AbstractEmail;
use INIT;

class ConfirmedQuotationEmail extends AbstractEmail {

    protected $title = 'Confirmed Quotation';
    protected $internal_project_id;
    protected $internal_job_id;
    protected $external_project_id;
    protected $project_words_count;
    protected $config;


    public function __construct( $templatePath ) {

        $this->_setLayout( 'skeleton.html' );
        $this->_setTemplateByPath( $templatePath );
    }

    public function setConfig($config){
        $this->config = $config;
    }

    public function send() {
        $this->sendTo( $this->config[ 'success_quotation_email_address' ], "Translated Team" );
    }

    public function setInternalIdProject( $id ) {
        $this->internal_project_id = $id;
    }

    public function setInternalJobId( $id ) {
        $this->internal_job_id = $id;
    }

    public function setExternalProjectId( $id ) {
        $this->external_project_id = $id;
    }

    public function setProjectWordsCount( $count ) {
        $this->project_words_count = $count;
    }

    protected function _getTemplateVariables() {
        return [
                'internal_project_id' => $this->internal_project_id,
                'internal_job_id'     => $this->internal_job_id,
                'external_project_id' => $this->external_project_id,
                'project_words_count' => $this->project_words_count,
        ];
    }

    protected function _getLayoutVariables($messageBody = null) {
        $vars            = parent::_getLayoutVariables();
        $vars[ 'title' ] = $this->title;

        return $vars;
    }


    protected function _getDefaultMailConf() {
        $mailConf = parent::_getDefaultMailConf();

        $mailConf[ 'from' ]       = INIT::$MAILER_RETURN_PATH;
        $mailConf[ 'sender' ]     = INIT::$MAILER_RETURN_PATH;
        $mailConf[ 'returnPath' ] = INIT::$MAILER_RETURN_PATH;

        return $mailConf;
    }
}
