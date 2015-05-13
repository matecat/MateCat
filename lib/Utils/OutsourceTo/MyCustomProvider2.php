<?php
/**
 * Created by PhpStorm.
 */

/**
 * Concrete Class to negotiate a Quote/Login/Review/Confirm communication
 *
 * ** WARNING THIS EXAMPLE DON'T WORKS BECAUSE OF NOT-EXISTENT URL: http://www.myprovider2.test/url_for_quote/
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 29/04/14
 * Time: 10.48
 *
 */
class OutsourceTo_MyCustomProvider2 extends OutsourceTo_AbstractProvider {

    /**
     * Perform Quotes to the selected Provider
     *
     * @param array|null $volAnalysis
     *
     * @return void
     */
    public function performQuote( $volAnalysis = null ) {


        //call matecat API to get Project information
        $project_url_api = INIT::$HTTPHOST . INIT::$BASEURL . "api/status?id_project=" . $this->pid . "&project_pass=" . $this->ppassword;
        $raw_volAnalysis = file_get_contents( $project_url_api );
        $volAnalysis = json_decode( $raw_volAnalysis, true );

        //prepare the store variable
        $client_output = array ();

        foreach( $this->jobList as $job ){

            //trim decimals to int
            $job_payableWords =  (int)$volAnalysis[ 'data' ][ 'jobs' ][ $job[ 'jid' ] ][ 'totals' ][ $job['jpassword'] ]['TOTAL_PAYABLE'][0];

            /*
             * //languages are in the form:
             *
             *     "langpairs":{
             *          "5888-e94bd2f79afd":"en-GB|fr-FR",
             *          "5890-e852ca45c66e":"en-GB|it-IT"
             *   },
             *
             */
            $langPairs = $volAnalysis[ 'jobs' ][ 'langpairs' ][ $job[ 'jid' ] . "-" .$job['jpassword'] ];

            $_langPairs_array = explode( "|", $langPairs );
            $source = $_langPairs_array[0];
            $target = $_langPairs_array[1];

            /*
             * Build here your post-fields for the external system quote request
             * this is only an example
             *
             */
            $server_output[ $job[ 'jid' ] . "-" . $job[ 'jpassword' ] ] = array(

                //unique identifier for the project
                    'id' => $job[ 'jid' ] . "-" . $job[ 'jpassword' ],

                //name for the job/chunk, not important for client interface
                    'name' => 'MATECAT_' . $job[ 'jid' ] . "-" . $job[ 'jpassword' ],

                //username for authentication on the remote service
                    'user' => 'MyProviderUser',

                //password for authentication on the remote service
                    'pass' => 'MyProviderPass',

                //source language of the job/chunk
                    'source' => $source,

                //target language of the job/chunk
                    'target' => $target,

                //the payable words of the job
                    'words' => $job_payableWords, //not important for client interface

            );

            /*
             * Call for Quote
             */
            $responseQuote = Utils::curl_post( 'http://www.myprovider2.test/url_for_quote/', $server_output );

            /**
             * Field identified with * are not mandatory for the interface but they will posted out to the
             * MyProvider service
             *
             * @see public/js/outsource.js
             *      @@ Line:132 @@
             *      //IMPORTANT post out the quotes
             *      $('#continueForm input[name=quoteData]').attr('value', JSON.stringify( UI.quoteResponse ) );
             *
             * in JSON format
             *
             * <pre>
             *  array (
             *    'url_ok' => '',
             *    'url_ko' => '',
             *    'quoteData' => '[{"id":"5914-d2f63f48c079","name":"MATECAT_5914-d2f63f48c079","user":"MyProviderUser","pass":"MyProviderPass","source":"en-GB","price":16.2,"target":"it-IT","words":108,"show_info":"1","delivery_date":"1999-12-31T23:59:59Z"}]',
             *  )
             * </pre>
             *
             * So, put here the fields and values returned from quote that you need to know on the external service
             *
             * N.B. These values and key names are only for example purpose
             *
             */
            $client_output[ $job[ 'jid' ] . "-" . $job[ 'jpassword' ] ] = array(

                //unique identifier for the quote of the project
                'id' => $job[ 'jid' ] . "-" . $job[ 'jpassword' ],

                //unique identifier for the quote of the project
                'quote_pid' => $responseQuote[ 'quote_pid' ],

                //name for the job/chunk, not important for client interface
                'name' => 'MATECAT_' . $job[ 'jid' ] . "-" . $job[ 'jpassword' ],

                //source language of the job/chubk
                'source' => $source,

                ///hard code here you price for language or get your prices from a table or an array
                'price' => $responseQuote['price'],

                //target language of the job/chunk
                'target' => $target,

                //the payable words of the job
                'words' => $job_payableWords, //not important for client interface

                //used by interface to automatically show a little review
                'show_info' => '1',

                //the estimated delivery date in UTC format, used by interface
                'delivery_date' => $responseQuote['delivery_date'],

            );

        }

        $this->_quote_result = $client_output;

    }

}