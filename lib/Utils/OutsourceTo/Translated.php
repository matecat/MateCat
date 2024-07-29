<?php

/**
 * Concrete Class to negotiate a Quote/Login/Review/Confirm communication
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 29/04/14
 * Time: 10.48
 *
 *
 *
 ****************************************************************************************************************
 ***************************************** GUIDE ****************************************************************
 ****************************************************************************************************************
 *
 ********************************** HOW IT WORKS (in short) *****************************************************
 *  For each job, check if it has already been outsourced. If not, ask for a quote and put the result in a
 *  Shop_ItemHTSQuoteJob object. Put all the Shop_ItemHTSQuoteJob in a Shop_Cart object, and cache it in session.
 *
 ********************************** HOW IT WORKS (long version) *************************************************
 *  PROCEDURE
 *      the main function is "performQuote", which makes 3 main things:
 *          1-  first of all retrieves, calling "OutsourceTo_Translated::__getProjectData", the general information
 *              about the project, which are common to all jobs: subject and volume analysis. These information, the
 *              first time, are retrieved via API status, and then stored in a Shop_ItemHTSQuoteJob (yes, as if it was
 *              a real quote. This is an hack for caching the volume analysis and not calling API again).
 *              @see OutsourceTo_Translated::__getProjectData for further information.
 *
 *          2-  All the jobs we have to ask a quote for are stored in $this->jobList variable.
 *              The function "OutsourceTo_Translated::__processOutsourcedJobs" iterates over them and asks to
 *              vendor API which of them have already been outsourced.
 *              In case the vendor replies positively, the result is stored in a Shop_ItemHTSQuoteJob,
 *              so it will be in session for the next time.
 *              @see OutsourceTo_Translated::__processOutsourcedJobs for further information.
 *
 *          3-  Finally, "OutsourceTo_Translated::__processNormalJobs" is invoked, and all the other jobs are sent
 *              to the vendor for receiving a quote. Again, quotes are stored in an Shop_ItemHTSQuoteJob object.
 *              @see OutsourceTo_Translated::__processNormalJobs for further information.
 *
 *
 *  OBJECTS INVOLVED AND SESSION STORAGE
 *      Each reply the vendor returns is stored in a Shop_ItemHTSQuoteJob object.
 *      All the Shop_ItemHTSQuoteJob are stored in a Shop_Cart.
 *      The Shop_Cart object is stored in session, and provides functions for retrieving Shop_ItemHTSQuoteJob(s).
 *      Depending on the HTS reply, only some fields of a Shop_ItemHTSQuoteJob are filled
 *      (@see OutsourceTo_Translated::__prepareOutsourcedJobCart and OutsourceTo_Translated::__prepareQuotedJobCart
 *      for details about which fields are filled in case of "job already outsourced" and "quote for a job"
 *      replies respectively). Project information (as volume analysis and subject) are stored in a
 *      Shop_ItemHTSQuoteJob too (in order to take advantage of the caching system).
 *      @see OutsourceTo_Translated::__getProjectData for details.
 *
 *
 *  NORMAL QUOTES vs OUTSOURCED QUOTES
 *      Each Shop_ItemHTSQuoteJob is assigned with an id, which, by convention, is composed by 3 elements:
 *      id and pass of the job it wraps the data about, and the delivery date (in millis) the user chose (if any).
 *      The pattern is: JOBID-JOBPASSWORD-DELIVERYDATE
 *      Examples are:
 *          12345-qwerty-624475440000  -> user chose 624475440000 as delivery date (timestamp)
 *          12345-qwerty-0              -> user did not provide a delivery date, vendor is choosing
 *      Therefore, in cache there might be many entries for the same job, one for each delivery date the user tried.
 *      For an outsourced job instead, key "outsourced" is used: 12345-qwerty-outsourced
 *
 *
 *  NAMING AND Shop_Cart USAGE
 *      There are 2 different data structures Matecat relies on:
 *      Shop_Cart "outsource_to_external_cache" and $this->_quote_result.
 *      The former is a cache object of all the replies (Shop_ItemHTSQuoteJob(s)) the vendor returns to Matecat.
 *      The latter is an array containing all the replies (Shop_ItemHTSQuoteJob(s)) related to the current execution.
 *      EXAMPLE:
 *          The user clicks on button "Translate" for JOB_1. The procedure, as described above is executed.
 *          In the end, Shop_Cart outsource_to_external_cache and $this->_quote_result will both
 *          contain the Shop_ItemHTSQuoteJob object storing the reply (whichever it will be).
 *          Now, the user closes the outsource popup and clicks on the button "Translate" for JOB_2.
 *          Again the procedure is executed and in the end, the Shop_Cart outsource_to_external_cache will contain
 *          2 Shop_ItemHTSQuoteJob, one for each of the 2 quotes, while $this->_quote_result will only
 *          contain data about the second job (that is, each click on a Translate button is a different execution).
 *
 *
 *  TWO TYPES OF Shop_Cart: "outsource_to_external_cache" vs "outsource_to_external"
 *      In case the user clicks on "Order" for a job, he is redirected on vendor website, and login is handled
 *      via OpenID flow. The flow is as follow:
 *          1. Matecat redirects the user to the vendor website passing an ID
 *          2. User logins in vendor website
 *          3. Vendor sends a success callback to Matecat, returning the ID Matecat itself passed before
 *          4. Matecat returns to vendor all the data associated to that ID
 *      The crucial point is the 4th: Matecat needs to "remember" which quotes are associated to a certain ID.
 *      Current data structures are not enough because
 *          - Shop_Cart is persistent but contains all quotes, not only those related to current execution
 *          - $this->_quote_result contains quotes related to current execution but is not persistent
 *      Therefore we need a further data structure for persistently handling quotes related to the current execution.
 *      In order to do this, a second Shop_Cart is used: Shop_Cart "outsource_to_external". This will behave
 *      exactly as Shop_Cart "outsource_to_external_cache", but only contains quotes related to the current execution.
 *
 *
 */
