<?php

use LQA\ChunkReviewDao;
use SubFiltering\Filter;

define( "LTPLACEHOLDER", "##LESSTHAN##" );
define( "GTPLACEHOLDER", "##GREATERTHAN##" );
define( "AMPPLACEHOLDER", "##AMPPLACEHOLDER##" );

class CatUtils {

    const splitPlaceHolder = '##$_SPLIT$##';

    const lfPlaceholderClass   = '_0A';
    const crPlaceholderClass   = '_0D';
    const crlfPlaceholderClass = '_0D0A';
    const lfPlaceholder        = '##$_0A$##';
    const crPlaceholder        = '##$_0D$##';
    const crlfPlaceholder      = '##$_0D0A$##';
    const lfPlaceholderRegex   = '/\#\#\$_0A\$\#\#/g';
    const crPlaceholderRegex   = '/\#\#\$_0D\$\#\#/g';
    const crlfPlaceholderRegex = '/\#\#\$_0D0A\$\#\#/g';

    const tabPlaceholder      = '##$_09$##';
    const tabPlaceholderClass = '_09';
    const tabPlaceholderRegex = '/\#\#\$_09\$\#\#/g';

    const nbspPlaceholder      = '##$_A0$##';
    const nbspPlaceholderClass = '_A0';
    const nbspPlaceholderRegex = '/\#\#\$_A0\$\#\#/g';

    public static $cjk = [ 'zh' => 1.8, 'ja' => 2.5, 'ko' => 2.5, 'km' => 5 ];

    /**
     * @param $langCode
     *
     * @return bool
     */
    public static function isCJK( $langCode ) {
        return array_key_exists( explode( '-', $langCode )[ 0 ], self::$cjk );
    }

    /**
     * @return string[]
     */
    public static function CJKFullwidthPunctuationChars() {
        return [
                '，',
                '。',
                '、',
                '！',
                '？',
                '：',
                '；',
                '「',
                '」',
                '『',
                '』',
                '（',
                '）',
                '—',
                '《',
                '》',
        ];
    }

    public static function placeholdamp( $s ) {
        $s = preg_replace( "/\&/", AMPPLACEHOLDER, $s );

        return $s;
    }

    public static function restoreamp( $s ) {
        $pattern = "#" . AMPPLACEHOLDER . "#";
        $s       = preg_replace( $pattern, self::unicode2chr( "&" ), $s );

        return $s;
    }

    public static function parse_time_to_edit( $ms ) {
        if ( $ms <= 0 ) {
            return [ "00", "00", "00", "00" ];
        }

        $usec = $ms % 1000;

        if ( !is_numeric( $ms ) ) {
            throw new InvalidArgumentException( "Wrong DataType provided: " . var_export( $ms, true ) . "\n Expected integer." );
        }

        $ms = (int)$ms;

        $ms = floor( $ms / 1000 );

        $seconds = str_pad( $ms % 60, 2, "0", STR_PAD_LEFT );
        $ms      = floor( $ms / 60 );

        $minutes = str_pad( $ms % 60, 2, "0", STR_PAD_LEFT );
        $ms      = floor( $ms / 60 );

        $hours = str_pad( $ms % 60, 2, "0", STR_PAD_LEFT );

//        $ms = floor($ms / 60);

        return [ $hours, $minutes, $seconds, $usec ];
    }

    public static function dos2unix( $dosString ) {
        $dosString = str_replace( "\r\n", "\r", $dosString );
        $dosString = str_replace( "\n", "\r", $dosString );
        $dosString = str_replace( "\r", "\n", $dosString );

        return $dosString;
    }

