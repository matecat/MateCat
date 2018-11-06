<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:14
 */

namespace CommandLineTasks\Outsource;

use API\V2\Json\ProjectUrls;
use Features\Microsoft;
use Features\Microsoft\Utils\Email\ConfirmedQuotationEmail;
use Features\Microsoft\Utils\Email\ErrorQuotationEmail;
use Features\Outsource\Constants\ServiceTypes;


class MicrosoftOutsourceToHTS extends AbstractOutsource {

    protected function configure() {

        parent::configure();

        $this
                // the name of the command (the part after "bin/console")
                ->setName( 'outsource:microsoft' );

    }

    protected function _call( \Jobs_JobStruct $job, \Projects_ProjectStruct $project ){

        $projectData = ( new \Projects_ProjectDao() )->getProjectData( $project->id, $project->password );
        $formatted   = new ProjectUrls( $projectData );

        //Let the Feature Class decide about Urls
        $formatted = Microsoft::projectUrls( $formatted );

        $this->config = Microsoft::getConfig();

        $eq_word = \Jobs_JobDao::getTODOWords( $job );

        if( $eq_word != 0 ){
            $eq_word = max( number_format( $eq_word + 0.00000001, 0, "", "" ), 1 );
        }

        if( $this->input->getOption( 'test' ) ){
            $this->output->writeln( "  - Quote would have been sent, Job ID {$job->id} and password {$job->password}. Words: $eq_word" , true );
            return;
        }

        $this->output->writeln( "  - Sending Quote and Confirm, Job ID {$job->id} and password {$job->password}. Words: $eq_word" , true );

        $this->setSuccessMailSender( new ConfirmedQuotationEmail( Microsoft::getPluginBasePath() . '/Features/Microsoft/View/Emails/confirmed_quotation.html' ) );
        $this->setFailureMailSender( new ErrorQuotationEmail( Microsoft::getPluginBasePath() . '/Features/Microsoft/View/Emails/error_quotation.html' ) );

        $response = $this->requestJobQuote( $job, $eq_word, $project, $formatted, ServiceTypes::SERVICE_TYPE_PROFESSIONAL );

        if ( !empty( $response ) ) {
            $this->output->writeln( "  - Quote Success, HTS PID: " . $this->getExternalProjectId() . " - Words: $eq_word", true );
        } else {
            $this->output->writeln( "  - FAILED...." );
        }

    }

    /**
     * @param \Jobs_JobStruct         $job
     * @param                         $eq_word
     * @param \Projects_ProjectStruct $project
     *
     * @return string
     */
    protected function prepareQuoteUrl( \Jobs_JobStruct $job, $eq_word, \Projects_ProjectStruct $project ){

        if( $project->id_customer == $this->config[ 'microsoft_user1' ] ){
            $hts_user = $this->config[ 'translated_username_pilot1' ];
            $hts_pass = $this->config[ 'translated_password_pilot1' ];
        } elseif( $project->id_customer == $this->config[ 'microsoft_user2' ] ) {
            $hts_user = $this->config[ 'translated_username_pilot2' ];
            $hts_pass = $this->config[ 'translated_password_pilot2' ];
        } else {
            $hts_user = 'microsoftdemo';
            $hts_pass = 'microsoftdemo';
        }

        return "http://www.translated.net/hts/index.php?" . http_build_query( [
                        'f'             => 'quote',
                        'cid'           => $hts_user,
                        'p'             => $hts_pass,
                        's'             => $job->source,
                        't'             => $job->target,
                        'pn'            => $project->name,
                        'w'             => $eq_word,
                        'df'            => 'matecat',
                        'matecat_pid'   => $project->id,
                        'matecat_ppass' => $project->password,
                        'matecat_pname' => $project->name,
                        'subject'       => $job->subject,
                        'jt'            => ServiceTypes::SERVICE_TYPE_PROFESSIONAL,
                        'fd'            => 0,
                        'of'            => 'json',
                        'matecat_raw'   => $job->total_raw_wc
                ], PHP_QUERY_RFC3986 );

    }

    /**
     * @param                         $urls
     * @param \Projects_ProjectStruct $project
     *
     * @return string
     */
    protected function prepareConfirmUrl( $urls, \Projects_ProjectStruct $project ){

        if( $project->id_customer == $this->config[ 'microsoft_user1' ] ){
            $hts_user = $this->config[ 'translated_username_pilot1' ];
            $hts_pass = $this->config[ 'translated_password_pilot1' ];
        } elseif( $project->id_customer == $this->config[ 'microsoft_user2' ] ) {
            $hts_user = $this->config[ 'translated_username_pilot2' ];
            $hts_pass = $this->config[ 'translated_password_pilot2' ];
        } else {
            $hts_user = 'microsoftdemo';
            $hts_pass = 'microsoftdemo';
        }

        return "http://www.translated.net/hts/index.php?" . http_build_query( [
                        'f'             => 'confirm',
                        'cid'           => $hts_user,
                        'p'             => $hts_pass,
                        'pid'           => $this->external_project_id,
                        'c'             => 1,
                        'of'            => "json",
                        'urls'          => json_encode( $urls ),
                        'append_to_pid' => ( !empty( $this->external_parent_project_id ) ? $this->external_parent_project_id : null ),
                        'matecat_host'  => parse_url( \INIT::$HTTPHOST, PHP_URL_HOST )
                ], PHP_QUERY_RFC3986 );

    }

}