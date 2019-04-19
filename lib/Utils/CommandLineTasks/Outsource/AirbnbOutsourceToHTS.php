<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:14
 */

namespace CommandLineTasks\Outsource;

use API\V2\Json\ProjectUrls;
use Features\Airbnb;
use Features\Airbnb\Utils\Email\ConfirmedQuotationEmail;
use Features\Airbnb\Utils\Email\ErrorQuotationEmail;
use Features\Outsource\Constants\ServiceTypes;


class AirbnbOutsourceToHTS extends AbstractOutsource {

    protected function configure() {

        parent::configure();

        $this
                // the name of the command (the part after "bin/console")
                ->setName( 'outsource:airbnb' );

    }

    protected function _call( \Jobs_JobStruct $job, \Projects_ProjectStruct $project ){

        $projectData = ( new \Projects_ProjectDao() )->getProjectData( $project->id, $project->password );
        $formatted   = new ProjectUrls( $projectData );

        //Let the Feature Class decide about Urls
        $formatted = Airbnb::projectUrls( $formatted );

        $this->config = Airbnb::getConfig();

        $eq_word = \Jobs_JobDao::getTODOWords( $job );

        if( $eq_word != 0 ){
            $eq_word = max( number_format( $eq_word + 0.00000001, 0, "", "" ), 1 );
        }

        if( $this->input->getOption( 'test' ) ){
            $this->output->writeln( "  - Quote would have been sent, Job ID {$job->id} and password {$job->password}. Words: $eq_word" , true );
            return;
        }

        $this->output->writeln( "  - Sending Quote and Confirm, Job ID {$job->id} and password {$job->password}. Words: $eq_word" , true );

        $this->setSuccessMailSender( new ConfirmedQuotationEmail( Airbnb::getPluginBasePath() . '/Features/Airbnb/View/Emails/confirmed_quotation.html' ) );
        $this->setFailureMailSender( new ErrorQuotationEmail( Airbnb::getPluginBasePath() . '/Features/Airbnb/View/Emails/error_quotation.html' ) );

        //Let use project metadata parameters
        $metadataDao            = new \Projects_MetadataDao();
        $quote_pid_append       = @$metadataDao->get( $project->id, Airbnb::REFERENCE_QUOTE_METADATA_KEY )->value;
        $total_batch_word_count = @$metadataDao->get( $project->id, Airbnb::BATCH_WORD_COUNT_METADATA_KEY )->value;

        if( !empty( $quote_pid_append ) ){
            $this->setExternalParentProjectId( $quote_pid_append );
        } else {
            $this->output->writeln( "  - SKIPPED, was the first job in batch Java tool should re-create it ...." );
            return;
        }

        if( !empty( $total_batch_word_count ) ){
            $this->setTotalBatchWordCount( $total_batch_word_count );
        }

        $response = $this->requestJobQuote( $job, $eq_word, $project, $formatted, ServiceTypes::SERVICE_TYPE_PREMIUM );

        if ( !empty( $response ) ) {
            $this->output->writeln( "  - Quote Success, HTS PID: " . $this->getExternalProjectId() . " - Parent HTS PID: $quote_pid_append - Words: $eq_word - Total Batch WordCount $total_batch_word_count", true );
        } else {
            $this->output->writeln( "  - FAILED...." );
        }

    }

    /**
     * @param                         $urls
     * @param \Projects_ProjectStruct $project
     *
     * @return string
     */
    protected function prepareConfirmUrl( $urls, \Projects_ProjectStruct $project ) {

        return "http://www.translated.net/hts/index.php?" . http_build_query( [
                        'f'                => 'confirm',
                        'cid'              => $this->config[ 'translated_username' ],
                        'p'                => $this->config[ 'translated_password' ],
                        'pid'              => $this->external_project_id,
                        'c'                => 1,
                        'of'               => "json",
                        'urls'             => json_encode( $urls ),
                        'append_to_pid'    => ( !empty( $this->external_parent_project_id ) ? $this->external_parent_project_id : null ),
                        'batch_word_count' => ( !empty( $this->total_batch_word_count ) ? $this->total_batch_word_count : null ),
                        'matecat_host'     => parse_url( \INIT::$HTTPHOST, PHP_URL_HOST ),
                        'on_tool'          => !in_array( $project->id_customer, $this->config[ 'airbnb_translated_internal_user' ] )
                ], PHP_QUERY_RFC3986 );

    }

    /**
     * @param \Jobs_JobStruct         $job
     * @param                         $eq_word
     * @param \Projects_ProjectStruct $project
     * @param string                  $service_type
     *
     * @return string
     */
    protected function prepareQuoteUrl( \Jobs_JobStruct $job, $eq_word, \Projects_ProjectStruct $project, $service_type = ServiceTypes::SERVICE_TYPE_PROFESSIONAL ){

        return "http://www.translated.net/hts/index.php?" . http_build_query( [
                        'f'                => 'quote',
                        'cid'              => $this->config[ 'translated_username' ],
                        'p'                => $this->config[ 'translated_password' ],
                        's'                => $job->source,
                        't'                => $job->target,
                        'pn'               => $project->name,
                        'w'                => ( is_null( $eq_word ) ? 0 : $eq_word ),
                        'df'               => 'matecat',
                        'matecat_pid'      => $project->id,
                        'matecat_ppass'    => $project->password,
                        'matecat_pname'    => $project->name,
                        'subject'          => $job->subject,
                        'jt'               => $service_type,
                        'fd'               => 0,
                        'of'               => 'json',
                        'matecat_raw'      => $job->total_raw_wc,
                        'batch_word_count' => ( !empty( $this->total_batch_word_count ) ? $this->total_batch_word_count : null ),
                        'on_tool'          => !in_array( $project->id_customer, $this->config[ 'airbnb_translated_internal_user' ] )
                ], PHP_QUERY_RFC3986 );

    }

}