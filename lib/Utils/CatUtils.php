<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/MyMemory.copyrighted.php";

define("LTPLACEHOLDER", "##LESSTHAN##");
define("GTPLACEHOLDER", "##GREATERTHAN##");
define("AMPPLACEHOLDER", "##AMPPLACEHOLDER##");
//define("NBSPPLACEHOLDER", "<x id=\"nbsp\"/>");

class CatUtils {

    const splitPlaceHolder     = '##$_SPLIT$##';

    const lfPlaceholderClass   = '_0A';
    const crPlaceholderClass   = '_0D';
    const crlfPlaceholderClass = '_0D0A';
    const lfPlaceholder        = '##$_0A$##';
    const crPlaceholder        = '##$_0D$##';
    const crlfPlaceholder      = '##$_0D0A$##';
    const lfPlaceholderRegex   = '/\#\#\$_0A\$\#\#/g';
    const crPlaceholderRegex   = '/\#\#\$_0D\$\#\#/g';
    const crlfPlaceholderRegex = '/\#\#\$_0D0A\$\#\#/g';

    const tabPlaceholder       = '##$_09$##';
    const tabPlaceholderClass  = '_09';
    const tabPlaceholderRegex  = '/\#\#\$_09\$\#\#/g';

    const nbspPlaceholder       = '##$_A0$##';
    const nbspPlaceholderClass  = '_A0';
    const nbspPlaceholderRegex  = '/\#\#\$_A0\$\#\#/g';

    public static $cjk = array( 'zh' => 1.8, 'ja' => 2.5, 'ko' => 2.5, 'km' => 5 );

    public static function isCJK( $langCode ){
        return array_key_exists( explode( '-', $langCode )[0], self::$cjk ) ;
    }

    // ----------------------------------------------------------------

    public static function placeholdamp($s) {
        $s = preg_replace("/\&/", AMPPLACEHOLDER, $s);
        return $s;
    }

    public static function restoreamp($s) {
        $pattern = "#" . AMPPLACEHOLDER . "#";
        $s = preg_replace($pattern, self::unicode2chr("&"), $s);
        return $s;
    }

    public static function parse_time_to_edit($ms) {
        if ($ms <= 0) {
            return array("00", "00", "00", "00");
        }

        $usec = $ms % 1000;

        if ( !is_numeric( $ms ) ) {
            throw new InvalidArgumentException("Wrong DataType provided: " . var_export( $ms, true ) . "\n Expected integer.");
        }

        $ms = (int)$ms;

        $ms = floor($ms / 1000);

        $seconds = str_pad($ms % 60, 2, "0", STR_PAD_LEFT);
        $ms = floor($ms / 60);

        $minutes = str_pad($ms % 60, 2, "0", STR_PAD_LEFT);
        $ms = floor($ms / 60);

        $hours = str_pad($ms % 60, 2, "0", STR_PAD_LEFT);
        $ms = floor($ms / 60);

        return array($hours, $minutes, $seconds, $usec);
    }

    public static function dos2unix( $dosString ){
        $dosString = str_replace( "\r\n","\r", $dosString );
        $dosString = str_replace( "\n","\r", $dosString );
        $dosString = str_replace( "\r","\n", $dosString );
        return $dosString;
    }

    /**
     * @param $segment
     * @return mixed
     * @deprecated
     */
    private static function placehold_xml_entities($segment) {
        $pattern ="|&#(.*?);|";
        $res=preg_replace($pattern,"<x id=\"XMLENT$1\"/>",$segment);
        return $res;
    }

    public static function restore_xml_entities($segment) {
        return preg_replace ("|<x id=\"XMLENT(.*?)\"/>|","&#$1",$segment);
    }

