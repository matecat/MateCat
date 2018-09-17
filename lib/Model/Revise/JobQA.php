<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 28/01/15
 * Time: 14.51
 */
class Revise_JobQA {

    private $job_id;
    private $job_password;
    private $job_words;
    private $job_vote;

    /**
     * @var ErrorCount_Struct
     */
    private        $job_error_totals;
    private static $error_info;
    private        $reviseClass;

    public function __construct( $id_job, $password_job, $job_words, Constants_Revise $reviseClass ) {
        $this->job_id       = $id_job;
        $this->job_password = $password_job;
        $this->job_words    = $job_words;

        $this->reviseClass = $reviseClass;

        self::$error_info = array(
                'typing'      => array(
                        'maxErr'     => $reviseClass::MAX_TYPING,
                        'acceptance' => null,
                        'foundErr'   => null,
                        'vote'       => null,
                        'textVote'   => null,
                ),
                'translation' => array(
                        'maxErr'     => $reviseClass::MAX_TRANSLATION,
                        'acceptance' => null,
                        'foundErr'   => null,
                        'vote'       => null,
                        'textVote'   => null,
                ),
                'terminology' => array(
                        'maxErr'     => $reviseClass::MAX_TERMINOLOGY,
                        'acceptance' => null,
                        'foundErr'   => null,
                        'vote'       => null,
                        'textVote'   => null,
                ),
                'language'    => array(
                        'maxErr'     => $reviseClass::MAX_QUALITY,
                        'acceptance' => null,
                        'foundErr'   => null,
                        'vote'       => null,
                        'textVote'   => null,
                ),
                'style'       => array(
                        'maxErr'     => $reviseClass::MAX_STYLE,
                        'acceptance' => null,
                        'foundErr'   => null,
                        'vote'       => null,
                        'textVote'   => null,
                )

        );
    }

    /**
     * Get job's error information from the database.
     * NB: It must be explicitly invoked after class initialization.
     * @throws Exception Throws exception on DB fail
     */
    public function retrieveJobErrorTotals( $ttl = 900 ) {
        $errorCountDao = new ErrorCount_ErrorCountDAO( Database::obtain() );

        $searchErrorCountStruct = new ErrorCount_Struct();
        $searchErrorCountStruct->setIdJob( $this->job_id );
        $searchErrorCountStruct->setJobPassword( $this->job_password );

        $jobErrorTotals = $errorCountDao->setCacheTTL( $ttl )->read( $searchErrorCountStruct );

        /**
         * @var $jobErrorTotals ErrorCount_Struct
         */
        $jobErrorTotals = $jobErrorTotals[ 0 ];

        $this->job_error_totals = $jobErrorTotals;
    }

    public function cleanErrorCache(){
        $errorCountDao = new ErrorCount_ErrorCountDAO( Database::obtain() );
        $searchErrorCountStruct = new ErrorCount_Struct();
        $searchErrorCountStruct->setIdJob( $this->job_id );
        $searchErrorCountStruct->setJobPassword( $this->job_password );
        $errorCountDao->cleanErrorCache( $searchErrorCountStruct );
    }

    /**
     * Get the QA value for the job in a client readable structure
     *
     * @return array
     * @throws ReflectionException
     */
    public function getQaData() {
        if ( empty( $this->job_error_totals ) ) {
            throw new BadFunctionCallException( "You must call retrieveJobErrorTotals first" );
        }

        $reflect = new ReflectionClass( $this->reviseClass );

        $qaData = array();

        foreach ( self::$error_info as $field => $info ) {
            $fieldName = $constants = $reflect->getConstant( "ERR_" . strtoupper( $field ) );
            $qaData[]    = array(
                    'type'    => $fieldName,
                    'field' => $field,
                    'allowed' => round( $info[ 'acceptance' ], 1 , PHP_ROUND_HALF_UP ),
                    'found'   => $info[ 'foundErr' ],
                    'founds' => ['minor' => $info['foundErr_min'], 'major' => $info['foundErr_maj']],
                    'vote'    => $info[ 'textVote' ]
            );
        }

        return $qaData;
    }

