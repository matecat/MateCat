<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 28/01/15
 * Time: 14.51
 */
class Revise_JobQA {

    const WORD_INTERVAL = 2500;
    const MAX_TYPING = 5;
    const MAX_TRANSLATION = 3;
    const MAX_TERMINOLOGY = 4;
    const MAX_QUALITY = 5;
    const MAX_STYLE = 2;

    const VOTE_EXCELLENT  = "Excellent";
    const VOTE_VERY_GOOD  = "Very Good";
    const VOTE_GOOD       = "Good";
    const VOTE_ACCEPTABLE = "Acceptable";
    const VOTE_FAIL       = "Fail";

    /**
     * A scale for the votes, needs to get the lesser vote of a job ( evalJobVote )
     *
     * @var int[]
     */
    private $scaleVote    = array(
            self::VOTE_EXCELLENT  => 100,
            self::VOTE_VERY_GOOD  => 80,
            self::VOTE_GOOD       => 60,
            self::VOTE_ACCEPTABLE => 40,
            self::VOTE_FAIL       => 20
    );

    /**
     * The lesser vote of the job after it is evaluated ( evalJobVote )
     * @var string
     */
    private $leastVote = self::VOTE_EXCELLENT;

    private $job_id;
    private $job_password;
    private $job_words;

    /**
     * @var ErrorCount_Struct
     */
    private $job_error_totals;
    private static $error_info;

    public function __construct( $id_job, $password_job, $job_words ) {
        $this->job_id       = $id_job;
        $this->job_password = $password_job;
        $this->job_words    = $job_words;

        self::$error_info = array(
                'typing'      => array(
                        'maxErr'     => self::MAX_TYPING,
                        'acceptance' => null,
                        'foundErr'   => null,
                        'vote'       => null,
                        'textVote'   => null
                ),
                'translation' => array(
                        'maxErr'     => self::MAX_TRANSLATION,
                        'acceptance' => null,
                        'foundErr'   => null,
                        'vote'       => null,
                        'textVote'   => null
                ),
                'terminology' => array(
                        'maxErr'     => self::MAX_TERMINOLOGY,
                        'acceptance' => null,
                        'foundErr'   => null,
                        'vote'       => null,
                        'textVote'   => null
                ),
                'quality'     => array(
                        'maxErr'     => self::MAX_QUALITY,
                        'acceptance' => null,
                        'foundErr'   => null,
                        'vote'       => null,
                        'textVote'   => null
                ),
                'style'       => array(
                        'maxErr'     => self::MAX_STYLE,
                        'acceptance' => null,
                        'foundErr'   => null,
                        'vote'       => null,
                        'textVote'   => null
                )

        );
    }

    /**
     * Get job's error information from the database.
     * NB: It must be explicitly invoked after class initialization.
     * @throws Exception Throws exception on DB fail
     */
    public function retrieveJobErrorTotals() {
        $errorCountDao = new ErrorCount_ErrorCountDAO( Database::obtain() );

        $searchErrorCountStruct = new ErrorCount_Struct();
        $searchErrorCountStruct->setIdJob( $this->job_id );
        $searchErrorCountStruct->setJobPassword( $this->job_password );

        $jobErrorTotals = $errorCountDao->read( $searchErrorCountStruct );
        /**
         * @var $jobErrorTotals ErrorCount_Struct
         */
        $jobErrorTotals = $jobErrorTotals[ 0 ];

        $this->job_error_totals = $jobErrorTotals;
    }

    /**
     * Get the QA value for the job in a client readable structure
     *
     * @return array
     */
    public function getQaData() {
        if ( empty( $this->job_error_totals ) ) {
            throw new BadFunctionCallException( "You must call retrieveJobErrorTotals first" );
        }

        $reflect = new ReflectionClass( 'Constants_Revise' );

        $qaData = array();

        foreach ( self::$error_info as $field => $info ) {
            $fieldName = $constants = $reflect->getConstant( "ERR_" . strtoupper( $field ) );
            $qaData[]    = array(
                    'type'    => $fieldName,
                    'allowed' => (int)$info[ 'acceptance' ],
                    'found'   => $info[ 'foundErr' ],
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
            self::$error_info[ $field ][ 'acceptance' ] = ( $this->job_words / 2500 ) * self::$error_info[ $field ][ 'maxErr' ];
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
            $errNumber = $this->job_error_totals->$methodName();

            self::$error_info[ $field ][ 'foundErr' ] = $errNumber;
            self::$error_info[ $field ][ 'vote' ]     = $errNumber / self::$error_info[ $field ][ 'acceptance' ];
            self::$error_info[ $field ][ 'textVote' ] = self::vote2text( self::$error_info[ $field ][ 'vote' ] );
        }
    }

    /**
     * @return float
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
            if( $this->scaleVote[ $info[ 'textVote' ] ] < $this->scaleVote[ $this->leastVote ] ){
                $this->leastVote = $info[ 'textVote' ];
            }
        }

        $avgMark = $avgMark / count( self::$error_info );

        return array( 'avg' => $avgMark, 'minText' => $this->leastVote );
    }

    private static function vote2text( $vote ) {

        if ( $vote >= 4.2 ) {
            return self::VOTE_FAIL;
        } elseif ( $vote >= 3 ) {
            return self::VOTE_ACCEPTABLE;
        } elseif ( $vote >= 1.8 ) {
            return self::VOTE_GOOD;
        } elseif ( $vote >= 1.2 ) {
            return self::VOTE_VERY_GOOD;
        } elseif ( $vote >= 0 ) {
            return self::VOTE_EXCELLENT;
        } else {
            return "";
        }

    }

} 