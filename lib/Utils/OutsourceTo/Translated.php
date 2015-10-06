<?php
/**
 * Created by PhpStorm.
 */

/**
 * Concrete Class to negotiate a Quote/Login/Review/Confirm communication
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 29/04/14
 * Time: 10.48
 *
 */
class OutsourceTo_Translated extends OutsourceTo_AbstractProvider {

    private $fixedDelivery;
    private $typeOfService;

    /**
     * Class constructor
     *
     * There will be defined the callback urls for success or failure on login system
     *
     */
    public function __construct() {

        //SESSION ENABLED
        Bootstrap::sessionStart();

        $this->currency    = "EUR";
        $this->change_rate = 1;

        /**
         * @see OutsourceTo_AbstractProvider::$_outsource_login_url_ok
         */
        $this->_outsource_login_url_ok = INIT::$HTTPHOST . INIT::$BASEURL . "index.php?action=OutsourceTo_TranslatedSuccess";
        $this->_outsource_login_url_ko = INIT::$HTTPHOST . INIT::$BASEURL . "index.php?action=OutsourceTo_TranslatedError";
    }

    /**
     * Perform a quote on the remote Provider server
     *
     * @see OutsourceTo_AbstractProvider::performQuote
     *
     * @param array|null $volAnalysis
     */
    public function performQuote( $volAnalysis = null ) {

        /**
         * cache this job info for 20 minutes ( session duration )
         */
        $cache_cart = Shop_Cart::getInstance( 'outsource_to_external_cache' );

        if ( $volAnalysis == null ) {

            //call matecat API for Project status and information
            $project_url_api = INIT::$HTTPHOST . INIT::$BASEURL . "api/status?id_project=" . $this->pid . "&project_pass=" . $this->ppassword;

            if ( !$cache_cart->itemExists( $project_url_api ) ) {

                //trick/hack for shop cart
                //Use the shop cart to add Projects info
                //to the cache cart because of taking advantage of the cart cache invalidation on project split/merge
                Log::doLog( "Project Not Found in Cache. Call API url for STATUS: " . $project_url_api );


                $options = array(
                        CURLOPT_HEADER         => false,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HEADER         => 0,
                        CURLOPT_USERAGENT      => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                        CURLOPT_CONNECTTIMEOUT => 5, // a timeout to call itself should not be too much higher :D
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_SSL_VERIFYHOST => 2
                );

                //prepare handlers for curl to quote service
                $mh           = new MultiCurlHandler();
                $resourceHash = $mh->createResource( $project_url_api, $options );
                $mh->multiExec();

                if ( $mh->hasError( $resourceHash ) ) {
                    Log::doLog( $mh->getError( $resourceHash ) );
                }

                $raw_volAnalysis = $mh->getSingleContent( $resourceHash );

                $mh->multiCurlCloseAll();

                //retrieve the project subject: pick the project's first job and get the subject
                $jobData = getJobData( $this->jobList[ 0 ][ 'jid' ], $this->jobList[ 0 ][ 'jpassword' ] );
                $subject = $jobData[ 'subject' ];

                $itemCart                = new Shop_ItemHTSQuoteJob();
                $itemCart[ 'id' ]        = $project_url_api;
                $itemCart[ 'show_info' ] = $raw_volAnalysis;

                $itemCart[ 'subject' ] = $subject;

                $cache_cart->addItem( $itemCart );

            } else {

                $tmp_project_cache = $cache_cart->getItem( $project_url_api );
                $raw_volAnalysis   = $tmp_project_cache[ 'show_info' ];
                $subject = $tmp_project_cache['subject'];

            }

//        Log::doLog( $raw_volAnalysis );

            $volAnalysis = json_decode( $raw_volAnalysis, true );

        }

//        Log::doLog( $volAnalysis );
        $_jobLangs = array();

        $options = array(
                CURLOPT_HEADER         => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => 0,
                CURLOPT_USERAGENT      => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
        );

        //prepare handlers for curl to quote service
        $mh = new MultiCurlHandler();
        foreach ( $this->jobList as $job ) {

            //trim decimals to int
            $job_payableWords = (int)$volAnalysis[ 'data' ][ 'jobs' ][ $job[ 'jid' ] ][ 'totals' ][ $job[ 'jpassword' ] ][ 'TOTAL_PAYABLE' ][ 0 ];

            /*
             * //languages are in the form:
             *
             *     "langpairs":{
             *          "5888-e94bd2f79afd":"en-GB|fr-FR",
             *          "5889-c853a841dafd":"en-GB|de-DE",
             *          "5890-e852ca45c66e":"en-GB|it-IT",
             *          "5891-b43f2f067319":"en-GB|es-ES"
             *   },
             *
             */
            $langPairs = $volAnalysis[ 'jobs' ][ 'langpairs' ][ $job[ 'jid' ] . "-" . $job[ 'jpassword' ] ];

            $_langPairs_array = explode( "|", $langPairs );
            $source           = $_langPairs_array[ 0 ];
            $target           = $_langPairs_array[ 1 ];

            //save langpairs of the jobs
            $_jobLangs[ $job[ 'jid' ] . "-" . $job[ 'jpassword' ] ][ 'source' ] = $source;
            $_jobLangs[ $job[ 'jid' ] . "-" . $job[ 'jpassword' ] ][ 'target' ] = $target;

            $fixedDeliveryDateForQuote = ( $this->fixedDelivery > 0 ) ? date( "Y-m-d H:i:s", $this->fixedDelivery / 1000 ) : "0";

            $url =  "https://www.translated.net/hts/matecat-endpoint.php?" .
                    "f=quote&" .
                    "cid=htsdemo&" .
                    "p=htsdemo5&" .
                    "s=$source&" .
                    "t=$target&" .
                    "pn=MATECAT_{$job['jid']}-{$job['jpassword']}&" .
                    "w=$job_payableWords&" .
                    "df=matecat&" .
                    "matecat_pid=" . $this->pid .
                    "&matecat_ppass=" . $this->ppassword .
                    "&matecat_pname=" . $volAnalysis[ 'data' ][ 'summary' ][ 'NAME' ] .
                    "&subject=" . $subject .
                    "&jt=R" .
                    "&fd=" . urlencode( $fixedDeliveryDateForQuote ) .
                    "&of=json";

            if ( !$cache_cart->itemExists( $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-" . $this->fixedDelivery ) ) {
                Log::doLog( "Not Found in Cache. Call url for Quote:  " . $url );
                $tokenHash = $mh->createResource( $url, $options, $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-" . $this->fixedDelivery );
            } else {
                $cartElem               = $cache_cart->getItem( $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-" . $this->fixedDelivery );
                $cartElem[ "currency" ] = $this->currency;
                $cartElem[ "timezone" ] = $this->timezone;
                $cartElem[ "typeOfService" ] = $this->typeOfService;
                $cache_cart->delItem( $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-" . $this->fixedDelivery );
                $cache_cart->addItem( $cartElem );
            }
        }

        $mh->multiExec();

        $res = $mh->getAllContents();

        $failures = array();

        //fetch contents and store in cache if there are
        foreach ( $res as $jpid => $quote ) {

            if ( $mh->hasError( $jpid ) ) {
                Log::doLog( $mh->getError( $jpid ) );
            }

            /*
             * Quotes are plain text line feed separated fields in the form:
             *   1
             *   OK
             *   2014-04-16T09:30:00Z
             *   488
             *   46.36
             *   11140320
             *   1
             */

            Log::doLog( $quote );

            $result_quote                       = json_decode( $quote, TRUE );
            $itemCart                           = new Shop_ItemHTSQuoteJob();
            $itemCart[ 'id' ]                   = $jpid;
            $itemCart[ 'project_name' ]         = $volAnalysis[ 'data' ][ 'summary' ][ 'NAME' ];
            $itemCart[ 'name' ]                 = "MATECAT_$jpid";
            $itemCart[ 'source' ]               = $source; //get the right language
            $itemCart[ 'target' ]               = $target; //get the right language
            $itemCart[ 'words' ]                = $job_payableWords;
            $itemCart[ 'subject' ]              = $subject;
            $itemCart[ 'currency' ]             = $this->currency;
            $itemCart[ 'timezone' ]             = $this->timezone;
            $itemCart[ 'quote_result' ]         = $result_quote[ 'code' ];
            $itemCart[ 'quote_available' ]      = $result_quote[ 'quote_available' ];
            $itemCart[ 'typeOfService' ]        = $this->typeOfService;
            if( $itemCart[ 'quote_result' ] == 1 && $itemCart[ 'quote_available' ] == 1 ) {
                $itemCart['price'] = $result_quote['translation']['price'];
                $itemCart['delivery'] = $result_quote['translation']['delivery'];
                $itemCart['r_price'] = $result_quote['revision']['price'];
                $itemCart['r_delivery'] = $result_quote['revision']['delivery'];
                $itemCart['quote_pid'] = $result_quote['pid'];
                $itemCart['show_info'] = $result_quote['showquote'];
                $itemCart['show_translator_data'] = $result_quote['show_translator_data'];
                if ($itemCart['show_translator_data'] == 1) {
                    $itemCart['t_name'] = $result_quote['translation']['translator_name'];
                    $itemCart['t_native_lang'] = $result_quote['translation']['translator_native_lang'];
                    $itemCart['t_words_specific'] = $result_quote['translation']['translator_words_specific'];
                    $itemCart['t_words_total'] = $result_quote['translation']['translator_words_total'];
                    $itemCart['t_vote'] = $result_quote['translation']['translator_vote'];
                    $itemCart['t_positive_feedbacks'] = $result_quote['translation']['translator_positive_feedbacks'];
                    $itemCart['t_total_feedbacks'] = $result_quote['translation']['translator_total_feedbacks'];
                    $itemCart['t_experience_years'] = $result_quote['translation']['translator_experience_years'];
                    $itemCart['t_education'] = $result_quote['translation']['translator_education'];
                    $itemCart['t_chosen_subject'] = $result_quote['translation']['chosen_subject'];
                    $itemCart['t_subjects'] = $result_quote['translation']['translator_subjects'];
                    $itemCart['show_revisor_data'] = $result_quote['show_revisor_data'];
                    if( $itemCart['show_revisor_data'] == 1 ) {
                        $itemCart['r_vote'] = $result_quote['revision']['revisor_vote'];
                    }
                }
            }

            $cache_cart->addItem( $itemCart );

            Log::doLog( $itemCart );

            //Oops we got an error
            if ( $itemCart[ 'quote_result' ] != 1 ) {
                $failures[ $jpid ] = $jpid;
            }

        }

        $shopping_cart = Shop_Cart::getInstance( 'outsource_to_external' );

        //now get the right contents
        foreach ( $this->jobList as $job ) {
            $shopping_cart->delItem( $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-" . $this->fixedDelivery );
            $shopping_cart->addItem( $cache_cart->getItem( $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-" . $this->fixedDelivery ) );
            $this->_quote_result = array( $shopping_cart->getItem( $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-" . $this->fixedDelivery ) );
        }

        //check for failures.. destroy the cache
        if ( !empty( $failures ) ) {
            foreach ( $failures as $jpid ) {
                $cache_cart->delItem( $jpid );
            }
        }

    }



    /**********************************************************************************************/
    /************** SOME MORE TRANSLATED-SPECIFIC FUNCTIONS FOR SETTING QUOTE PARAMETERS **********/
    /**********************************************************************************************/

    /**
     * Set the fixed (desidered) delivery date of the translation.
     * This parameters will be set only when the customer uses "need it faster" feature
     *
     * @param string $fixedDelivery
     *
     * @return $this
     */
    public function setFixedDelivery( $fixedDelivery ) {
        $this->fixedDelivery = $fixedDelivery;

        return $this;
    }


    /**
     * Set the chosen type of service.
     * This parameters, for the moment can be only: "premium" or "professional"
     *
     * @param string $typeOfService
     *
     * @return $this
     */
    public function setTypeOfService( $typeOfService ) {
        $this->typeOfService = $typeOfService;

        return $this;
    }
}
