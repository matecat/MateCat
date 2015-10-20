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
    private $_curlOptions;

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

        $this->_curlOptions = array(    CURLOPT_HEADER         => false,
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_HEADER         => 0,
                                        CURLOPT_USERAGENT      => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                                        CURLOPT_CONNECTTIMEOUT => 10,
                                        CURLOPT_SSL_VERIFYPEER => true,
                                        CURLOPT_SSL_VERIFYHOST => 2 );
    }

    /**
     * Perform a quote on the remote Provider server
     *
     * @see OutsourceTo_AbstractProvider::performQuote
     *
     * @param array|null $volAnalysis
     */
    public function performQuote( $volAnalysis = null ) {

        $curlOptionsForAnalysis = $this->_curlOptions;
        $curlOptionsForAnalysis[ CURLOPT_CONNECTTIMEOUT ] = 5; // a timeout to call itself should not be too much higher :D

        /**
         * cache this job info for 20 minutes ( session duration )
         */

        list( $subject, $volAnalysis ) = $this->__getProjectData( $volAnalysis, $curlOptionsForAnalysis );


        /**
         * check if some job has already been outsourced
         */
        $mh = new MultiCurlHandler();
        foreach ( $this->jobList as $job ) {

            if( Shop_Cart::getInstance( 'outsource_to_external_cache' )->itemExists( $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-outsourced" ) ) {
                $this->__updateCartElements( $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-outsourced", $this->timezone, $this->currency );
                continue;
            }

            //trim decimals to int
            $words = max( (int)$volAnalysis[ 'data' ][ 'jobs' ][ $job[ 'jid' ] ][ 'totals' ][ $job[ 'jpassword' ] ][ 'TOTAL_PAYABLE' ][ 0 ], 1 );

            $url =  "http://www.translated.net/hts/matecat-endpoint.php?f=outsourced&cid=htsdemo&p=htsdemo5" .
                    "&matecat_pid=" . $this->pid . "&matecat_ppass=" . $this->ppassword . "&matecat_words=$words" .
                    "&matecat_jid=" . $job[ 'jid' ] . "&matecat_jpass=" . $job[ 'jpassword' ] . "&of=json";

            $mh->createResource( $url, $this->_curlOptions, $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-outsourced" );
        }

        $mh->multiExec();
        $res = $mh->getAllContents();

        //fetch contents and store in cache if there are
        foreach ( $res as $jobCredentials => $outsourceInfo ) {
            $result_outsource = json_decode( $outsourceInfo, true );

            if( $result_outsource[ "code" ] != 1 || $result_outsource[ "outsourced" ] != 1 ) {
                continue;
            }

            $itemCart = $this->__prepareOutsourcedJobCart( $jobCredentials, $volAnalysis, $subject, $result_outsource );
            $this->__addCartElement( $itemCart, true );
        }


        /**
         * prepare the environment for having outsource quotes
         */
        $mh = new MultiCurlHandler();
        foreach ( $this->jobList as $job ) {

            if( Shop_Cart::getInstance( 'outsource_to_external_cache' )->itemExists( $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-outsourced" ) ) {
                continue;
            }

            if ( Shop_Cart::getInstance( 'outsource_to_external_cache' )->itemExists( $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-" . $this->fixedDelivery ) ) {
                $this->__updateCartElements( $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-" . $this->fixedDelivery, $this->timezone, $this->currency, $this->typeOfService );
                continue;
            }

            list( $source, $target ) = explode( "|", $volAnalysis[ 'jobs' ][ 'langpairs' ][ $job[ 'jid' ] . "-" . $job[ 'jpassword' ] ] );

            //trim decimals to int
            $words = max( (int)$volAnalysis[ 'data' ][ 'jobs' ][ $job[ 'jid' ] ][ 'totals' ][ $job[ 'jpassword' ] ][ 'TOTAL_PAYABLE' ][ 0 ], 1 );

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

            $fixedDeliveryDateForQuote = ( $this->fixedDelivery > 0 ) ? date( "Y-m-d H:i:s", $this->fixedDelivery / 1000 ) : "0";

            $url =  "https://www.translated.net/hts/matecat-endpoint.php?f=quote&cid=htsdemo&p=htsdemo5&s=$source&t=$target" .
                    "&pn=MATECAT_{$job['jid']}-{$job['jpassword']}&w=$words&df=matecat&matecat_pid=" . $this->pid .
                    "&matecat_ppass=" . $this->ppassword . "&matecat_pname=" . $volAnalysis[ 'data' ][ 'summary' ][ 'NAME' ] .
                    "&subject=$subject&jt=R&fd=" . urlencode( $fixedDeliveryDateForQuote ) . "&of=json";

            Log::doLog( "Not Found in Cache. Call url for Quote:  " . $url );
            $mh->createResource( $url, $this->_curlOptions, $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-" . $this->fixedDelivery );
        }

        $mh->multiExec();
        $res = $mh->getAllContents();

        //fetch contents and store in cache if there are
        foreach ( $res as $jpid => $quote ) {

            if ( $mh->hasError( $jpid ) ) {
                Log::doLog( $mh->getError( $jpid ) );
                continue;
            }

            Log::doLog( $quote );

            $result_quote = json_decode( $quote, TRUE );
            if ( $result_quote[ 'code' ] != 1 ) {
                Log::doLog( "HTS returned an error. Skip quote" );
                continue;
            }

            $itemCart = $this->__prepareQuotedJobCart( $jpid, $volAnalysis, $subject, $result_quote );
            $this->__addCartElement( $itemCart );

            Log::doLog( $itemCart );
        }
    }


    private function __getProjectData( $volAnalysis, $curlOptions ) {
        $project_url_api = INIT::$HTTPHOST . INIT::$BASEURL . "api/status?id_project=" . $this->pid . "&project_pass=" . $this->ppassword;

        if( !Shop_Cart::getInstance( 'outsource_to_external_cache' )->itemExists( $project_url_api ) ) {
            Log::doLog( "Project Not Found in Cache. Call API url for STATUS: " . $project_url_api );

            //trick/hack for shop cart
            //Use the shop cart to add Projects info
            //to the cache cart because of taking advantage of the cart cache invalidation on project split/merge

            //prepare handlers for curl to quote service
            $mh           = new MultiCurlHandler();
            $resourceHash = $mh->createResource( $project_url_api, $curlOptions );
            $mh->multiExec();

            if ( $mh->hasError( $resourceHash ) ) {
                Log::doLog( $mh->getError( $resourceHash ) );
            }

            $volAnalysis = $mh->getSingleContent( $resourceHash );

            $mh->multiCurlCloseAll();

            //retrieve the project subject: pick the project's first job and get the subject
            $jobData = getJobData( $this->jobList[ 0 ][ 'jid' ], $this->jobList[ 0 ][ 'jpassword' ] );

            $projectItemCart                = new Shop_ItemHTSQuoteJob();
            $projectItemCart[ 'id' ]        = $project_url_api;
            $projectItemCart[ 'subject' ]   = $jobData[ 'subject' ];
            $projectItemCart[ 'show_info' ] = $volAnalysis;

            Shop_Cart::getInstance( 'outsource_to_external_cache' )->addItem( $projectItemCart );
        }

        $projectItemCart = Shop_Cart::getInstance( 'outsource_to_external_cache' )->getItem( $project_url_api );

        if( $volAnalysis != null ) {
            $projectItemCart[ 'show_info' ] = $volAnalysis;
            Shop_Cart::getInstance( 'outsource_to_external_cache' )->delItem( $project_url_api );
            Shop_Cart::getInstance( 'outsource_to_external_cache' )->addItem( $projectItemCart );
        }

        return array( $projectItemCart[ 'subject' ], json_decode( $projectItemCart[ 'show_info' ], true ) );
    }


    private function __prepareOutsourcedJobCart( $jpid, $volAnalysis, $subject, $apiCallResult ) {
        list( $jid, $jpsw, ) = explode( "-", $jpid );
        list( $source, $target ) = explode( "|", $volAnalysis[ 'jobs' ][ 'langpairs' ][ "$jid-$jpsw" ] );

        $itemCart                    = new Shop_ItemHTSQuoteJob();
        $itemCart[ 'id' ]            = $jpid;
        $itemCart[ 'project_name' ]  = $volAnalysis[ 'data' ][ 'summary' ][ 'NAME' ];
        $itemCart[ 'name' ]          = "MATECAT_$jpid";
        $itemCart[ 'source' ]        = $source;
        $itemCart[ 'target' ]        = $target;
        $itemCart[ 'words' ]         = max( (int)$volAnalysis[ 'data' ][ 'jobs' ][ $jid ][ 'totals' ][ $jpsw ][ 'TOTAL_PAYABLE' ][ 0 ], 1 );
        $itemCart[ 'subject' ]       = $subject;
        $itemCart[ 'currency' ]      = $this->currency;
        $itemCart[ 'timezone' ]      = $this->timezone;
        $itemCart[ 'quote_result' ]  = $apiCallResult[ 'code' ];
        $itemCart[ 'outsourced' ]    = $apiCallResult[ 'outsourced' ];
        $itemCart[ 'typeOfService' ] = $apiCallResult[ 'type_of_service' ];
        $itemCart[ 'price' ]         = $apiCallResult[ 'price' ];
        $itemCart[ 'delivery' ]      = $apiCallResult[ 'delivery' ];
        $itemCart[ 'link_to_status' ]= $apiCallResult[ 'link_to_status' ];
        $itemCart[ 'quantity' ]      = 1;

        return $itemCart;
    }


    private function __prepareQuotedJobCart( $jpid, $volAnalysis, $subject, $apiCallResult ) {
        list( $jid, $jpsw, ) = explode( "-", $jpid );
        list( $source, $target ) = explode( "|", $volAnalysis[ 'jobs' ][ 'langpairs' ][ "$jid-$jpsw" ] );

        $itemCart                      = new Shop_ItemHTSQuoteJob();
        $itemCart[ 'id' ]              = $jpid;
        $itemCart[ 'project_name' ]    = $volAnalysis[ 'data' ][ 'summary' ][ 'NAME' ];
        $itemCart[ 'name' ]            = "MATECAT_$jpid";
        $itemCart[ 'source' ]          = $source;
        $itemCart[ 'target' ]          = $target;
        $itemCart[ 'words' ]           = max( (int)$volAnalysis[ 'data' ][ 'jobs' ][ $jid ][ 'totals' ][ $jpsw ][ 'TOTAL_PAYABLE' ][ 0 ], 1 );
        $itemCart[ 'subject' ]         = $subject;
        $itemCart[ 'currency' ]        = $this->currency;
        $itemCart[ 'timezone' ]        = $this->timezone;
        $itemCart[ 'quote_result' ]    = $apiCallResult[ 'code' ];
        $itemCart[ 'outsourced' ]      = 0;
        $itemCart[ 'quote_available' ] = $apiCallResult[ 'quote_available' ];
        $itemCart[ 'typeOfService' ]   = $this->typeOfService;

        if( $itemCart[ 'quote_result' ] == 1 && $itemCart[ 'quote_available' ] == 1 ) {
            $itemCart['price']                = $apiCallResult['translation']['price'];
            $itemCart['delivery']             = $apiCallResult['translation']['delivery'];
            $itemCart['r_price']              = $apiCallResult['revision']['price'];
            $itemCart['r_delivery']           = $apiCallResult['revision']['delivery'];
            $itemCart['quote_pid']            = $apiCallResult['pid'];
            $itemCart['show_info']            = $apiCallResult['showquote'];
            $itemCart['show_translator_data'] = $apiCallResult['show_translator_data'];

            if ($itemCart['show_translator_data'] == 1) {
                $itemCart['t_name']               = $apiCallResult['translation']['translator_name'];
                $itemCart['t_native_lang']        = $apiCallResult['translation']['translator_native_lang'];
                $itemCart['t_words_specific']     = $apiCallResult['translation']['translator_words_specific'];
                $itemCart['t_words_total']        = $apiCallResult['translation']['translator_words_total'];
                $itemCart['t_vote']               = $apiCallResult['translation']['translator_vote'];
                $itemCart['t_positive_feedbacks'] = $apiCallResult['translation']['translator_positive_feedbacks'];
                $itemCart['t_total_feedbacks']    = $apiCallResult['translation']['translator_total_feedbacks'];
                $itemCart['t_experience_years']   = $apiCallResult['translation']['translator_experience_years'];
                $itemCart['t_education']          = $apiCallResult['translation']['translator_education'];
                $itemCart['t_chosen_subject']     = $apiCallResult['translation']['chosen_subject'];
                $itemCart['t_subjects']           = $apiCallResult['translation']['translator_subjects'];
                $itemCart['show_revisor_data']    = $apiCallResult['show_revisor_data'];

                if( $itemCart['show_revisor_data'] == 1 ) {
                    $itemCart['r_vote'] = $apiCallResult['revision']['revisor_vote'];
                }
            }
        }

        return $itemCart;
    }


    private function __updateCartElements( $cartId, $newCurrency, $newTimezone, $newTypeOfService = null ) {
        $cartElem = Shop_Cart::getInstance( 'outsource_to_external_cache' )->getItem( $cartId );
        $cartElem[ "currency" ] = !empty( $newCurrency ) ? $newCurrency : $cartElem[ "currency" ];
        $cartElem[ "timezone" ] = !empty( $newTimezone ) ? $newTimezone : $cartElem[ "timezone" ];
        $cartElem[ "typeOfService" ] = !empty( $newTypeOfService ) ? $newTypeOfService : $cartElem[ "typeOfService" ];

        $this->__addCartElement( $cartElem );
    }


    private function __addCartElement( $cartElem, $deleteOnPartialMatch = false ) {
        $this->__addCartElementToCart( $cartElem, 'outsource_to_external', $deleteOnPartialMatch );
        $this->__addCartElementToCart( $cartElem, 'outsource_to_external_cache', $deleteOnPartialMatch );

        $this->_quote_result[] = array( $cartElem );
    }


    private function __addCartElementToCart( $cartElem, $cartName, $deleteOnPartialMatch ) {
        $idToUse = ( $deleteOnPartialMatch ) ? substr( $cartElem[ "id" ], 0, strrpos( $cartElem[ "id" ], "-" ) ) : $cartElem[ "id" ];
        Shop_Cart::getInstance( $cartName )->delItem( $idToUse );
        Shop_Cart::getInstance( $cartName )->addItem( $cartElem );
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