class OutsourceTo_Translated extends OutsourceTo_AbstractProvider {

    private $fixedDelivery;
    private $typeOfService;
    private $_curlOptions;

    /**
     * Class constructor
     *
     * There will be defined the callback urls for success or failure on login system,
     * default localization values (currency and timezone)
     * and default connection parameters for curls
     *
     * @see OutsourceTo_AbstractProvider::$_outsource_login_url_ok
     * @see OutsourceTo_AbstractProvider::$_outsource_login_url_ko
     */
    public function __construct() {

        Bootstrap::sessionStart();

        $this->currency    = "EUR";
        $this->change_rate = 1;

        $this->_outsource_login_url_ok      = INIT::$HTTPHOST . INIT::$BASEURL . "index.php?action=OutsourceTo_TranslatedSuccess";
        $this->_outsource_login_url_ko      = INIT::$HTTPHOST . INIT::$BASEURL . "index.php?action=OutsourceTo_TranslatedError";
        $this->_outsource_url_confirm       = INIT::$HTTPHOST . INIT::$BASEURL . "api/app/outsource/confirm/%u/%s";

        $this->_curlOptions = [
            CURLOPT_HEADER         => 0,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPGET        => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true
        ];
    }


    /**
     * Perform a quote on the remote Provider server (@see GUIDE->"PROCEDURE")
     *
     * @see OutsourceTo_AbstractProvider::performQuote
     *
     * @param array|null $volAnalysis
     */
    public function performQuote( $volAnalysis = null ) {

        list( $subject, $volAnalysis ) = $this->__getProjectData();

        $this->__processOutsourcedJobs( $subject, $volAnalysis );
        $this->__processNormalJobs( $subject, $volAnalysis );
    }


    /**
     * Retrieve data about the whole project (information common to all jobs): subject and volume analysis
     *  These info are retrieved from API status and Database and cached in session, as if they were a real quote.
     *  The workflow is as follow:
     *      -   if the data is not in a Shop_ItemHTSQuoteJob cached in session
     *              retrieve them from API and DB and put them in session.
     *      -   retrieve project information from session and return them to caller
     * @see GUIDE->"PROCEDURE"->POINT 1 for details
     *
     * @return array
     */
    private function __getProjectData() {

        /**
         ************************** GET VOLUME ANALYSIS FIRST *************************
         */
        $x = new StatusController();
        $x->setIdProject( $this->pid );
        $x->setPpassword( $this->ppassword );
        $x->doAction();
        $volAnalysis = $x->getApiOutput();

        /**
         *************************** GET SUBJECT **************************************
         */
        // subject is retrieved from database: get first job of the project and get its subject
        $jStruct = new Jobs_JobStruct();
        $jStruct->id = $this->jobList[ 0 ][ 'jid' ];
        $jStruct->password = $this->jobList[ 0 ][ 'jpassword' ];
        $jobDao = new Jobs_JobDao();
        $jobData = $jobDao->setCacheTTL( 60 * 60 )->read( $jStruct )[0];

        return array( $jobData[ 'subject' ], json_decode( $volAnalysis, true ) );
    }