    /**
     * Perform a computation on the string to find the length of the strings separated by the placeholder
     *
     * @param        $segment
     * @param string $separateWithChar
     *
     * @param Filter $Filter
     *
     * @return array
     * @throws Exception
     */
    public static function parseSegmentSplit( $segment, $separateWithChar, Filter $Filter ) {
        $split_chunks    = explode( self::splitPlaceHolder, $segment );
        $chunk_positions = [];

        if ( count( $split_chunks ) > 1 ) {
            $segment           = "";
            $chunk_positions[] = 0;
            foreach ( $split_chunks as $pos => $chunk ) {
                if ( strlen( $chunk ) == 0 ) {
                    break;
                } //remove eventually present null string

                $chunk = $Filter->fromLayer2ToLayer0( $chunk );

                //WARNING We count length in NO MULTIBYTE mode
                $separator_len = strlen( $separateWithChar );
                $separator     = $separateWithChar;

                //if the last char of the last chunk AND the first of the next are spaces, don't add another one
                if ( substr( $chunk, -1 ) == $separateWithChar || @substr( $split_chunks[ $pos + 1 ], 0, 1 ) == $separateWithChar ) {
                    $separator_len = 0;
                    $separator     = '';
                }

                $chunk_positions[] = strlen( $chunk ) + $separator_len;
                $segment           .= $chunk . $separator;

            }
        }

        return [ $segment, $chunk_positions ];
    }

    /**
     * Create a string with placeholders in the right position based on the struct
     *
     * @param       $segment
     * @param array $chunk_positions
     *
     * @return string
     */
    public static function reApplySegmentSplit( $segment, array $chunk_positions ) {

        $string_chunks = [];
        $last_sum      = 0;
        foreach ( $chunk_positions as $pos => $value ) {
            if ( isset( $chunk_positions[ $pos + 1 ] ) ) {
                $string_chunks[] = substr( $segment, $chunk_positions[ $pos ] + $last_sum, $chunk_positions[ $pos + 1 ] );
                $last_sum        += $chunk_positions[ $pos ];
            }

        }

        if ( empty( $string_chunks ) ) {
            return $segment;
        } else {
            return implode( self::splitPlaceHolder, $string_chunks );
        }

    }

    /**
     * @param Translations_SegmentTranslationStruct $translation
     * @param                                       $is_revision
     * @param array                                 $errors
     *
     * @return array
     */
    public static function addSegmentTranslation( Translations_SegmentTranslationStruct $translation, $is_revision, array &$errors ) {

        try {
            //if needed here can be placed a check for affected_rows == 0 //( the return value of addTranslation )
            Translations_SegmentTranslationDao::addTranslation( $translation, $is_revision );
        } catch ( Exception $e ) {
            $errors[] = [ "code" => -101, "message" => $e->getMessage() ];
        }

        return $errors;

    }