    /**
     * @param $field string
     */
    private function evalAcceptanceThreshold( $field ) {
        if ( array_key_exists( $field, self::$error_info ) ) {

            /*
             * maxErr is the number of Major Tolerated Errors, Major counts as 1
             */
            self::$error_info[ $field ][ 'acceptance' ] = ( $this->job_words / constant( get_class( $this->reviseClass ) . "::WORD_INTERVAL" ) ) * self::$error_info[ $field ][ 'maxErr' ];
        }
    }

    /**
     * @param $field string
     */
    private function evalFieldVote( $field ) {
        if ( array_key_exists( $field, self::$error_info ) ) {
            //evaluate the method name to invoke into job_error_totals
            $methodName = 'get' . ucfirst( $field );

            /**
             * @var $errNumber int
             */
            $found_min = $this->job_error_totals->{$methodName . "Min"}();
            $found_maj = $this->job_error_totals->{$methodName . "Maj"}();
            $errNumberMin = $found_min * constant( get_class( $this->reviseClass ) . "::SERV_VALUE_MINOR" );
            $errNumberMaj = $found_maj * constant( get_class( $this->reviseClass ) . "::SERV_VALUE_MAJOR" );
            $errNumber    = $errNumberMin + $errNumberMaj;

            self::$error_info[ $field ][ 'foundErr' ] = $errNumber;
            self::$error_info[ $field ][ 'foundErr_min' ] = $found_min;
            self::$error_info[ $field ][ 'foundErr_maj' ] = $found_maj;
            self::$error_info[ $field ][ 'vote' ]     = $errNumber / (
                    self::$error_info[ $field ][ 'acceptance' ] == 0
                            ? 1
                            : self::$error_info[ $field ][ 'acceptance' ]
                    );
            self::$error_info[ $field ][ 'textVote' ] = $this->vote2text( self::$error_info[ $field ][ 'vote' ] );
        }
    }

    /**
     * @return array
     */
    public function evalJobVote() {
        foreach ( self::$error_info as $field => $info ) {
            $this->evalAcceptanceThreshold( $field );
            $this->evalFieldVote( $field );
        }

        //evaluate
        $avgMark = 0.0;
        foreach ( self::$error_info as $field => $info ) {
            $avgMark += $info[ 'vote' ];
        }

        $avgMark = $avgMark / count( self::$error_info );

        $this->job_vote = array(
                'avg'              => $avgMark,
                'minText'          => $this->vote2text( $avgMark ),
                'equivalent_class' => self::vote2EquivalentScore( $avgMark )
        );

        return $this->job_vote;
    }

    public function getJobVote() {
        return $this->job_vote;
    }

    private function vote2text( $vote ) {

        if ( $vote >= 0.94 ) {
            return constant( get_class( $this->reviseClass ) . "::VOTE_FAIL" );
        } elseif ( $vote >= 0.7 ) {
            return constant( get_class( $this->reviseClass ) . "::VOTE_POOR" );
        } elseif ( $vote >= 0.46 ) {
            return constant( get_class( $this->reviseClass ) . "::VOTE_ACCEPTABLE" );
        } elseif ( $vote >= 0.22 ) {
            return constant( get_class( $this->reviseClass ) . "::VOTE_GOOD" );
        } elseif ( $vote >= 0.10 ) {
            return constant( get_class( $this->reviseClass ) . "::VOTE_VERY_GOOD" );
        } elseif ( $vote >= 0 ) {
            return constant( get_class( $this->reviseClass ) . "::VOTE_EXCELLENT" );
        } else {
            return "";
        }

    }

    /**
     * Convert job average score to Equivalent Translated Score
     *
     * @param $vote
     *
     * @return null|string
     */
    private static function vote2EquivalentScore( $vote ){
        foreach( Constants_Revise::$equivalentScoreMap as $class => $value ){
            if ( $value >= $vote ) return $class;
        }
        return null;
    }

}