    public static function placehold_xliff_tags($segment) {

        //remove not existent </x> tags
        $segment = preg_replace('|(</x>)|si', "", $segment);

        //$segment=preg_replace('|<(g\s*.*?)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);
        $segment = preg_replace('|<(g\s*id=["\']+.*?["\']+\s*[^<>]*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);

        $segment = preg_replace('|<(/g)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);

        $segment = preg_replace('|<(x .*?/?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('#<(bx[ ]{0,}/?|bx .*?/?)>#si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('#<(ex[ ]{0,}/?|ex .*?/?)>#si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(bpt\s*.*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/bpt)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(ept\s*.*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/ept)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(ph .*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/ph)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(it .*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/it)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(mrk\s*.*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/mrk)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);

        return self::__encode_tag_attributes( $segment );
    }

    private static function __encode_tag_attributes( $segment ){

        if( !function_exists( 'callback_encode' ) ){
            function callback_encode( $matches ) {
                return LTPLACEHOLDER . base64_encode( $matches[1] ) . GTPLACEHOLDER;
            }
        }

        return preg_replace_callback( '/' . LTPLACEHOLDER . '(.*?)' . GTPLACEHOLDER . '/u'
                , 'callback_encode'
                , $segment
        ); //base64 of the tag content to avoid unwanted manipulation

    }

    private static function __decode_tag_attributes( $segment ){

        if( !function_exists( 'callback_decode' ) ){
            function callback_decode( $matches ) {
                $_match =  base64_decode( $matches[1] );
                return LTPLACEHOLDER . $_match . GTPLACEHOLDER;
            }
        }

        return preg_replace_callback( '/' . LTPLACEHOLDER . '(.*?)' . GTPLACEHOLDER . '/u'
                , 'callback_decode'
                , $segment
        ); //base64 decode of the tag content to avoid unwanted manipulation

    }

    private static function restore_xliff_tags($segment) {
        $segment = self::__decode_tag_attributes( $segment );

        preg_match_all( '/[\'"]base64:(.+)[\'"]/U', $segment, $html, PREG_SET_ORDER ); // Ungreedy
        foreach( $html as $tag_attribute ){
            $segment = preg_replace( '/[\'"]base64:(.+)[\'"]/U', '"' . base64_decode( $tag_attribute[ 1 ] ) . '"', $segment, 1 );
        }

        $segment = str_replace(LTPLACEHOLDER, "<", $segment);
        $segment = str_replace(GTPLACEHOLDER, ">", $segment);
        return $segment;
    }

    public static function restore_xliff_tags_for_view($segment) {
        $segment = self::__decode_tag_attributes( $segment );

        preg_match_all( '/equiv-text\s*?=\s*?["\'](.*?)["\']/', $segment, $html, PREG_SET_ORDER );
        foreach( $html as $tag_attribute ){
            //replace subsequent elements excluding already encoded
            $segment = preg_replace( '/equiv-text\s*?=["\'](?!base64:)(.*?)["\']/', 'equiv-text="base64:' . base64_encode( $tag_attribute[ 1 ] ) . "\"", $segment, 1 );
        }

        $segment = str_replace(LTPLACEHOLDER, "&lt;", $segment);
        $segment = str_replace(GTPLACEHOLDER, "&gt;", $segment);
        return $segment;
    }



     private static function get_xliff_tags($segment) {

        //remove not existent </x> tags
        $segment = preg_replace('|(</x>)|si', "", $segment);

        $matches=array();
        $match=array();


        $res=preg_match('|(<g\s*id=["\']+.*?["\']+\s*[^<>]*?>)|si',$segment, $match);
        if ($res and isset($match[0])){
            $matches[]=$match[0];
        }

        $segment = preg_replace('|<(/g)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(x.*?/?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('#<(bx[ ]{0,}/?|bx .*?/?)>#si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('#<(ex[ ]{0,}/?|ex .*?/?)>#si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(bpt\s*.*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/bpt)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(ept\s*.*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/ept)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(ph\s*.*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/ph)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(it\s*.*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/ph)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(it\s*.*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/it)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(mrk\s*.*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/mrk)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        return $segment;
    }

    public static function stripTags($text) {
        $pattern_g_o = '|(<.*?>)|';
        $pattern_g_c = '|(</.*?>)|';
        $pattern_x = '|(<.*?/>)|';

        $text = preg_replace($pattern_x, "", $text);

        $text = preg_replace($pattern_g_o, "", $text);
        $text = preg_replace($pattern_g_c, "", $text);
        return $text;
    }

    public static function raw2DatabaseXliff( $segment ){

        $segment = self::placehold_xliff_tags($segment);
        $segment = htmlspecialchars(
                html_entity_decode($segment, ENT_NOQUOTES, 'UTF-8'),
                ENT_NOQUOTES | 16, 'UTF-8', false
        );

        //Substitute 4(+)-byte characters from a UTF-8 string to htmlentities
        $segment = preg_replace_callback( '/([\xF0-\xF7]...)/s', 'CatUtils::htmlentitiesFromUnicode', $segment );

        //replace all incoming &nbsp; ( \xA0 ) with normal spaces ( \x20 ) as we accept only ##$_A0$##
        $segment = str_replace( self::unicode2chr(0Xa0) , " ", $segment );

        //encode all not valid XML entities
        $segment = preg_replace('/&(?!lt;|gt;|amp;|quot;|apos;|#[x]{0,1}[0-9A-F]{1,7};)/', '&amp;' , $segment );

        $segment = self::restore_xliff_tags($segment);
        return $segment;

    }

    /**
     * Perform a computation on the string to find the length of the strings separated by the placeholder
     *
     * @param $segment
     * @param $separateWithChar
     *
     * @return array
     */
    public static function parseSegmentSplit( $segment, $separateWithChar = '' ){
        $split_chunks = explode( self::splitPlaceHolder, $segment );
        $chunk_positions = array();
        $last = 0;

        if( count( $split_chunks ) > 1){
            $segment = "";
            $chunk_positions[] = 0;
            foreach( $split_chunks as $pos => $chunk ){
                if ( strlen( $chunk ) == 0 ) break; //remove eventually present null string

                //WARNING We count length in NO MULTIBYTE mode
                $separator_len = strlen( $separateWithChar );
                $separator     = $separateWithChar;

                //if the last char of the last chunk AND the first of the next are spaces, don't add another one
                if( substr( $chunk, -1 ) == $separateWithChar || @substr( $split_chunks[ $pos + 1 ], 0, 1 ) == $separateWithChar ){
                    $separator_len = 0;
                    $separator = '';
                }

                $chunk_positions[] = strlen( $chunk ) + $separator_len;
                $segment .= $chunk . $separator;

            }
        }

        return array( $segment, $chunk_positions );
    }

    /**
     * Create a string with placeholders in the right position based on the struct
     *
     * @param       $segment
     * @param array $chunk_positions
     *
     * @return string
     */
    public static function reApplySegmentSplit( $segment, Array $chunk_positions ){

        $string_chunks = array();
        $last_sum = 0;
        foreach ( $chunk_positions as $pos => $value ){
            if( isset( $chunk_positions[ $pos + 1 ] ) ){
                $string_chunks[] = substr( $segment, $chunk_positions[ $pos ] + $last_sum, $chunk_positions[ $pos + 1 ] );
                $last_sum += $chunk_positions[ $pos ];
            }

        }

        if( empty( $string_chunks ) ) return $segment;
        else return implode( self::splitPlaceHolder, $string_chunks );

    }

    public static function view2rawxliff($segment) {

        //normal control characters should not be sent by the client
        $segment = str_replace(
                array(
                        "\r", "\n", "\t",
                        "&#0A;", "&#0D;", "&#09;"
                ), "", $segment
        );

        //Replace br placeholders
        $segment = str_replace( self::crlfPlaceholder, "\r\n", $segment );
        $segment = str_replace( self::lfPlaceholder,"\n", $segment );
        $segment = str_replace( self::crPlaceholder,"\r", $segment );
        $segment = str_replace( self::tabPlaceholder,"\t", $segment );

        // input : <g id="43">bang & olufsen < 3 </g> <x id="33"/>; --> valore della funzione .text() in cat.js su source, target, source suggestion,target suggestion
        // output : <g> bang &amp; olufsen are > 555 </g> <x/>
        // caso controverso <g id="4" x="&lt; dfsd &gt;">
        $segment = self::placehold_xliff_tags($segment);
        $segment = htmlspecialchars(
            html_entity_decode($segment, ENT_NOQUOTES, 'UTF-8'),
            ENT_NOQUOTES, 'UTF-8', false
        );

        //Substitute 4(+)-byte characters from a UTF-8 string to htmlentities
        $segment = preg_replace_callback( '/([\xF0-\xF7]...)/s', 'CatUtils::htmlentitiesFromUnicode', $segment );

        //replace all incoming &nbsp; ( \xA0 ) with normal spaces ( \x20 ) as we accept only ##$_A0$##
        $segment = str_replace( self::unicode2chr(0Xa0) , " ", $segment );

        // now convert the real &nbsp;
        $segment = str_replace( self::nbspPlaceholder, self::unicode2chr(0Xa0) , $segment );

        //encode all not valid XML entities
        $segment = preg_replace('/&(?!lt;|gt;|amp;|quot;|apos;|#[x]{0,1}[0-9A-F]{1,7};)/', '&amp;' , $segment );

        $segment = self::restore_xliff_tags($segment);
        return $segment;
    }

    public static function rawxliff2view($segment) {
        // input : <g id="43">bang &amp; &lt; 3 olufsen </g>; <x id="33"/>
        //$segment = self::placehold_xml_entities($segment);
        $segment = self::placehold_xliff_tags($segment);

        //replace all outgoing spaces couples to a space and a &nbsp; so they can be displayed to the browser
        $segment = preg_replace('/[ ]{2}/', "&nbsp; ", $segment);
        $segment = preg_replace('/[ ]$/', "&nbsp;", $segment);

        $segment = html_entity_decode($segment, ENT_NOQUOTES | 16 /* ENT_XML1 */, 'UTF-8');
        $segment = preg_replace_callback( '/([\xF0-\xF7]...)/s', 'CatUtils::htmlentitiesFromUnicode', $segment );

        // restore < e >
        $segment = str_replace("<", "&lt;", $segment);
        $segment = str_replace(">", "&gt;", $segment);
        $segment = preg_replace('|<(.*?)>|si', "&lt;$1&gt;", $segment);

        $segment = self::restore_xliff_tags_for_view($segment);

        $segment = str_replace("\r\n", self::crlfPlaceholder, $segment );
        $segment = str_replace("\n", self::lfPlaceholder, $segment );
        $segment = str_replace("\r", self::crPlaceholder, $segment ); //x0D character
        $segment = str_replace("\t", self::tabPlaceholder, $segment ); //x09 character
        $segment = preg_replace( '/[\x{c2}]{0,1}\x{a0}/u', self::nbspPlaceholder, $segment ); //xA0 character ( NBSP )
        return $segment;
    }

    /**
     * Used to export Database XML string into TMX files as valid XML
     *
     * @param $segment
     *
     * @return mixed
     */
    public static function rawxliff2rawview($segment) {
        // input : <g id="43">bang &amp; &lt; 3 olufsen </g>; <x id="33"/>
        $segment = self::placehold_xliff_tags($segment);
        $segment = htmlspecialchars( $segment, ENT_NOQUOTES, 'UTF-8', false );
        $segment = self::restore_xliff_tags_for_view($segment);
        return $segment;
    }

    public static function addSegmentTranslation( array $_Translation, array &$errors ) {

        try {
            //if needed here can be placed a check for affected_rows == 0 //( $updateRes )
            $updateRes = addTranslation( $_Translation );
        } catch ( Exception $e ){
            $errors[] = array( "code" => -101, "message" => $e->getMessage() );
        }

        return $errors;

    }

    /**
     * Make an estimation on performance
     * @param array $job_stats
     *
     * @return array
     */
    protected static function _performanceEstimationTime( array $job_stats ){

        $estimation_temp = getLastSegmentIDs($job_stats['id']);

        $estimation_concat = array();
        foreach( $estimation_temp as $sid ){
            $estimation_concat[] = $sid['id_segment'];
        }
        $estimation_seg_ids = implode( ",",$estimation_concat );

        if ($estimation_seg_ids) {
            //perform check on performance if single segment are set to check or globally Forced
            // Calculating words per hour and estimated completion
            $estimation_temp = getEQWLastHour($job_stats['id'], $estimation_seg_ids);
            if ($estimation_temp[0]['data_validity'] == 1) {
                $job_stats['WORDS_PER_HOUR'] = number_format($estimation_temp[0]['words_per_hour'], 0, '.', ',');
                // 7.2 hours
                // $job_stats['ESTIMATED_COMPLETION'] = number_format( ($job_stats['DRAFT']+$job_stats['REJECTED'])/$estimation_temp[0]['words_per_hour'],1);
                // 1 h 32 m
                // $job_stats['ESTIMATED_COMPLETION'] = date("G",($job_stats['DRAFT']+$job_stats['REJECTED'])/$estimation_temp[0]['words_per_hour']*3600) . "h " . date("i",($job_stats['DRAFT']+$job_stats['REJECTED'])/$estimation_temp[0]['words_per_hour']*3600) . "m";
                $job_stats['ESTIMATED_COMPLETION'] = date("z\d G\h i\m", ($job_stats['DRAFT'] + $job_stats['REJECTED'])*3600 / ( !empty( $estimation_temp[0]['words_per_hour'] ) ? $estimation_temp[0]['words_per_hour'] : 1 )-3600);
            }
        }

        return $job_stats;

    }

    /**
     * Perform analysis on single Job
     *
     * <pre>
     *      $job_stats = array(
     *          'id'                           => (int),
     *          'TOTAL'                        => (int),
     *          'TRANSLATED'                   => (int),
     *          'APPROVED'                     => (int),
     *          'REJECTED'                     => (int),
     *          'DRAFT'                        => (int),
     *          'ESTIMATED_COMPLETION'         => (int),
     *          'WORDS_PER_HOUR'               => (int),
     *      );
     * </pre>
     *
     * @param mixed $job_stats
     * @return mixed $job_stats
     */
    protected static function _getStatsForJob( $job_stats ) {

        $job_stats[ 'PROGRESS' ]             = ( $job_stats[ 'TRANSLATED' ] + $job_stats[ 'APPROVED' ] );
        $job_stats[ 'TOTAL_FORMATTED' ]      = number_format( $job_stats[ 'TOTAL' ], 0, ".", "," );
        $job_stats[ 'PROGRESS_FORMATTED' ]   = number_format( $job_stats[ 'TRANSLATED' ] + $job_stats[ 'APPROVED' ], 0, ".", "," );
        $job_stats[ 'APPROVED_FORMATTED' ]   = number_format( $job_stats[ 'APPROVED' ], 0, ".", "," );
        $job_stats[ 'REJECTED_FORMATTED' ]   = number_format( $job_stats[ 'REJECTED' ], 0, ".", "," );
        $job_stats[ 'DRAFT_FORMATTED' ]      = number_format( $job_stats[ 'DRAFT' ], 0, ".", "," );
        $job_stats[ 'TRANSLATED_FORMATTED' ] = number_format( $job_stats[ 'TRANSLATED' ], 0, ".", "," );

        $job_stats[ 'APPROVED_PERC' ]   = ( $job_stats[ 'APPROVED' ] ) / $job_stats[ 'TOTAL' ] * 100;
        $job_stats[ 'REJECTED_PERC' ]   = ( $job_stats[ 'REJECTED' ] ) / $job_stats[ 'TOTAL' ] * 100;
        $job_stats[ 'DRAFT_PERC' ]      = ( $job_stats[ 'DRAFT' ] / $job_stats[ 'TOTAL' ] * 100 );
        $job_stats[ 'TRANSLATED_PERC' ] = ( $job_stats[ 'TRANSLATED' ] / $job_stats[ 'TOTAL' ] * 100 );
        $job_stats[ 'PROGRESS_PERC' ]   = ( $job_stats[ 'PROGRESS' ] / $job_stats[ 'TOTAL' ] ) * 100;

        if($job_stats[ 'TRANSLATED_PERC' ] > 100) {
            $job_stats[ 'TRANSLATED_PERC' ] = 100;
        }

        if($job_stats[ 'PROGRESS_PERC' ] > 100) {
            $job_stats[ 'PROGRESS_PERC' ] = 100;
        }

        if($job_stats[ 'DRAFT_PERC' ] < 0) {
            $job_stats[ 'DRAFT_PERC' ] = 0;
        }

        $temp = array(
                $job_stats[ 'TRANSLATED_PERC' ],
                $job_stats[ 'DRAFT_PERC' ],
                $job_stats[ 'REJECTED_PERC' ],
                $job_stats[ 'PROGRESS_PERC' ],
        );
        $max = max( $temp );
        $min = min( $temp );
        if( $max < 99 || $min > 1 ) $significantDigits = 0;
        else $significantDigits = 2;

        $job_stats[ 'TRANSLATED_PERC_FORMATTED' ] = round( $job_stats[ 'TRANSLATED_PERC' ], $significantDigits );
        $job_stats[ 'DRAFT_PERC_FORMATTED' ]      = round( $job_stats[ 'DRAFT_PERC' ], $significantDigits );
        $job_stats[ 'APPROVED_PERC_FORMATTED' ]   = round( $job_stats[ 'APPROVED_PERC' ], $significantDigits );
        $job_stats[ 'REJECTED_PERC_FORMATTED' ]   = round( $job_stats[ 'REJECTED_PERC' ], $significantDigits );
        $job_stats[ 'PROGRESS_PERC_FORMATTED' ]   = round( $job_stats[ 'PROGRESS_PERC' ], $significantDigits );

        $todo = $job_stats[ 'DRAFT' ] + $job_stats[ 'REJECTED' ];
        if( $todo < 1 && $todo > 0 ){
            $job_stats[ 'TODO_FORMATTED' ] = 1;
            $job_stats[ 'TODO' ] = 1;
        } else {
            $job_stats[ 'TODO_FORMATTED' ] = number_format( $job_stats[ 'DRAFT' ] + $job_stats[ 'REJECTED' ], 0, ".", "," );
            $job_stats[ 'TODO' ] = (float)number_format( $job_stats[ 'DRAFT' ] + $job_stats[ 'REJECTED' ], 0, ".", "" );
        }

        $t = 'approved';
        if ($job_stats['TRANSLATED_FORMATTED'] > 0)
            $t = "translated";
        if ($job_stats['DRAFT_FORMATTED'] > 0)
            $t = "draft";
        if ($job_stats['REJECTED_FORMATTED'] > 0)
            $t = "draft";
        if( $job_stats['TRANSLATED_FORMATTED'] == 0 &&
                $job_stats['DRAFT_FORMATTED'] == 0 &&
                $job_stats['REJECTED_FORMATTED'] == 0 &&
                $job_stats['APPROVED_FORMATTED'] == 0 ){
            $t = 'draft';
        }
        $job_stats['DOWNLOAD_STATUS'] = $t;

        return $job_stats;

    }

    /**
     * Public interface to single Job Stats Info
     *
     *
     * @param int $jid
     * @param int $fid
     * @param string $jPassword
     * @return mixed $job_stats
     *
     * <pre>
     *      $job_stats = array(
     *          'id'                           => (int),
     *          'TOTAL'                        => (int),
     *          'TRANSLATED'                   => (int),
     *          'APPROVED'                     => (int),
     *          'REJECTED'                     => (int),
     *          'DRAFT'                        => (int),
     *          'ESTIMATED_COMPLETION'         => (int),
     *          'WORDS_PER_HOUR'               => (int),
     *      );
     * </pre>
     *
     */
    public static function getStatsForJob( $jid, $fid = null, $jPassword = null ) {

        $job_stats = getStatsForJob($jid, $fid, $jPassword);
        $job_stats = $job_stats[0];

        $job_stats = self::_getStatsForJob($job_stats); //true set estimation check if present
        return self::_performanceEstimationTime($job_stats);

    }

    /**
     * @param WordCount_Struct $wCount
     *
     * @return array
     */
    public static function getFastStatsForJob( WordCount_Struct $wCount, $performanceEstimation = true ){

        $job_stats = array();
        $job_stats[ 'id' ]         = $wCount->getIdJob();
//        $job_stats[ 'NEW' ]        = $wCount->getNewWords();
        $job_stats[ 'DRAFT' ]      = $wCount->getNewWords() + $wCount->getDraftWords();
        $job_stats[ 'TRANSLATED' ] = $wCount->getTranslatedWords();
        $job_stats[ 'APPROVED' ]   = $wCount->getApprovedWords();
        $job_stats[ 'REJECTED' ]   = $wCount->getRejectedWords();

        //sometimes new_words + draft_words < 0 (why?). If it happens, set draft words to 0
        if($job_stats[ 'DRAFT' ] < 0 ) {
            $job_stats[ 'DRAFT' ] = 0;
        }

        //avoid division by zero warning
        $total = $wCount->getTotal();
        $job_stats[ 'TOTAL' ]      = ( $total == 0 ? 1 : $total );
        $job_stats = self::_getStatsForJob($job_stats); //true set estimation check if present

        if( !$performanceEstimation ){
            return $job_stats;
        }

        return self::_performanceEstimationTime($job_stats);

    }

    public static function getStatsForFile($fid) {


        $file_stats = getStatsForFile($fid);

        $file_stats = $file_stats[0];
        $file_stats['ID_FILE'] = $fid;
        $file_stats['TOTAL_FORMATTED'] = number_format($file_stats['TOTAL'], 0, ".", ",");
        $file_stats['REJECTED_FORMATTED'] = number_format($file_stats['REJECTED'], 0, ".", ",");
        $file_stats['DRAFT_FORMATTED'] = number_format($file_stats['DRAFT'], 0, ".", ",");


        return $file_stats;
    }

    /**
     * Remove Tags and treat numbers as one word
     *
     * @param        $string
     * @param string $source_lang
     *
     * @return mixed|string
     */
    public static function clean_raw_string_4_word_count( $string, $source_lang = 'en-US' ){

        //return empty on string composed only by spaces
        //do nothing
        if ( preg_replace( '#[\p{Z}]+#u', '', $string ) == '' ) {
            return '';
        }

        if ( strpos( $source_lang, '-' ) !== false ) {
            $tmp_lang    = explode( '-', $source_lang );
            $source_lang = $tmp_lang[ 0 ];
            unset( $tmp_lang );
        }

        $string = preg_replace( '#<.*?>#si', ' ', $string );
        $string = preg_replace( '#<\/.*?>#si', ' ', $string );

        //remove ampersands and entities. Converters returns entities in xml, we want raw strings.
        $string = html_entity_decode( $string, ENT_XML1, 'UTF-8' );

        /**
         * Count numbers as One Word
         */
        if ( array_key_exists( $source_lang, self::$cjk ) ) {

            // 17/01/2014
            // sostituiamo i numeri con N nel CJK in modo da non alterare i rapporti carattere/parola
            // in modo che il conteggio
            // parole consideri i segmenti che differiscono per soli numeri come ripetizioni (come TRADOS)
            $string = preg_replace( '/[0-9]+([\.,][0-9]+)*/', 'N', $string );

        } else {

            // 08/02/2011 CONCORDATO CON MARCO : sostituire tutti i numeri con un segnaposto, in modo che il conteggio
            // parole consideri i segmenti che differiscono per soli numeri come ripetizioni (come TRADOS)
            $string = preg_replace( '/[0-9]+([\.,][0-9]+)*/', 'TRANSLATED_NUMBER', $string );

        }

        return $string;

    }

    /**
     * Count words in a string
     *
     * @param        $string
     * @param string $source_lang
     *
     * @return float|int
     */
    public static function segment_raw_wordcount( $string, $source_lang = 'en-US' ) {

        $string = self::clean_raw_string_4_word_count( $string, $source_lang );
        
        /**
         * Escape dash and underscore and replace them with Macro and Cedilla characters!
         *
         * Dash and underscore must not be treated as separated words
         * Macro and Cedilla characters are not replaced by unicode regular expressions below
         */
        $string = str_replace( array( '-', '_' ), array( "¯", '¸' ), $string );

        /**
         * Remove Unicode:
         * @see http://php.net/manual/en/regexp.reference.unicode.php
         * P -> Punctuation
         * Z -> Separator ( but not spaces )
         * C -> Other
         */
        $string = preg_replace( '#[\p{P}\p{Zl}\p{Zp}\p{C}]+#u', " ", $string );

        /**
         * Now reset chars
         */
        $string = str_replace( array( "¯", '¸' ), array( '-', '_' ), $string );


        //check for a string made of spaces only, after the string was cleaned
        $string_with_no_spaces = preg_replace( '#[\p{P}\p{Z}\p{C}]+#u', "", $string );
        if ( $string_with_no_spaces == "" ) {
            return 0;
        }

        //first two letter of code lang
        $source_lang_two_letter = explode( "-" , $source_lang )[0];
        if ( array_key_exists( $source_lang_two_letter, self::$cjk ) ) {

            $res = mb_strlen( $string_with_no_spaces, 'UTF-8' );

        } else {

            $string = str_replace( " ", "<sep>", $string );
            $string = str_replace( " ", "<sep>", $string ); //use breaking spaces also

            $words_array = explode( "<sep>", $string );
            $words_array = array_filter( $words_array, function ( $word ) {
                return trim( $word ) != "";
            } );

            $res = @count( $words_array );

        }

        return $res;

    }

    /**
     * Generate 128bit password with real uniqueness over single process instance
     *   N.B. Concurrent requests can collide ( Ex: fork )
     *
     * Minimum Password Length 12 Characters
     *
     * @param int $length
     *
     * @return bool|string
     */
    public static function generate_password( $length = 12 ) {

        $_pwd = md5( uniqid('',true) );
        $pwd = substr( $_pwd, 0, 6 ) . substr( $_pwd, -6, 6 );

        if( $length > 12 ){
            while( strlen($pwd) < $length ){
                $pwd .= self::generate_password();
            }
            $pwd = substr( $pwd, 0, $length );
        }

        return $pwd;

    }

    /**
     *
     * This function works only on unix machines. For BSD based change parameter of command file to Uppercase I
     * <pre>
     *      shell_exec( "file -I $tmpOrigFName" );
     * </pre>
     *
     * @param $toEncoding
     * @param $documentContent string Reference to the string document
     *
     * @return string
     * @throws Exception
     */
    public static function convertEncoding( $toEncoding, &$documentContent ) {

        //Example: The file is UTF-16 Encoded

        $tmpOrigFName = tempnam( "/tmp", mt_rand( 0, 1000000000 ) . uniqid( "", true ) );
        file_put_contents( $tmpOrigFName, $documentContent );

        $cmd = "file -i $tmpOrigFName";
        Log::doLog( $cmd );

        $file_info = shell_exec( $cmd );
        list( $file_info, $charset ) = explode( "=", $file_info );
        $charset = trim( $charset );

        if ( $charset == 'utf-16le' ) {
            $charset = 'Unicode';
        }

        //do nothing if "from" and "to" parameters are the equals
        if ( strtolower( $charset ) == strtolower( $toEncoding ) ) {
            return array( $charset, $documentContent );
        }

        $converted = iconv( $charset, $toEncoding . "//IGNORE", $documentContent );

        return array( $charset, $converted );

    }

    /**
     * Get the char code from a multi byte char
     *
     * 2/3 times faster than the old implementation
     *
     * @param $mb_char string Unicode Multibyte Char String
     *
     * @return int
     *
     */
    public static function fastUnicode2ord( $mb_char ){
        switch( strlen( $mb_char) ){
            case 1:
                return ord( $mb_char);
                break;
            case 2:
                return ( ord( $mb_char[0] ) - 0xC0 ) * 0x40 +
                         ord( $mb_char[1] ) - 0x80;
                break;
            case 3:
                return ( ord( $mb_char[0] ) - 0xE0 ) * 0x1000 +
                       ( ord( $mb_char[1] ) - 0x80 ) * 0x40 +
                         ord( $mb_char[2] ) - 0x80;
                break;
            case 4:
                return ( ord( $mb_char[0] ) - 0xF0 ) * 0x40000 +
                       ( ord( $mb_char[1] ) - 0x80 ) * 0x1000 +
                       ( ord( $mb_char[2] ) - 0x80 ) * 0x40 +
                         ord( $mb_char[3] ) - 0x80;
                break;
        }
    }

    public static function htmlentitiesFromUnicode( $str ){
        return "&#" . self::fastUnicode2ord( $str[1] ) . ";";
    }

    // multibyte string manipulation functions
    // source : http://stackoverflow.com/questions/9361303/can-i-get-the-unicode-value-of-a-character-or-vise-versa-with-php
    // original source : PHPExcel libary (http://phpexcel.codeplex.com/)
    // get the char from unicode code
    public static function unicode2chr( $o ) {
        if ( function_exists( 'mb_convert_encoding' ) ) {
            return mb_convert_encoding( '&#' . intval( $o ) . ';', 'UTF-8', 'HTML-ENTITIES' );
        } else {
            return chr( intval( $o ) );
        }
    }

    /**
     * @param $job
     * @return WordCount_Struct
     */
    public static function getWStructFromJobArray( $job ) {
        $job = Utils::ensure_keys($job, array(
            'new_words', 'draft_words', 'translated_words', 'approved_words', 'rejected_words',
            'status_analysis', 'jid', 'jpassword'
        ));

        $wStruct = new WordCount_Struct();

        $wStruct->setIdJob( $job['jid'] ) ;
        $wStruct->setJobPassword($job['jpassword']);
        $wStruct->setNewWords($job['new_words']);
        $wStruct->setDraftWords($job['draft_words']);
        $wStruct->setTranslatedWords($job['translated_words']);
        $wStruct->setApprovedWords($job['approved_words']);
        $wStruct->setRejectedWords($job['rejected_words']);

        // For projects created with No tm analysis enabled
        if ($wStruct->getTotal() == 0 && ($job['status_analysis'] == Constants_ProjectStatus::STATUS_DONE || $job['status_analysis'] == Constants_ProjectStatus::STATUS_NOT_TO_ANALYZE)) {
            $wCounter = new WordCount_Counter();
            $wStruct = $wCounter->initializeJobWordCount($job['jid'], $job['jpassword'] );
            Log::doLog("BackWard compatibility set Counter.");
            return $wStruct;
        }
        return $wStruct;
    }

    /**
     * Returns the string representing the overall quality for a job,
     * taking into account both old revision and new revision.
     *
     * @param $job
     * @return string
     */
    public static function getQualityOverallFromJobStruct( Jobs_JobStruct $job ) {

        $values = self::getQualityInfoFromJobStruct( $job );

        $result = null ;

        if ( in_array( Features::REVIEW_IMPROVED, $job->getProject()->getFeatures()->getCodes() ) ) {
            $result = @$values->is_pass ? 'excellent' : 'fail' ;
        } else {
            $result = strtolower( $values['minText'] ) ;
        }

        return $result ;
    }

    /**
     * @param Jobs_JobStruct $job
     *
     * @return array|\LQA\ChunkReviewStruct|null
     *
     */
    public static function getQualityInfoFromJobStruct( Jobs_JobStruct $job ){

        $result = null ;

        if ( in_array( Features::REVIEW_IMPROVED, $job->getProject()->getFeatures()->getCodes() ) ) {
            $review = \LQA\ChunkReviewDao::findOneChunkReviewByIdJobAndPassword( $job->id, $job->password ) ;
            $result = $review;
        } else {
            $struct = CatUtils::getWStructFromJobStruct( $job, $job->getProject()->status_analysis ) ;
            $jobQA = new Revise_JobQA(
                    $job->id, $job->password, $struct->getTotal()
            );
            $jobQA->retrieveJobErrorTotals();
            $result = $jobQA->evalJobVote();
        }

        return $result ;

    }

    /**
     * @param Jobs_JobStruct $job
     * @param                $analysis_status
     *
     * @return WordCount_Struct
     */
    public static function getWStructFromJobStruct( Jobs_JobStruct $job, $analysis_status ) {

        $wStruct = new WordCount_Struct();

        $wStruct->setIdJob( $job->id ) ;
        $wStruct->setJobPassword( $job->password );
        $wStruct->setNewWords($job->new_words );
        $wStruct->setDraftWords($job->draft_words );
        $wStruct->setTranslatedWords( $job->translated_words );
        $wStruct->setApprovedWords( $job->approved_words );
        $wStruct->setRejectedWords( $job->rejected_words );

        // For projects created with No tm analysis enabled
        if ($wStruct->getTotal() == 0 && ( $analysis_status == Constants_ProjectStatus::STATUS_DONE || $analysis_status == Constants_ProjectStatus::STATUS_NOT_TO_ANALYZE)) {
            $wCounter = new WordCount_Counter();
            $wStruct = $wCounter->initializeJobWordCount( $job->id, $job->password );
            Log::doLog("BackWard compatibility set Counter.");
            return $wStruct;
        }
        return $wStruct;
    }

    public static function getTMProps( $job_data ){

        //TODO this should use the Project DAO instead and use internal cache system to store the record
        try {
            $redisHandler = new Predis\Client( INIT::$REDIS_SERVERS );
            $redisHandler->get(1); //ping established connection
        } catch ( Exception $e ) {
            $redisHandler = null;
            Log::doLog( $e->getMessage() );
            Log::doLog( "No Redis server(s) available." );
        }

        if ( isset( $redisHandler ) && !empty( $redisHandler ) ) {
            $_existingResult = $redisHandler->get( "project_data_for_job_id:" . $job_data['id'] );
            if ( !empty( $_existingResult ) ) {
                return unserialize( $_existingResult );
            }
        }

        $projectData = getProjectJobData( $job_data['id_project'] );

        $result = array(
                'project_id'   => $projectData[ 0 ][ 'pid' ],
                'project_name' => $projectData[ 0 ][ 'pname' ],
                'job_id'       => $job_data[ 'id' ],
        );

        if ( isset( $redisHandler ) && !empty( $redisHandler ) ) {
            $redisHandler->setex(
                    "project_data_for_job_id:" . $job_data['id'],
                    60 * 60 * 24 * 15, /* 15 days of lifetime */
                    serialize( $result )
            );
        }

        return $result;

    }


}

