<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 10/04/2018
 * Time: 11:40
 */

namespace Features\Outsource\Traits;

use API\V2\Json\ProjectUrls;
use DataAccess\ShapelessConcreteStruct;
use Email\AbstractEmail;
use Exception;
use Features\Outsource\Constants\ServiceTypes;
use Features\Outsource\Email\ConfirmedQuotationEmail;
use Features\Outsource\Email\ErrorQuotationEmail;
use INIT;
use MultiCurlHandler;
use Outsource\ConfirmationDao;
use Outsource\TranslatedConfirmationStruct;
use Utils;

/**
 * Trait Translated
 *
 * This trait is meant to be used as Features\BaseFeature concrete class
 *
 * @package Features\Outsource\Traits
 */
trait Translated {

    /**
     * @var ConfirmedQuotationEmail
     */
    protected $successEmailObject;

    /**
     * @var ErrorQuotationEmail
     */
    protected $failureEmailObject;

    protected $internal_project_id;
    protected $internal_job_id;
    protected $external_project_id;
    protected $project_words_count;

    protected $external_parent_project_id;

    protected $total_batch_word_count;

    protected $config;

    public function setSuccessMailSender( AbstractEmail $emailObject ) {
        $this->successEmailObject = $emailObject;
    }

    public function setFailureMailSender( AbstractEmail $emailObject ) {
        $this->failureEmailObject = $emailObject;
    }

    /**
     * @param mixed $external_parent_project_id
     */
    public function setExternalParentProjectId( $external_parent_project_id ) {
        $this->external_parent_project_id = $external_parent_project_id;
    }

    /**
     * @param mixed $total_batch_word_count
     *
     * @return $this
     */
    public function setTotalBatchWordCount( $total_batch_word_count ) {
        $this->total_batch_word_count = $total_batch_word_count;

        return $this;
    }

    public function setInternalIdProject( $id ) {
        $this->internal_project_id = $id;
        $this->successEmailObject->setInternalIdProject( $id );
        $this->failureEmailObject->setInternalIdProject( $id );
    }

    public function setInternalJobId( $id ) {
        $this->successEmailObject->setInternalJobId( $id );
        $this->failureEmailObject->setInternalJobId( $id );
        $this->internal_job_id = $id;
    }

    public function setExternalProjectId( $id ) {
        $this->external_project_id = $id;
        $this->successEmailObject->setExternalProjectId( $id );
        $this->failureEmailObject->setExternalProjectId( $id );
    }

    public function setProjectWordsCount( $count ) {
        $this->project_words_count = $count;
        $this->successEmailObject->setProjectWordsCount( $count );
        $this->failureEmailObject->setProjectWordsCount( $count );
    }

    public function getInternalIdProject() {
        return $this->internal_project_id;
    }

    public function getInternalJobId() {
        return $this->internal_job_id;
    }

    public function getExternalProjectId() {
        return $this->external_project_id;
    }

    public function getProjectWordsCount() {
        return $this->project_words_count;
    }

    public function requestProjectQuote( \Projects_ProjectStruct $projectStruct, $_analyzed_report, $service_type = ServiceTypes::SERVICE_TYPE_PROFESSIONAL ) {

        $this->setInternalIdProject( $projectStruct->id );
        $eq_words_count = [];
        foreach ( $_analyzed_report as $job_info ) {
            $eq_words_count[ $job_info[ 'id_job' ] ] = $job_info[ 'eq_wc' ];
        }

        $jobs = ( new \Jobs_JobDao() )->getByProjectId( $projectStruct->id );

        /**
         * @var $projectData ShapelessConcreteStruct[]
         */
        $projectData = ( new \Projects_ProjectDao() )->setCacheTTL( 60 * 60 * 24 )->getProjectData( $projectStruct->id, $projectStruct->password );
        $formatted   = new ProjectUrls( $projectData );

        //Let the Feature Class decide about Urls
        $formatted = $this->projectUrls( $formatted );

        $this->config = self::getConfig();
        $this->failureEmailObject->setConfig($this->config);
        $this->successEmailObject->setConfig($this->config);

        foreach ( $jobs as $job ) {

            $this->requestJobQuote( $job, $eq_words_count[ $job[ 'id' ] ], $projectStruct, $formatted, $service_type );

        }

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

        return "https://www.translated.net/hts/index.php?" . http_build_query( [
                        'f'             => 'quote',
                        'cid'           => $this->config[ 'translated_username' ],
                        'p'             => $this->config[ 'translated_password' ],
                        's'             => $job->source,
                        't'             => $job->target,
                        'pn'            => $project->name,
                        'w'             => ( is_null( $eq_word ) ? 0 : $eq_word ),
                        'df'            => 'matecat',
                        'matecat_pid'   => $project->id,
                        'matecat_ppass' => $project->password,
                        'matecat_pname' => $project->name,
                        'subject'       => $job->subject,
                        'jt'            => $service_type,
                        'fd'            => 0,
                        'of'            => 'json',
                        'matecat_raw'   => $job->total_raw_wc
                ], PHP_QUERY_RFC3986 );

    }