    /**
     * Check which jobs, among those the user asked a quote for, have already been outsourced
     * At the end of this function, all jobs already outsourced will be in cache
     *
     *  The whole flow is composed by 2 phases:
     *      1-  during the first one all jobs are iterated, and for each one of them:
     *              if there already is something in cache telling that it has been outsourced, then skip it.
     *              Otherwise, add it to the list of the jobs we have to ask the vendor about
     *
     *      2-  In the second phase, call the vendor with the above jobs and cache al the replies in session
     *  @see GUIDE->"PROCEDURE"->POINT 2 for details
     *
     * @param string $subject
     * @param array $volAnalysis
     */
    private function __processOutsourcedJobs( $subject, $volAnalysis ) {
        $mh = new MultiCurlHandler();

        /**
         ************************** FIRST PART: CHECK CACHE ***************************
         */
        foreach ( $this->jobList as $job ) {

            // Is there in cache something that tells this job has already been outsourced?
            if( Shop_Cart::getInstance( 'outsource_to_external_cache' )->itemExists( $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-outsourced" ) ) {
                // if so, then update the job localization info (currency and timezone), according to user preferences
                $this->__updateCartElements( $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-outsourced", $this->currency, $this->timezone );
                continue;
            }

            $url = "https://www.translated.net/hts/matecat-endpoint.php?" . http_build_query( [
                    'f'             => 'outsourced',
                    'cid'           => 'htsdemo',
                    'p'             => 'htsdemo5',
                    'matecat_pid'   => $this->pid,
                    'matecat_ppass' => $this->ppassword,
                    'matecat_words' => $this->getTotalPayableWords($volAnalysis),
                    'matecat_jid'   => $job[ 'jid' ],
                    'matecat_jpass' => $job[ 'jpassword' ],
                    'of'            => 'json'
                ], PHP_QUERY_RFC3986 );

            $mh->createResource( $url, $this->_curlOptions, $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-outsourced" );

        }


        /**
         ************************** SECOND PART: CALL VENDOR *****************************
         */
        // execute call and retrieve the result
        $mh->multiExec();
        $res = $mh->getAllContents();

        // for each reply
        foreach ( $res as $jobCredentials => $outsourceInfo ) {
            $result_outsource = json_decode( $outsourceInfo, true );

            Log::doJsonLog( $outsourceInfo );

            // if some error occurred, or the job has not been outsourced yet, then skip this job
            if( $result_outsource[ "code" ] != 1 || $result_outsource[ "outsourced" ] != 1 ) {
                continue;
            }

            // job has been outsourced, create a proper Shop_ItemHTSQuoteJob to hold it, and add it to the Shop_Cart
            $itemCart = $this->__prepareOutsourcedJobCart( $jobCredentials, $volAnalysis, $subject, $result_outsource );

            // NOTE: if we are here it means this is the first time (unless the cache is expired) we realize this
            // job has been outsourced. In cache there still might be many entries about old quotes for this job.
            // We need to delete them all, that is, delete the JID-JPSW-* pattern. The "true" parameter forces the
            // delete function to only check the prefix (job id and password ) for matching condition.
            // See GUIDE->"NORMAL QUOTES vs OUTSOURCED QUOTES" for details
            $this->__addCartElement( $itemCart, true );
        }
    }


    /**
     * Retrieve a quote for all the jobs the user ask for, and which have not been outsourced yet
     * At the end of this function, there will be in cache a quote for each one of these jobs
     *
     *  The whole flow is composed by 2 phases:
     *      1-  during the first one all jobs are iterated, and for each one of them:
     *              if there already is something in cache telling that it has been outsourced or we have a quote, then skip it.
     *              Otherwise, add it to the list of the jobs we have to ask the vendor for a quote
     *
     *      2-  In the second phase, call the vendor with the above jobs and cache al the replies in session
     *  @see GUIDE->"PROCEDURE"->POINT 3 for details
     *
     * @param string $subject
     * @param array $volAnalysis
     */
    private function __processNormalJobs( $subject, $volAnalysis ) {
        $mh = new MultiCurlHandler();

        /**
         ************************** FIRST PART: CHECK CACHE ***************************
         */
        foreach ( $this->jobList as $job ) {

            // has the job already been outsourced? If so, it has been fully prepared in
            //  OutsourceTo_Translated::__processOutsourcedJobs" function. Just skip it
            // NOTE:    this "if" is necessary in order to not process again a job already outsourced.
            //          A possible alternative is to unset from the $this->jobList array all the jobs detected as
            //          outsourced during OutsourceTo_Translated::__processOutsourcedJobs function
            if( Shop_Cart::getInstance( 'outsource_to_external_cache' )->itemExists( $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-outsourced" ) ) {
                continue;
            }

            // in case we have a quote in cache, we are done with this job anyway
            if ( Shop_Cart::getInstance( 'outsource_to_external_cache' )->itemExists( $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-" . $this->fixedDelivery ) ) {
                // update the job localization info (currency and timezone), according to user preferences
                $this->__updateCartElements( $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-" . $this->fixedDelivery, $this->currency, $this->timezone, $this->typeOfService );
                continue;
            }

            $langPairs = $this->getLangPairs($job[ 'jid' ], $job[ 'jpassword' ], $volAnalysis);

            if(!empty($langPairs)){
                // get delivery date chosen by the user (if any), otherwise set it to 0 to tell the vendor no date has been specified
                // NOTE: UI returns a timestamp in millis. Despite we use the one in millis for the caching ID
                // (See: GUIDE->"NORMAL QUOTES vs OUTSOURCED QUOTES"), we here need to convert it in seconds
                // and provide a MySQL -like date format. E.g. "1989-10-15 18:24:00"
                $fixedDeliveryDateForQuote = ( $this->fixedDelivery > 0 ) ? date( "Y-m-d H:i:s", $this->fixedDelivery / 1000 ) : "0";

                $url = "https://www.translated.net/hts/matecat-endpoint.php?" . http_build_query( [
                        'f'             => 'quote',
                        'cid'           => 'htsdemo',
                        'p'             => 'htsdemo5',
                        's'             => $langPairs['source'],
                        't'             => $langPairs['target'],
                        'pn'            => "MATECAT_{$job['jid']}-{$job['jpassword']}",
                        'w'             => $this->getTotalPayableWords($volAnalysis),
                        'df'            => 'matecat',
                        'matecat_pid'   => $this->pid,
                        'matecat_ppass' => $this->ppassword,
                        'matecat_pname' => $volAnalysis[ 'name' ],
                        'subject'       => $subject,
                        'jt'            => 'R',
                        'fd'            => $fixedDeliveryDateForQuote,
                        'of'            => 'json'
                    ], PHP_QUERY_RFC3986 );

                Log::doJsonLog( "Not Found in Cache. Call url for Quote:  " . $url );
                $mh->createResource( $url, $this->_curlOptions, $job[ 'jid' ] . "-" . $job[ 'jpassword' ] . "-" . $this->fixedDelivery );
            }
        }


        /**
         ************************** SECOND PART: CALL VENDOR *****************************
         */
        // execute call and retrieve the result
        $mh->multiExec();
        $res = $mh->getAllContents();

        // for each reply
        foreach ( $res as $jpid => $quote ) {

            // if some error occurred, log it and skip this job
            if ( $mh->hasError( $jpid ) ) {
                Log::doJsonLog( $mh->getError( $jpid ) );
                continue;
            }

            Log::doJsonLog( $quote );

            // parse the result and check if the vendor returned some error. In case, skip the quote
            $result_quote = json_decode( $quote, TRUE );
            if ( $result_quote[ 'code' ] != 1 ) {
                Log::doJsonLog( "HTS returned an error. Skip quote" );
                continue;
            }

            // quote received correctly. Create a proper Shop_ItemHTSQuoteJob to hold it, and add it to the Shop_Cart
            $itemCart = $this->__prepareQuotedJobCart( $jpid, $volAnalysis, $subject, $result_quote );

            // NOTE: In this case we only have to delete the single quote, and replace it with the new one,
            // but all the other quotes the user might have asked must remain in cache,
            // so do not pass the "true" parameter, as done before in "OutsourceTo_Translated::__processOutsourcedJobs".
            // See GUIDE->"NORMAL QUOTES vs OUTSOURCED QUOTES" for details
            $this->__addCartElement( $itemCart );

            Log::doJsonLog( $itemCart );
        }
    }


    /**
     * Create a Shop_ItemHTSQuoteJob which wraps a "job already outsourced" vendor reply
     *
     * @param string $jpid
     * @param array $volAnalysis
     * @param string $subject
     * @param array $apiCallResult
     *
     * @return Shop_ItemHTSQuoteJob
     */
    private function __prepareOutsourcedJobCart( $jpid, $volAnalysis, $subject, $apiCallResult ) {
        // $jpid is always in the form "JOBID-JOBPASSWORD-outsourced". Get job id and password from it
        list( $jid, $jpsw, ) = explode( "-", $jpid );

        $langPairs = $this->getLangPairs($jid, $jpsw, $volAnalysis);

        if(!empty($langPairs)){
            // instantiate the Shop_ItemHTSQuoteJob and fill it
            // SAMPLE VENDOR REPLY:
            //  {
            //      "code": 1,
            //      "outsourced": 1,
            //      "price": FLOAT,
            //      "delivery": "YYYY-MM-DD HH:MM:SS",
            //      "type_of_service": "STRING",            <- "professional" or "premium"
            //      "link_to_status": "STRING"              <- where to see the order status
            //  }
            $itemCart                    = new Shop_ItemHTSQuoteJob();
            $itemCart[ 'id' ]            = $jpid;
            $itemCart[ 'project_name' ]  = $volAnalysis[ 'name' ];
            $itemCart[ 'name' ]          = "MATECAT_$jpid";
            $itemCart[ 'source' ]        = $langPairs['source'];
            $itemCart[ 'target' ]        = $langPairs['target'];
            $itemCart[ 'words' ]         = $this->getTotalPayableWords($volAnalysis);
            $itemCart[ 'subject' ]       = $subject;
            $itemCart[ 'currency' ]      = $this->currency;
            $itemCart[ 'timezone' ]      = $this->timezone;
            $itemCart[ 'quote_result' ]  = $apiCallResult[ 'code' ];
            $itemCart[ 'outsourced' ]    = 1;
            $itemCart[ 'typeOfService' ] = $apiCallResult[ 'type_of_service' ];
            $itemCart[ 'price' ]         = $apiCallResult[ 'price' ];
            $itemCart[ 'delivery' ]      = $apiCallResult[ 'delivery' ];
            $itemCart[ 'link_to_status' ]= $apiCallResult[ 'link_to_status' ];
            $itemCart[ 'quantity' ]      = 1;

            // NOTE:
            //  vendor returns an error in case words = 0, therefore, during functions
            //  OutsourceTo_Translated::__processOutsourcedJobs and OutsourceTo_Translated::__processNormalJobs, they
            //  are rounded to the nearest int and set at minimum to 1. Here, since they are recomputed,
            //  we need to do the same trick again. Alternatively, they might be passed as parameter

            return $itemCart;
        }

        return null;
    }


    /**
     * Create a Shop_ItemHTSQuoteJob which wraps a quote vendor reply
     *
     * @param string $jpid
     * @param array $volAnalysis
     * @param string $subject
     * @param array $apiCallResult
     *
     * @return Shop_ItemHTSQuoteJob
     */
    private function __prepareQuotedJobCart( $jpid, $volAnalysis, $subject, $apiCallResult ) {
        // $jpid is always in the form "JOBID-JOBPASSWORD-outsourced". Get job id and password from it
        list( $jid, $jpsw, ) = explode( "-", $jpid );
        $subject_handler = Langs_LanguageDomains::getInstance();
        $subjectsHashMap = $subject_handler->getEnabledHashMap();

        $langPairs = $this->getLangPairs($jid, $jpsw, $volAnalysis);
        $source = $langPairs['source'];
        $target = $langPairs['target'];

        // instantiate the Shop_ItemHTSQuoteJob and fill it
        // SAMPLE VENDOR REPLY:
        //  {
        //      "code": 1,
        //      "message": "OK",
        //      "pid": INTEGER,                             <- project id in vendor system
        //      "showquote": INTEGER,
        //      "quote_available": INTEGER,                 <- quote available for this job? 0 or 1
        //      "show_translator_data": INTEGER,            <- data about translator available? 0 or 1
        //      "show_revisor_data": INTEGER,               <- data about revisor available? 0 or 1
        //      "translation": {
        //          "price": FLOAT,
        //          "delivery": "YYYY-MM-DD HH:MM:SS",
        //          "translator_name": "STRING",
        //          "translator_native_lang": "STRING",
        //          "translator_words_total": INTEGER,      <- words translated in last 12 months
        //          "translator_words_specific": INTEGER,   <- words translated in last 12 months in subject specified by user
        //          "translator_vote": FLOAT,
        //          "translator_positive_feedbacks": INTEGER,
        //          "translator_total_feedbacks": INTEGER,
        //          "translator_experience_years": INTEGER,
        //          "translator_education": "STRING",
        //          "chosen_subject": "STRING",
        //          "translator_subjects": "STRING"
        //      },
        //      "revision": {
        //          "price": FLOAT,                         <- the price surplus (to be added to translation price)
        //          "delivery": "YYYY-MM-DD HH:MM:SS",
        //          "revisor_vote": FLOAT                   <- the delta improvement. To be added to translator vote
        //      }
        //  }

        $itemCart                        = new Shop_ItemHTSQuoteJob();
        $itemCart[ 'id' ]                = $jpid;
        $itemCart[ 'project_name' ]      = $volAnalysis[ 'name' ];
        $itemCart[ 'name' ]              = "MATECAT_$jpid";
        $itemCart[ 'source' ]            = $source;
        $itemCart[ 'target' ]            = $target;
        $itemCart[ 'words' ]             = $this->getTotalPayableWords($volAnalysis);
        $itemCart[ 'subject' ]           = $subject;
        $itemCart[ 'subject_printable' ] = $subjectsHashMap[ $subject ];
        $itemCart[ 'currency' ]          = $this->currency;
        $itemCart[ 'timezone' ]          = $this->timezone;
        $itemCart[ 'quote_result' ]      = $apiCallResult[ 'code' ];
        $itemCart[ 'outsourced' ]        = 0;
        $itemCart[ 'quote_available' ]   = $apiCallResult[ 'quote_available' ];
        $itemCart[ 'typeOfService' ]     = $this->typeOfService;

        // if the vendor has a quote available for this job, then get the info
        if( $itemCart[ 'quote_result' ] == 1 && $itemCart[ 'quote_available' ] == 1 ) {
            $itemCart['price']                = $apiCallResult['translation']['price'];
            $itemCart['delivery']             = $apiCallResult['translation']['delivery'];
            $itemCart['r_price']              = $apiCallResult['revision']['price'];
            $itemCart['r_delivery']           = $apiCallResult['revision']['delivery'];
            $itemCart['quote_pid']            = $apiCallResult['pid'];
            $itemCart['show_info']            = $apiCallResult['showquote'];
            $itemCart['show_translator_data'] = $apiCallResult['show_translator_data'];

            // if the vendor provided data about translator, then get it
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

                // if the vendor provided data about revisor, then get it
                if( $itemCart['show_revisor_data'] == 1 ) {
                    $itemCart['r_vote'] = $apiCallResult['revision']['revisor_vote'];
                }
            }
        }

        // NOTE:
        //  vendor returns an error in case words = 0, therefore, during functions
        //  OutsourceTo_Translated::__processOutsourcedJobs and OutsourceTo_Translated::__processNormalJobs, they
        //  are rounded to the nearest int and set at minimum to 1. Here, since they are recomputed,
        //  we need to do the same trick again. Alternatively, they might be passed as parameter

        return $itemCart;
    }


    /**
     * Update localization info of a Shop_ItemHTSQuoteJob stored in cache.
     *  A proper update function does not exists, therefore the procedure is:
     *      - get the current element from cache
     *      - update the parameters
     *      - re-add it to the cache
     *
     * @see OutsourceTo_Translated::__addCartElement
     *
     * @param string $cartId
     * @param string $newCurrency
     * @param int $newTimezone
     * @param string $newTypeOfService
     *
     */
    private function __updateCartElements( $cartId, $newCurrency, $newTimezone, $newTypeOfService = null ) {
        $cartElem = Shop_Cart::getInstance( 'outsource_to_external_cache' )->getItem( $cartId );
        $cartElem[ "currency" ] = !empty( $newCurrency ) ? $newCurrency : $cartElem[ "currency" ];
        $cartElem[ "timezone" ] = !empty( $newTimezone ) ? $newTimezone : $cartElem[ "timezone" ];
        $cartElem[ "typeOfService" ] = !empty( $newTypeOfService ) ? $newTypeOfService : $cartElem[ "typeOfService" ];

        $this->__addCartElement( $cartElem );
    }


    /**
     * Add a cart element in all the data-structures it needs to be added, in order to ensure consistency.
     *  As described in GUIDE->"TWO TYPES OF Shop_Cart", there are 2 carts Matecat relies on.
     *  This function adds the cart element in both of them, by calling twice the
     *  OutsourceTo_Translated::__addCartElementToCart function.
     *  Moreover, the cart element is added to the array of results, $this->_quote_result
     *
     * @see OutsourceTo_Translated::__addCartElementToCart
     *
     * @param Shop_ItemHTSQuoteJob $cartElem
     * @param bool $deleteOnPartialMatch
     *
     */
    private function __addCartElement( $cartElem, $deleteOnPartialMatch = false ) {
        $this->__addCartElementToCart( $cartElem, 'outsource_to_external', $deleteOnPartialMatch );
        $this->__addCartElementToCart( $cartElem, 'outsource_to_external_cache', $deleteOnPartialMatch );

        $this->_quote_result[] = array( $cartElem );
    }


    /**
     * Add a cart element in the specified Shop_Cart.
     *  In order to add an element, it is necessary to delete it first. Therefore "delItem" function is called first.
     *
     *  IMPORTANT: how deletion works.
     *      "delItem" function deletes ALL the carts starting with $idToUse.
     *      Hence, even a partial matching is accepted and the element is deleted.
     *
     *      This function always receives $cartElem parameters whose id is always in the form:
     *          JOBID-JOBPASSWORD-outsourced        <- when caching outsourced jobs
     *          JOBID-JOBPASSWORD-INTEGER           <- when caching normal quotes
     *
     *      In case delItem is called with the full $cartElem ID (when parameter $deleteOnPartialMatch is false)
     *      only one element (at most) in the cart will match the whole id, therefore a single deletion is made.
     *      In case delItem is called with only job id and password as ID (when parameter $deleteOnPartialMatch is true)
     *      all the data about that job (regardless of the delivery date or whether it was outsourced or not) is deleted.
     *
     *
     * @see OutsourceTo_Translated::__processOutsourcedJobs for when parameter $deleteOnPartialMatch is set to true
     *
     * @param Shop_ItemHTSQuoteJob $cartElem
     * @param string $cartName
     * @param bool $deleteOnPartialMatch
     *
     */
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

    public function getOutsourceConfirm(){
        $urls = [];
        foreach ( $this->jobList as $job ) {
            $urls[ ] = sprintf( $this->_outsource_url_confirm, $job[ 'jid' ], $job[ 'jpassword' ] );
        }
        return $urls;
    }

    /**
     * @param $volAnalysis
     * @return int|mixed
     */
    private function getTotalPayableWords($volAnalysis)
    {
        $total = 0;
        $jobList = $this->jobList;

        foreach ($jobList as $job){

            $jid       = $job['jid'];
            $jpassword = $job['jpassword'];

            foreach ($volAnalysis['jobs'] as $jobVolAnalysis){
                foreach ($jobVolAnalysis['chunks'] as $chunkVolAnalysis){
                    if($jid == $jobVolAnalysis['id'] and $jpassword == $chunkVolAnalysis['password']){
                        $total = $total + $chunkVolAnalysis['total_equivalent'];
                    }
                }
            }
        }

        if($total === 0){
            return 1;
        }

        return (int)$total;
    }

    /**
     * @param $jid
     * @param $password
     * @param $volAnalysis
     * @return array
     */
    private function getLangPairs($jid, $password, $volAnalysis)
    {
        foreach ($volAnalysis['jobs'] as $job){
            foreach ($job['chunks'] as $chunk){
                if($job['id'] == $jid and $chunk['password'] === $password){
                    return [
                        'source' => $job['source'],
                        'target' => $job['target'],
                    ];
                }
            }
        }

        return [];
    }
}