    /**
     * Make an estimation on performance
     *
     * @param array $job_stats
     *
     * @return array
     * @throws Exception
     */
    protected static function _performanceEstimationTime( array $job_stats ) {

        $last_10_worked_ids = Translations_SegmentTranslationDao::getLast10TranslatedSegmentIDs( $job_stats[ 'id' ] );
        if ( !empty( $last_10_worked_ids ) and count($last_10_worked_ids) === 10 ) {

            //perform check on performance if single segment are set to check or globally Forced
            // Calculating words per hour and estimated completion
            $estimation_temp = Translations_SegmentTranslationDao::getEQWLastHour( $job_stats[ 'id' ], $last_10_worked_ids );

            $job_stats[ 'WORDS_PER_HOUR' ] = number_format( $estimation_temp[ 0 ][ 'words_per_hour' ], 0, '.', ',' );
            // 7.2 hours
            // $job_stats['ESTIMATED_COMPLETION'] = number_format( ($job_stats['DRAFT']+$job_stats['REJECTED'])/$estimation_temp[0]['words_per_hour'],1);
            // 1 h 32 m
            // $job_stats['ESTIMATED_COMPLETION'] = date("G",($job_stats['DRAFT']+$job_stats['REJECTED'])/$estimation_temp[0]['words_per_hour']*3600) . "h " . date("i",($job_stats['DRAFT']+$job_stats['REJECTED'])/$estimation_temp[0]['words_per_hour']*3600) . "m";
            $job_stats[ 'ESTIMATED_COMPLETION' ] = date( "z\d G\h i\m", ( $job_stats[ 'DRAFT' ] + $job_stats[ 'REJECTED' ] ) * 3600 / ( !empty( $estimation_temp[ 0 ][ 'words_per_hour' ] ) ? $estimation_temp[ 0 ][ 'words_per_hour' ] : 1 ) - 3600 );
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
     *
     * @return mixed $job_stats
     * @deprecated Formatting strings server-side for javascript rendered pages is deprecated.
     *
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

        if ( $job_stats[ 'TRANSLATED_PERC' ] > 100 ) {
            $job_stats[ 'TRANSLATED_PERC' ] = 100;
        }

        if ( $job_stats[ 'PROGRESS_PERC' ] > 100 ) {
            $job_stats[ 'PROGRESS_PERC' ] = 100;
        }

        if ( $job_stats[ 'DRAFT_PERC' ] < 0 ) {
            $job_stats[ 'DRAFT_PERC' ] = 0;
        }

        $temp = [
                $job_stats[ 'TRANSLATED_PERC' ],
                $job_stats[ 'DRAFT_PERC' ],
                $job_stats[ 'REJECTED_PERC' ],
                $job_stats[ 'PROGRESS_PERC' ],
        ];
        $max  = max( $temp );
        $min  = min( $temp );
        if ( $max < 99 || $min > 1 ) {
            $significantDigits = 0;
        } else {
            $significantDigits = 2;
        }

        $job_stats[ 'TRANSLATED_PERC_FORMATTED' ] = round( $job_stats[ 'TRANSLATED_PERC' ], $significantDigits );
        $job_stats[ 'DRAFT_PERC_FORMATTED' ]      = round( $job_stats[ 'DRAFT_PERC' ], $significantDigits );
        $job_stats[ 'APPROVED_PERC_FORMATTED' ]   = round( $job_stats[ 'APPROVED_PERC' ], $significantDigits );
        $job_stats[ 'REJECTED_PERC_FORMATTED' ]   = round( $job_stats[ 'REJECTED_PERC' ], $significantDigits );
        $job_stats[ 'PROGRESS_PERC_FORMATTED' ]   = round( $job_stats[ 'PROGRESS_PERC' ], $significantDigits );

        $todo = $job_stats[ 'DRAFT' ] + $job_stats[ 'REJECTED' ];
        if ( $todo < 1 && $todo > 0 ) {
            $job_stats[ 'TODO_FORMATTED' ] = 1;
            $job_stats[ 'TODO' ]           = 1;
        } else {
            $job_stats[ 'TODO_FORMATTED' ] = number_format( $job_stats[ 'DRAFT' ] + $job_stats[ 'REJECTED' ], 0, ".", "," );
            $job_stats[ 'TODO' ]           = (float)number_format( $job_stats[ 'DRAFT' ] + $job_stats[ 'REJECTED' ], 0, ".", "" );
        }

        $t = 'approved';
        if ( $job_stats[ 'TRANSLATED_FORMATTED' ] > 0 ) {
            $t = "translated";
        }
        if ( $job_stats[ 'DRAFT_FORMATTED' ] > 0 ) {
            $t = "draft";
        }
        if ( $job_stats[ 'REJECTED_FORMATTED' ] > 0 ) {
            $t = "draft";
        }
        if ( $job_stats[ 'TRANSLATED_FORMATTED' ] == 0 &&
                $job_stats[ 'DRAFT_FORMATTED' ] == 0 &&
                $job_stats[ 'REJECTED_FORMATTED' ] == 0 &&
                $job_stats[ 'APPROVED_FORMATTED' ] == 0 ) {
            $t = 'draft';
        }
        $job_stats[ 'DOWNLOAD_STATUS' ] = $t;

        return $job_stats;
    }

    /**
     * @param WordCount_Struct $wCount
     *
     * @param bool             $performanceEstimation
     *
     * @return array
     * @deprecated because if the use of pre-formatted values
     */
    public static function getFastStatsForJob( WordCount_Struct $wCount, $performanceEstimation = true ) {
        $job_stats = self::getPlainStatsForJobs( $wCount );
        $job_stats = self::_getStatsForJob( $job_stats ); //true set estimation check if present

        if ( !$performanceEstimation ) {
            return $job_stats;
        }

        return self::_performanceEstimationTime( $job_stats );
    }

    /**
     * Remove Tags and treat numbers as one word
     *
     * @param                 $string
     * @param string          $source_lang
     *
     * @param Filter|null     $Filter
     *
     * @return mixed|string
     * @throws \Exception
     */
    public static function clean_raw_string_4_word_count( $string, $source_lang = 'en-US', Filter $Filter = null ) {

        if ( $Filter === null ) {
            $Filter = SubFiltering\Filter::getInstance();
        }

        $string = $Filter->fromLayer0ToLayer1( $string );

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

        if ( preg_match_all( '#<[/]{0,1}(?![0-9]+)[a-z0-9\-\._]+?(?:\s[:_a-z]+=.+?)?\s*[\/]{0,1}>#i', $string, $matches, PREG_SET_ORDER ) ) {

            foreach ( $matches as $tag ) {
                if ( is_numeric( substr( $tag[ 0 ], -2, 1 ) ) && !preg_match( '#<[/]{0,1}[h][1-6][^>]*>#i', $tag[ 0 ] ) ) { //H tag are an exception
                    //tag can not end with a number
                    continue;
                }
                $string = str_replace( $tag[ 0 ], " ", $string );
            }

        }

        //remove ampersands and entities. Converters returns entities in xml, we want raw strings.
        //take a look at this string:
        // This is a string &amp;nbsp;
        $string = html_entity_decode(
                html_entity_decode( $string, ENT_HTML401 | ENT_QUOTES, 'UTF-8' )
        );

        /**
         * Count links as 1 word
         *
         * heuristic, of course this regexp is not perfect, hoping it is not too greedy
         *
         */
        $linkRegexp = '/(?:(?:[a-z]+:\/\/)|(?:\/\/))?(?:[\p{Latin}\d-_]+)?(?:[\p{Latin}\d-_]+\.[\p{Latin}\d-_]+\.[\p{Latin}\d#\?=\.-_]+)/u';


        /**
         * Count numbers as One Word
         */
        if ( array_key_exists( $source_lang, self::$cjk ) ) {

            $string = preg_replace( $linkRegexp, 'L', $string );

            // replace all numbers with a placeholder without spaces to no alter the ratio characters/words, so they will be counted as 1 word
            $string = preg_replace( '/\b[0-9]+(?:[\.,][0-9]+)*\b/', 'N', $string );

        } else {

            $string = preg_replace( $linkRegexp, ' L ', $string );

            //Refine links like "php://filter/read=string.strip_tags/resource=php://input" not available in CJK because we can't use \s identifier
            $string = preg_replace( '/(?:(?:[a-z]+:\/\/)[^\s]+)/u', ' L ', $string );

            // replace all numbers with a placeholder so they will be counted as 1 word
            $string = preg_replace( '/\b[0-9]+(?:[\.,][0-9]+)*\b/', ' N ', $string );

        }


        return $string;

    }

    /**
     * Count words in a string
     *
     * @param                 $string
     * @param string          $source_lang
     *
     * @param Filter|null     $filter
     *
     * @return float|int
     * @throws Exception
     */
    public static function segment_raw_word_count( $string, $source_lang = 'en-US', Filter $filter = null ) {

        //first two letter of code lang
        $source_lang_two_letter = explode( "-", $source_lang )[ 0 ];

        $string = self::clean_raw_string_4_word_count( $string, $source_lang, $filter );

        /**
         * Escape dash and underscore and replace them with Macro and Cedilla characters!
         *
         * Dash and underscore must not be treated as separated words
         * Macro and Cedilla characters are not replaced by unicode regular expressions below
         */
        $string = str_replace( [ '-', '_' ], [ "¯", '¸' ], $string );

        /**
         * Remove Unicode:
         * @see http://php.net/manual/en/regexp.reference.unicode.php
         * P -> Punctuation
         * Z -> Separator ( but not spaces )
         * C -> Other
         */
        $string = preg_replace( '#[\p{P}\p{Zl}\p{Zp}\p{C}]+#u', " ", $string );

        /**
         * Remove english possessive word count
         */
        if( $source_lang_two_letter == "en" ){
            $string = str_replace( ' s ', ' ', $string );
        }

        /**
         * Now reset chars
         */
        $string = str_replace( [ "¯", '¸' ], [ '-', '_' ], $string );


        //check for a string made of spaces only, after the string was cleaned
        $string_with_no_spaces = preg_replace( '#[\p{P}\p{Z}\p{C}]+#u', "", $string );
        if ( $string_with_no_spaces == "" ) {
            return 0;
        }


        if ( array_key_exists( $source_lang_two_letter, self::$cjk ) ) {
            $res = mb_strlen( $string_with_no_spaces, 'UTF-8' );
        } else {

            $words_array = preg_split( '/[\s]+/u', $string );
            $words_array = array_filter( $words_array, function ( $word ) {
                return trim( $word ) != "";
            } );

            $res = @count( $words_array );

        }

        return $res;

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
     * @return array( $charset, $converted )
     */
    public static function convertEncoding( $toEncoding, &$documentContent ) {

        //Example: The file is UTF-16 Encoded

        $tmpOrigFName = tempnam( "/tmp", mt_rand( 0, 1000000000 ) . uniqid( "", true ) );
        file_put_contents( $tmpOrigFName, $documentContent );

        $cmd = "file -i $tmpOrigFName";
        Log::doJsonLog( $cmd );

        $file_info = shell_exec( $cmd );
        list( , $charset ) = explode( "=", $file_info );
        $charset = trim( $charset );

        if ( $charset == 'utf-16le' ) {
            $charset = 'Unicode';
        }

        //do nothing if "from" and "to" parameters are the equals
        if ( strtolower( $charset ) == strtolower( $toEncoding ) ) {
            return [ $charset, $documentContent ];
        }

        $converted = iconv( $charset, $toEncoding . "//IGNORE", $documentContent );

        return [ $charset, $converted ];

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
    public static function fastUnicode2ord( $mb_char ) {
        switch ( strlen( $mb_char ) ) {
            case 1:
                return ord( $mb_char );
                break;
            case 2:
                return ( ord( $mb_char[ 0 ] ) - 0xC0 ) * 0x40 +
                        ord( $mb_char[ 1 ] ) - 0x80;
                break;
            case 3:
                return ( ord( $mb_char[ 0 ] ) - 0xE0 ) * 0x1000 +
                        ( ord( $mb_char[ 1 ] ) - 0x80 ) * 0x40 +
                        ord( $mb_char[ 2 ] ) - 0x80;
                break;
            case 4:
                return ( ord( $mb_char[ 0 ] ) - 0xF0 ) * 0x40000 +
                        ( ord( $mb_char[ 1 ] ) - 0x80 ) * 0x1000 +
                        ( ord( $mb_char[ 2 ] ) - 0x80 ) * 0x40 +
                        ord( $mb_char[ 3 ] ) - 0x80;
                break;
        }

        return 20; //as default return a space ( should never happen )

    }

    public static function htmlentitiesFromUnicode( $str ) {
        return "&#" . self::fastUnicode2ord( $str[ 1 ] ) . ";";
    }

    // multibyte string manipulation functions
    // source : http://stackoverflow.com/questions/9361303/can-i-get-the-unicode-value-of-a-character-or-vise-versa-with-php
    // original source : PHPExcel libary (http://phpexcel.codeplex.com/)
    // get the char from unicode code
    public static function unicode2chr( $o ) {
        if ( function_exists( 'mb_convert_encoding' ) ) {
            return mb_convert_encoding( '&#' . intval( $o ) . ';', 'UTF-8', 'HTML-ENTITIES' );
        }

        return chr( intval( $o ) );
    }

    /**
     * This function converts Unicode entites with no corresponding HTML entity
     * to their original value
     *
     * @param $str
     *
     * @return string|string[]
     */
    public static function restoreUnicodeEntitesToOriginalValues( $str ) {

        $entities = [
                "157" // https://www.codetable.net/decimal/157
        ];

        foreach ( $entities as $entity ) {
            $value = self::unicode2chr( $entity );
            $str   = str_replace( "&#" . $entity . ";", $value, $str );
        }

        return $str;
    }

    /**
     * This function trims, strips tags from a html entity decoded string
     *
     * @param string $str
     *
     * @return string
     */
    public static function trimAndStripFromAnHtmlEntityDecoded( $str ) {
        return trim( strip_tags( html_entity_decode( $str, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
    }

    /**
     * @param Jobs_JobStruct         $job
     *
     * @param Projects_ProjectStruct $projectStruct
     *
     * @return WordCount_Struct
     * @throws Exception
     */
    public static function getWStructFromJobArray( Jobs_JobStruct $job, Projects_ProjectStruct $projectStruct ) {

        $wStruct = new WordCount_Struct();

        $wStruct->setIdJob( $job[ 'id' ] );
        $wStruct->setJobPassword( $job[ 'password' ] );
        $wStruct->setNewWords( $job[ 'new_words' ] );
        $wStruct->setDraftWords( $job[ 'draft_words' ] );
        $wStruct->setTranslatedWords( $job[ 'translated_words' ] );
        $wStruct->setApprovedWords( $job[ 'approved_words' ] );
        $wStruct->setRejectedWords( $job[ 'rejected_words' ] );

        // For projects created with No tm analysis enabled
        if ( $wStruct->getTotal() == 0 && ( $projectStruct[ 'status_analysis' ] == Constants_ProjectStatus::STATUS_DONE || $projectStruct[ 'status_analysis' ] == Constants_ProjectStatus::STATUS_NOT_TO_ANALYZE ) ) {
            $wCounter = new WordCount_CounterModel();
            $wStruct  = $wCounter->initializeJobWordCount( $job[ 'id' ], $job[ 'password' ] );
            Log::doJsonLog( "BackWard compatibility set Counter." );

            return $wStruct;
        }

        return $wStruct;
    }

    /**
     * Returns the string representing the overall quality for a job,
     * taking into account both old revision and new revision.
     *
     * @param Jobs_JobStruct         $job
     *
     * @param Projects_ProjectStruct $project
     * @param FeatureSet             $featureSet
     *
     * @return string
     * @throws Exception
     */
    public static function getQualityOverallFromJobStruct( Jobs_JobStruct $job, Projects_ProjectStruct $project, FeatureSet $featureSet ) {
        $values = self::getQualityInfoOrChunkReviewStructFromJobStruct( $job, $featureSet );
        $result = null;

        if ( $featureSet->hasRevisionFeature() ) {

            if ( @$values->is_pass == null ) {
                $result = $values->is_pass;
            } elseif ( !empty( $values->is_pass ) ) {
                $result = 'excellent';
            } else {
                $result = 'fail';
            }

        } else {
            $result = strtolower( $values[ 'minText' ] );
        }

        return $result;
    }

    /**
     * @param Jobs_JobStruct $job
     *
     * @param FeatureSet     $featureSet
     *
     * @return array|\LQA\ChunkReviewStruct|null
     * @throws ReflectionException
     * @internal   param Projects_ProjectStruct $project
     * @deprecated this method should only return values for legacy revision, it should not return ChunkReviewStruct nor
     *             it should make use of $featureSet to determine the revision type, use `getQualityOverallFromJobStruct`.
     */
    public static function getQualityInfoOrChunkReviewStructFromJobStruct( Jobs_JobStruct $job, FeatureSet $featureSet ) {

        $result = null;
        if ( $featureSet->hasRevisionFeature() ) {
            $result = ( new ChunkReviewDao() )->findChunkReviews( new Chunks_ChunkStruct( $job->toArray() ) )[ 0 ];
        } else {
            $result = self::getQualityInfoFromJobStruct( $job, $featureSet );
        }

        return $result;
    }

    /**
     * @param Jobs_JobStruct $job
     * @param FeatureSet     $featureSet
     *
     * @return array
     */
    public static function getQualityInfoFromJobStruct( Jobs_JobStruct $job, FeatureSet $featureSet ) {
        $struct      = CatUtils::getWStructFromJobStruct( $job, $job->getProject()->status_analysis );
        $reviseClass = new Constants_Revise;

        $jobQA = new Revise_JobQA(
                $job->id,
                $job->password, $struct->getTotal(),
                $reviseClass
        );

        /**
         * @var $jobQA Revise_JobQA
         */
        list( $jobQA, ) = $featureSet->filter( "overrideReviseJobQA", [ $jobQA, $reviseClass ], $job->id, $job->password, $struct->getTotal() );
        $jobQA->retrieveJobErrorTotals();

        return $jobQA->evalJobVote();
    }


    /**
     * @param Jobs_JobStruct $job
     * @param                $analysis_status
     *
     * @return WordCount_Struct
     */
    public static function getWStructFromJobStruct( Jobs_JobStruct $job, $analysis_status ) {

        $wStruct = new WordCount_Struct();

        $wStruct->setIdJob( $job->id );
        $wStruct->setJobPassword( $job->password );
        $wStruct->setNewWords( $job->new_words );
        $wStruct->setDraftWords( $job->draft_words );
        $wStruct->setTranslatedWords( $job->translated_words );
        $wStruct->setApprovedWords( $job->approved_words );
        $wStruct->setRejectedWords( $job->rejected_words );

        // For projects created with No tm analysis enabled
        if ( $wStruct->getTotal() == 0 && ( $analysis_status == Constants_ProjectStatus::STATUS_DONE || $analysis_status == Constants_ProjectStatus::STATUS_NOT_TO_ANALYZE ) ) {
            $wCounter = new WordCount_CounterModel();
            $wStruct  = $wCounter->initializeJobWordCount( $job->id, $job->password );
            Log::doJsonLog( "BackWard compatibility set Counter." );

            return $wStruct;
        }

        return $wStruct;
    }

    public static function getSerializedCategories( $reviseClass ) {
        $categoriesDbNames = Constants_Revise::$categoriesDbNames;
        $categories        = [];
        foreach ( $categoriesDbNames as $categoryDbName ) {

            $categories[] = [
                    'label'         => constant( get_class( $reviseClass ) . "::" . strtoupper( $categoryDbName ) ),
                    'id'            => $categoryDbName,
                    'severities'    => [
                            [ 'label' => Constants_Revise::MINOR, 'penalty' => Constants_Revise::$const2ServerValues[ Constants_Revise::MINOR ] ],
                            [ 'label' => Constants_Revise::MAJOR, 'penalty' => Constants_Revise::$const2ServerValues[ Constants_Revise::MAJOR ] ]
                    ],
                    'subcategories' => [],
                    'options'       => [],
            ];
        }

        return $categories;
    }

    /**
     * @param WordCount_Struct $wCount
     *
     * @return array
     */
    public static function getPlainStatsForJobs( WordCount_Struct $wCount ) {
        $job_stats                 = [];
        $job_stats[ 'id' ]         = $wCount->getIdJob();
        $job_stats[ 'DRAFT' ]      = $wCount->getNewWords() + $wCount->getDraftWords();
        $job_stats[ 'TRANSLATED' ] = $wCount->getTranslatedWords();
        $job_stats[ 'APPROVED' ]   = $wCount->getApprovedWords();
        $job_stats[ 'REJECTED' ]   = $wCount->getRejectedWords();

        //sometimes new_words + draft_words < 0 (why?). If it happens, set draft words to 0
        if ( $job_stats[ 'DRAFT' ] < 0 ) {
            $job_stats[ 'DRAFT' ] = 0;
        }

        //avoid division by zero warning
        $total                = $wCount->getTotal();
        $job_stats[ 'TOTAL' ] = ( $total == 0 ? 1 : $total );

        return $job_stats;
    }

    /**
     * @param        $sid
     * @param        $results array The resultset from previous getNextSegment()
     * @param string $status
     *
     * @return null
     */

    public static function fetchStatus( $sid, $results, $status = Constants_TranslationStatus::STATUS_NEW ) {

        $statusWeight = [
                Constants_TranslationStatus::STATUS_NEW        => 10,
                Constants_TranslationStatus::STATUS_DRAFT      => 10,
                Constants_TranslationStatus::STATUS_REJECTED   => 10,
                Constants_TranslationStatus::STATUS_TRANSLATED => 40,
                Constants_TranslationStatus::STATUS_APPROVED   => 50
        ];

        $nSegment = null;
        if ( isset( $results[ 0 ][ 'id' ] ) ) {
            //if there are results check for next id,
            //otherwise get the first one in the list
//        $nSegment = $results[ 0 ][ 'id' ];
            //Check if there is translated segment with $seg[ 'id' ] > $sid
            foreach ( $results as $seg ) {
                if ( $seg[ 'status' ] == null ) {
                    $seg[ 'status' ] = Constants_TranslationStatus::STATUS_NEW;
                }
                if ( $seg[ 'id' ] > $sid && $statusWeight[ $seg[ 'status' ] ] == $statusWeight[ $status ] ) {
                    $nSegment = $seg[ 'id' ];
                    break;
                }
            }
            // If there aren't transleted segments in the next elements -> check starting from the first
            if ( !$nSegment ) {
                foreach ( $results as $seg ) {
                    if ( $seg[ 'status' ] == null ) {
                        $seg[ 'status' ] = Constants_TranslationStatus::STATUS_NEW;
                    }
                    if ( $statusWeight[ $seg[ 'status' ] ] == $statusWeight[ $status ] ) {
                        $nSegment = $seg[ 'id' ];
                        break;
                    }
                }
            }

        }

        return $nSegment;

    }

}

