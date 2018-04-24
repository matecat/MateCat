<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 10/04/2018
 * Time: 11:40
 */

namespace Features\Outsource\Traits;

use Email\AbstractEmail;
use MultiCurlHandler;
use Outsource\TranslatedConfirmationStruct;
use Outsource\ConfirmationDao;
use \Exception;
use Utils;
use \INIT;

trait Translated {

    protected $successEmailObject;
    protected $failureEmailObject;

    public function setSuccessMailSender( AbstractEmail $emailObject ) {
        $this->successEmailObject = $emailObject;
    }

    public function setFailureMailSender( AbstractEmail $emailObject ) {
        $this->failureEmailObject = $emailObject;
    }

    public function requestQuote( $project_id ) {

        $jobs = ( new \Jobs_JobDao() )->getByProjectId( $project_id, 3600 );
        /** @var $jobs \Jobs_JobStruct[] */
        $project = $jobs[ 0 ]->getProject();

        $config = self::getConfig();

        foreach ( $jobs as $job ) {

            $quote_url = "http://www.translated.net/hts/index.php?" . http_build_query( [
                            'f'             => 'quote',
                            'cid'           => $config[ 'translated_username' ],
                            'p'             => $config[ 'translated_password' ],
                            's'             => $job[ 'source' ],
                            't'             => $job[ 'target' ],
                            'pn'            => strtoupper( self::get_class_name() ) . "-{$job['id']}-{$job['password']}",
                            'w'             => $job[ 'total_raw_wc' ],
                            'df'            => 'matecat',
                            'matecat_pid'   => $project->id,
                            'matecat_ppass' => $project->password,
                            'matecat_pname' => $project->name,
                            'subject'       => $job[ 'subject' ],
                            'jt'            => 'T',
                            'fd'            => 0,
                            'of'            => 'json'
                    ], PHP_QUERY_RFC3986 );

            try {
                $quote_response = json_decode( self::request( $quote_url ) );
                Utils::raiseJsonExceptionError();
                if ( $quote_response->code != 1 ) {
                    throw new Exception( $quote_response->message );
                }
            } catch ( Exception $e ) {
                $this->failureEmailObject->setErrorMessage( $e->getMessage() );
                $this->failureEmailObject->send();

                return;
            }

            $confirmation_url = "http://www.translated.net/hts/index.php?" . http_build_query( [
                            'f'   => 'confirm',
                            'cid' => $config[ 'translated_username' ],
                            'p'   => $config[ 'translated_password' ],
                            'pid' => $quote_response->pid,
                            'c'   => 1,
                            'of'  => "json"
                    ], PHP_QUERY_RFC3986 );

            try {
                $response              = self::request( $confirmation_url );
                $confirmation_response = json_decode( $response );
                Utils::raiseJsonExceptionError();
                if ( $confirmation_response->code != 1 ) {
                    throw new Exception( $confirmation_response->message );
                }
                $this->successEmailObject->send();
            } catch ( Exception $e ) {
                $this->failureEmailObject->setErrorMessage( $e->getMessage() );
                $this->failureEmailObject->send();

                return;
            }

            $confirmationStruct = new TranslatedConfirmationStruct( [
                    'id_job'        => $job[ 'id' ],
                    'password'      => $job[ 'password' ],
                    'delivery_date' => $quote_response->delivery_date,
                    'price'         => $quote_response->total,
                    'quote_pid'     => $quote_response->pid
            ] );
            $cDao               = new ConfirmationDao;
            $cDao->insertStruct( $confirmationStruct, [ 'ignore' => true, 'no_nulls' => true ] );

        }

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
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_USERAGENT      => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                CURLOPT_CONNECTTIMEOUT => 5
        ];

        $token = $mh->createResource( $url, $curlOptions );

        $mh->multiExec();

        if ( $mh->hasError( $token ) ) {
            throw new Exception( $mh->getError( $token ) );
        }

        return $mh->getSingleContent( $token );
    }
}