    /**
     *
     * WARNING DO NOT REMOVE : This method expects a Projects_ProjectStruct, this is needed by other plugins
     *
     * @param \Projects_ProjectStruct $project
     * @param                         $urls
     *
     * @return string
     */
    protected function prepareConfirmUrl( $urls, \Projects_ProjectStruct $project ){

        return "https://www.translated.net/hts/index.php?" . http_build_query( [
                        'f'             => 'confirm',
                        'cid'           => $this->config[ 'translated_username' ],
                        'p'             => $this->config[ 'translated_password' ],
                        'pid'           => $this->external_project_id,
                        'c'             => 1,
                        'of'            => "json",
                        'urls'          => json_encode( $urls ),
                        'append_to_pid' => ( !empty( $this->external_parent_project_id ) ? $this->external_parent_project_id : null ),
                        'matecat_host'  => parse_url( INIT::$HTTPHOST, PHP_URL_HOST )
                ], PHP_QUERY_RFC3986 );

    }

    public function requestJobQuote( \Jobs_JobStruct $job, $eq_word, \Projects_ProjectStruct $project, ProjectUrls $formatted_urls, $service_type = ServiceTypes::SERVICE_TYPE_PROFESSIONAL ) {

        if ( $eq_word != 0 ) {
            $eq_word = max( number_format( $eq_word + 0.00000001, 0, "", "" ), 1 );
        }

        $this->setInternalIdProject( $job->id_project );

        $this->setInternalJobId( $job->id );

        $this->setProjectWordsCount( $eq_word );

        /*
         * Build quote URL
         */
        $quote_url = $this->prepareQuoteUrl( $job, $eq_word, $project, $service_type );

        try {
            $quote_response = json_decode( self::request( $quote_url ) );
            Utils::raiseJsonExceptionError();
            if ( $quote_response->code != 1 ) {
                \Log::doJsonLog( $quote_response );
                $this->failureEmailObject->setErrorMessage( $quote_response );
                $this->failureEmailObject->send();
                throw new Exception( $quote_response->message );
            }
        } catch ( Exception $e ) {
            \Log::doJsonLog( $e->getMessage() );
            $this->failureEmailObject->setErrorMessage( $e->getMessage() );
            $this->failureEmailObject->send();

            return;
        }

        $this->setExternalProjectId( $quote_response->pid );

        /** @var $formatted ProjectUrls */
        $urls = $formatted_urls->render( true )[ 'jobs' ][ $job->id ][ 'chunks' ][ $job->password ];

        /*
         * Build confirm URL
         */
        $confirmation_url = $this->prepareConfirmUrl( $urls, $project );

        try {
            $response              = self::request( $confirmation_url );
            $confirmation_response = json_decode( $response );
            Utils::raiseJsonExceptionError();
            if ( $confirmation_response->code != 1 ) {
                throw new Exception( $confirmation_response->message );
            }
            $this->successEmailObject->send();
        } catch ( Exception $e ) {
            \Log::doJsonLog( $e->getMessage() );
            $this->failureEmailObject->setErrorMessage( $e->getMessage() );
            $this->failureEmailObject->send();

            return;
        }

        $confirmationStruct = new TranslatedConfirmationStruct( [
                'id_job'        => $job->id,
                'password'      => $job->password,
                'delivery_date' => $quote_response->delivery_date,
                'price'         => $quote_response->total,
                'quote_pid'     => $quote_response->pid
        ] );
        $cDao               = new ConfirmationDao;
        $cDao->insertStruct( $confirmationStruct, [ 'ignore' => true, 'no_nulls' => true ] );

        $cDao->destroyConfirmationCache( $job );

        return true;

    }

    public static function get_class_name() {
        return ( new \ReflectionClass( get_called_class() ) )->getShortName();
    }

    public static function request( $url ) {

        $mh = new MultiCurlHandler();

        $curlOptions = [
                CURLOPT_HEADER         => 0,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPGET        => true,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_USERAGENT      => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                CURLOPT_CONNECTTIMEOUT => 5
        ];

        $token = $mh->createResource( $url, $curlOptions );

        $mh->multiExec();

        if ( $mh->hasError( $token ) ) {
            $error = $mh->getError( $token );
            throw new Exception( $error[ 'error' ] );
        }

        return $mh->getSingleContent( $token );
    }
}