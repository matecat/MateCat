<?php

use Exceptions\ControllerReturnException;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use Matecat\SubFiltering\Enum\CTypeEnum;
use Matecat\SubFiltering\MateCatFilter;
use Validator\IsJobRevisionValidator;
use Validator\IsJobRevisionValidatorObject;
use WordCount\CounterModel;
use WordCount\WordCountStruct;

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
    const crlfPlaceholderRegex = '/#\#\$_0D\$#\#\#\#\$_0A\$#\#/g';

    const tabPlaceholder      = '##$_09$##';
    const tabPlaceholderClass = '_09';
    const tabPlaceholderRegex = '/\#\#\$_09\$\#\#/g';

    const nbspPlaceholder      = '##$_A0$##';
    const nbspPlaceholderClass = '_A0';
    const nbspPlaceholderRegex = '/\#\#\$_A0\$\#\#/g';

    // CJK and CJ languages
    public static array $cjk = [ 'zh' => 1.8, 'ja' => 2.5, 'ko' => 2.5, 'km' => 5 ];
    public static array $cj  = [ 'zh' => 1.8, 'ja' => 2.5 ];

    /**
     * @param $langCode
     *
     * @return bool
     */
    public static function isCJK( $langCode ): bool {
        return array_key_exists( explode( '-', $langCode )[ 0 ], self::$cjk );
    }

    /**
     * @param $langCode
     *
     * @return bool
     */
    public static function isCJ( $langCode ): bool {
        return array_key_exists( explode( '-', $langCode )[ 0 ], self::$cj );
    }

    /**
     * @return string[]
     */
    public static function CJKFullwidthPunctuationChars(): array {
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

    /**
     * @param int $ms
     *
     * @return array|string[]
     */
    public static function parse_time_to_edit( int $ms ): array {

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

        return [ $hours, $minutes, $seconds, $usec ];
    }

    public static function dos2unix( string $dosString ): string {
        $dosString = str_replace( "\r\n", "\r", $dosString );
        $dosString = str_replace( "\n", "\r", $dosString );

        return str_replace( "\r", "\n", $dosString );
    }

    /**
     * Perform a computation on the string to find the length of the strings separated by the placeholder
     *
     * @param string        $segment
     * @param string        $separateWithChar
     *
     * @param MateCatFilter $Filter
     *
     * @return array
     * @throws Exception
     */
    public static function parseSegmentSplit( string $segment, string $separateWithChar, MateCatFilter $Filter ): array {
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
     * @param string|null $segment
     * @param array|null  $chunk_positions
     *
     * @return ?string
     */
    public static function reApplySegmentSplit( ?string $segment, ?array $chunk_positions = [] ): ?string {

        $string_chunks = [];
        $last_sum      = 0;
        foreach ( $chunk_positions as $pos => $value ) {
            if ( isset( $chunk_positions[ $pos + 1 ] ) ) {
                $string_chunks[] = substr( $segment, $value + $last_sum, $chunk_positions[ $pos + 1 ] );
                $last_sum        += $value;
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
     * @param bool                                  $is_revision
     *
     * @return void
     * @throws ControllerReturnException
     */
    public static function addSegmentTranslation( Translations_SegmentTranslationStruct $translation, bool $is_revision ) {

        try {
            Translations_SegmentTranslationDao::addTranslation( $translation, $is_revision );
        } catch ( Exception $e ) {
            throw  new ControllerReturnException( $e->getMessage(), $e->getCode(), $e );
        }

    }

    /**
     * Make an estimation on performance
     *
     * @param array $job_stats
     * @param int   $id_job
     *
     * @return array
     */
    protected static function _performanceEstimationTime( array $job_stats, int $id_job ): array {

        $last_10_worked_ids = Translations_SegmentTranslationDao::getLast10TranslatedSegmentIDsInLastHour( $id_job );
        if ( !empty( $last_10_worked_ids ) and count( $last_10_worked_ids ) === 10 ) {

            // Calculating words per hour and estimated completion
            $estimation_temp  = Translations_SegmentTranslationDao::getWordsPerSecond( $id_job, $last_10_worked_ids );
            $words_per_second = ( !empty( $estimation_temp[ 0 ][ 'words_per_second' ] ) ? $estimation_temp[ 0 ][ 'words_per_second' ] : 1 ); // avoid division by zero

            $totalWordsToDo = $job_stats[ 'raw' ][ 'new' ] + $job_stats[ 'raw' ][ 'draft' ] + ( $job_stats[ 'raw' ][ 'rejected' ] ?? 0 );

            $totalTimeSeconds = $totalWordsToDo / $words_per_second;

            // Convert the total time into days, hours, minutes, and seconds
            $days    = floor( $totalTimeSeconds / 86400 );
            $hours   = floor( ( $totalTimeSeconds % 86400 ) / 3600 );
            $minutes = floor( ( $totalTimeSeconds % 3600 ) / 60 );

            // Format the time in 'Dd Hh Mm Ss' format
            $job_stats[ 'estimated_completion' ] = sprintf( '%dd %dh %02dm', $days, $hours, $minutes );
            $job_stats[ 'words_per_hour' ]       = round( $words_per_second * 3600 );
        }

        return $job_stats;
    }

    /**
     *
     * This function exposes stats supporting new and old version counter
     *
     * @param WordCountStruct $wCount
     * @param bool            $performanceEstimation
     *
     * @return array
     */
    public static function getFastStatsForJob( WordCountStruct $wCount, bool $performanceEstimation = true ): array {

        $job_stats = $wCount->jsonSerialize();
        if ( !$performanceEstimation ) {
            return $job_stats;
        }

        return self::_performanceEstimationTime( $job_stats, $wCount->getIdJob() );

    }

    /**
     * Remove Tags and treat numbers as one word
     *
     * @param string             $string
     * @param string             $source_lang
     * @param MateCatFilter|null $Filter
     *
     * @return string
     * @throws Exception
     */
    public static function clean_raw_string_4_word_count( string $string, string $source_lang = 'en-US', MateCatFilter $Filter = null ): string {

        if ( $Filter === null ) {
            $Filter = MateCatFilter::getInstance( new FeatureSet(), $source_lang );
        }

        $string = $Filter->fromLayer0ToLayer1( $string );
        $string = self::replacePlaceholders( $string );

        //return empty on string composed only by spaces
        //do nothing
        if ( preg_replace( '#\p{Z}+#u', '', $string ) == '' ) {
            return '';
        }

        if ( strpos( $source_lang, '-' ) !== false ) {
            $tmp_lang    = explode( '-', $source_lang );
            $source_lang = $tmp_lang[ 0 ];
            unset( $tmp_lang );
        }

        if ( preg_match_all( '#</?(?![0-9]+)[a-z0-9\-._]+?(?:\s[:_a-z]+=.+?)?\s*/?>#i', $string, $matches, PREG_SET_ORDER ) ) {

            foreach ( $matches as $tag ) {
                if ( is_numeric( substr( $tag[ 0 ], -2, 1 ) ) && !preg_match( '#</?h[1-6][^>]*>#i', $tag[ 0 ] ) ) { //H tag are an exception
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
        $linkRegexp = '/(?:(?:[a-z]+:\/\/)|(?:\/\/))?(?:[\p{Latin}\d\-_]+)?(?:[\p{Latin}\d\-_]+\.[\p{Latin}\d\-_]+\.[\p{Latin}\d#\?=\.\-_]+)/u';


        /**
         * Count numbers as One Word
         */
        if ( array_key_exists( $source_lang, self::$cjk ) ) {

            $string = preg_replace( $linkRegexp, 'L', $string );

            // replace all numbers with a placeholder without spaces to no alter the ratio characters/words, so they will be counted as 1 word
            $string = preg_replace( '/\b[0-9]+(?:[.,][0-9]+)*\b/', 'N', $string );

        } else {

            $string = preg_replace( $linkRegexp, ' L ', $string );

            //Refine links like "php://filter/read=string.strip_tags/resource=php://input" not available in CJK because we can't use \s identifier
            $string = preg_replace( '#[a-z]+://\S+#u', ' L ', $string );

            // replace all numbers with a placeholder so they will be counted as 1 word
            $string = preg_replace( '/\b[0-9]+(?:[.,][0-9]+)*\b/', ' N ', $string );

        }

        return $string;
    }

    /**
     * @param $string
     *
     * @return string
     */
    private static function replacePlaceholders( $string ): string {
        $pattern = '|<ph id ?= ?["\'](mtc_[0-9]+)["\'] ?(ctype=["\'].+?["\'] ?) ?(equiv-text=["\'].+?["\'] ?)/>|ui';

        preg_match_all( $pattern, $string, $matches, PREG_SET_ORDER );

        foreach ( $matches as $match ) {
            $ctype = trim( $match[ 2 ] );
            $ctype = str_replace( '"', '', $ctype );
            $ctype = str_replace( 'ctype=', '', $ctype );

            if ( $ctype !== CTypeEnum::HTML ) {
                $string = str_replace( $match[ 0 ], 'P', $string );
            } else {
                $string = str_replace( $match[ 0 ], '', $string );
            }
        }

        return $string;
    }

    /**
     * Count words in a string
     *
     * @param string|null        $string $string
     * @param string             $source_lang
     * @param MateCatFilter|null $filter
     *
     * @return float|int
     * @throws Exception
     */
    public static function segment_raw_word_count( ?string $string = null, string $source_lang = 'en-US', MateCatFilter $filter = null ): int {

        if ( empty( $string ) && strlen( trim( $string ) ) === 0 ) {
            return 0;
        }

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
        if ( $source_lang_two_letter == "en" ) {
            $string = str_replace( ' s ', ' ', $string );
        }

        /**
         * Now reset chars
         */
        $string = str_replace( [ "¯", '¸' ], [ '-', '_' ], $string );


        //check for a string made of spaces only, after the string was cleaned
        $string_with_no_spaces = preg_replace( '#[\p{Z}\p{C}]+#u', "", $string );
        if ( $string_with_no_spaces == "" ) {
            return 0;
        }

        if ( array_key_exists( $source_lang_two_letter, self::$cjk ) ) {
            $res = mb_strlen( $string_with_no_spaces, 'UTF-8' );
        } else {

            $words_array = preg_split( '/\s+/u', $string );
            $words_array = array_filter( $words_array, function ( $word ) {
                return trim( $word ) != "";
            } );

            $res = count( $words_array );

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
     * @param string $toEncoding
     * @param string $documentContent Reference to the string document
     *
     * @return array( $charset, $converted )
     */
    public static function convertEncoding( string $toEncoding, string $documentContent ): array {

        //Example: The file is UTF-16 Encoded

        $tmpOrigFName = tempnam( "/tmp", mt_rand( 0, 1000000000 ) . uniqid( "", true ) );
        file_put_contents( $tmpOrigFName, $documentContent );

        $cmd = "file -i $tmpOrigFName";
        Log::doJsonLog( $cmd );

        $file_info = shell_exec( $cmd );
        [ , $charset ] = explode( "=", $file_info );
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
    public static function fastUnicode2ord( string $mb_char ): int {
        switch ( strlen( $mb_char ) ) {
            case 1:
                return ord( $mb_char );
            case 2:
                return ( ord( $mb_char[ 0 ] ) - 0xC0 ) * 0x40 +
                        ord( $mb_char[ 1 ] ) - 0x80;
            case 3:
                return ( ord( $mb_char[ 0 ] ) - 0xE0 ) * 0x1000 +
                        ( ord( $mb_char[ 1 ] ) - 0x80 ) * 0x40 +
                        ord( $mb_char[ 2 ] ) - 0x80;
            case 4:
                return ( ord( $mb_char[ 0 ] ) - 0xF0 ) * 0x40000 +
                        ( ord( $mb_char[ 1 ] ) - 0x80 ) * 0x1000 +
                        ( ord( $mb_char[ 2 ] ) - 0x80 ) * 0x40 +
                        ord( $mb_char[ 3 ] ) - 0x80;
        }

        return 20; //as default return a space ( should never happen )

    }

    public static function htmlentitiesFromUnicode( $str ): string {
        return "&#" . self::fastUnicode2ord( $str[ 1 ] ) . ";";
    }

    // multibyte string manipulation functions
    // source : http://stackoverflow.com/questions/9361303/can-i-get-the-unicode-value-of-a-character-or-vise-versa-with-php
    // original source : PHPExcel libary (http://phpexcel.codeplex.com/)
    // get the char from unicode code
    public static function unicode2chr( int $o ): string {
        if ( function_exists( 'mb_convert_encoding' ) ) {
            return mb_convert_encoding( '&#' . $o . ';', 'UTF-8', 'HTML-ENTITIES' );
        }

        return chr( $o );
    }

    /**
     * This function converts Unicode entites with no corresponding HTML entity
     * to their original value
     *
     * @param string $str
     *
     * @return string|string[]
     */
    public static function restoreUnicodeEntitiesToOriginalValues( string $str ): string {

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
    public static function trimAndStripFromAnHtmlEntityDecoded( string $str ): string {
        return trim( strip_tags( html_entity_decode( $str, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
    }

    /**
     * @param Jobs_JobStruct         $job
     *
     * @param Projects_ProjectStruct $projectStruct
     *
     * @return WordCountStruct
     * @throws Exception
     */
    public static function getWStructFromJobArray( Jobs_JobStruct $job, Projects_ProjectStruct $projectStruct ): WordCountStruct {

        $wStruct = WordCountStruct::loadFromJob( $job );

        // For projects created with No tm analysis enabled
        if ( $wStruct->getTotal() == 0 && ( $projectStruct[ 'status_analysis' ] == Constants_ProjectStatus::STATUS_DONE || $projectStruct[ 'status_analysis' ] == Constants_ProjectStatus::STATUS_NOT_TO_ANALYZE ) ) {
            $wCounter = new CounterModel();
            $wStruct  = $wCounter->initializeJobWordCount( $job[ 'id' ], $job[ 'password' ] );
            Log::doJsonLog( "BackWard compatibility set Counter." );

            return $wStruct;
        }

        return $wStruct;
    }

    /**
     * Returns the string representing the overall quality for a job,
     *
     * @param Jobs_JobStruct $job
     *
     * @param array          $chunkReviews
     *
     * @return string
     * @throws ReflectionException
     */
    public static function getQualityOverallFromJobStruct( Jobs_JobStruct $job, array $chunkReviews = [] ): ?string {
        $values = self::getChunkReviewStructFromJobStruct( $job, $chunkReviews );

        if ( !isset( $values ) ) {
            return null;
        }

        if ( !isset( $values->is_pass ) ) {
            return null;
        }

        $is_pass = $values->is_pass;

        if($is_pass == true){
            return 'excellent';
        }

        if($is_pass == false){
            return 'fail';
        }

        return null;
    }

    /**
     * @param Jobs_JobStruct $job
     * @param array          $chunkReviews
     *
     * @return ChunkReviewStruct|null
     * @throws ReflectionException
     */
    public static function getChunkReviewStructFromJobStruct( Jobs_JobStruct $job, array $chunkReviews = [] ): ?ChunkReviewStruct {
        return ( !empty( $chunkReviews ) ) ? $chunkReviews[ 0 ] : ( new ChunkReviewDao() )->findChunkReviews( $job )[ 0 ] ?? null;
    }

    /**
     * @param int    $sid
     * @param        $results array The resultset from previous getNextSegment()
     * @param string $status
     *
     * @return null|int
     */
    public static function fetchStatus( int $sid, array $results, string $status = Constants_TranslationStatus::STATUS_NEW ): ?int {

        $statusWeight = [
                Constants_TranslationStatus::STATUS_NEW        => 10,
                Constants_TranslationStatus::STATUS_DRAFT      => 10,
                Constants_TranslationStatus::STATUS_REJECTED   => 10,
                Constants_TranslationStatus::STATUS_TRANSLATED => 40,
                Constants_TranslationStatus::STATUS_APPROVED   => 50
        ];

        $nSegment = null;
        if ( isset( $results[ 0 ][ 'id' ] ) ) {
            //Check if there is a translated segment with $seg[ 'id' ] > $sid
            foreach ( $results as $seg ) {
                if ( $seg[ 'status' ] == null ) {
                    $seg[ 'status' ] = Constants_TranslationStatus::STATUS_NEW;
                }
                if ( $seg[ 'id' ] > $sid && $statusWeight[ $seg[ 'status' ] ] == $statusWeight[ $status ] ) {
                    $nSegment = $seg[ 'id' ];
                    break;
                }
            }
            // If there aren't translated segments in the next elements -> check starting from the first one
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

    /**
     * Check if a job is revision from a jid/password combination
     * (password could refer to a T, R1 or R2 job)
     *
     * @param $jid
     * @param $password
     *
     * @return bool|null
     */
    public static function getIsRevisionFromIdJobAndPassword( $jid, $password ): ?bool {

        $jobValidator = new IsJobRevisionValidator();

        try {

            $jobValidatorObject           = new IsJobRevisionValidatorObject();
            $jobValidatorObject->jid      = $jid;
            $jobValidatorObject->password = $password;

            return $jobValidator->validate( $jobValidatorObject );

        } catch ( Exception $ignore ) {
        }

        return null;
    }

    /**
     * @return bool
     */
    public static function getIsRevisionFromRequestUri(): bool {

        if ( !isset( $_SERVER[ 'REQUEST_URI' ] ) ) {
            return false;
        }

        $_from_url = parse_url( $_SERVER[ 'REQUEST_URI' ] );

        return self::isARevisePath( $_from_url[ 'path' ] );
    }

    /**
     * @return bool
     */
    public static function getIsRevisionFromReferer(): bool {

        if ( !isset( $_SERVER[ 'HTTP_REFERER' ] ) ) {
            return false;
        }

        $_from_url = parse_url( @$_SERVER[ 'HTTP_REFERER' ] );

        return self::isARevisePath( $_from_url[ 'path' ] );
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    private static function isARevisePath( string $path ): bool {
        return strpos( $path, "/revise" ) === 0;
    }

    /**
     * Get a job from a combination of ID and ANY password (t,r1 or r2)
     *
     * @param $jobId
     * @param $jobPassword
     *
     * @return null|Jobs_JobStruct
     * @throws ReflectionException
     */
    public static function getJobFromIdAndAnyPassword( $jobId, $jobPassword ): ?Jobs_JobStruct {
        $job = Jobs_JobDao::getByIdAndPassword( $jobId, $jobPassword );

        if ( !$job ) {

            $chunkReview = ChunkReviewDao::findByReviewPasswordAndJobId( $jobPassword, $jobId );

            if ( !$chunkReview ) {
                return null;
            }

            $job = $chunkReview->getChunk();
        }

        return $job;
    }

    /**
     * Get the correct password for job url
     *
     * If source_page is 1, the translation password is returned.
     *
     * Otherwise the function try to return the corresponding review_password
     *      *
     *
     * @param Jobs_JobStruct $job
     * @param int            $sourcePage
     *
     * @return string|null
     */
    public static function getJobPassword( Jobs_JobStruct $job, int $sourcePage = 1 ): ?string {
        if ( $sourcePage <= 1 ) {
            return $job->password;
        }

        $qa = ChunkReviewDao::findByIdJobAndPasswordAndSourcePage( $job->id, $job->password, $sourcePage );
        if ( !$qa ) {
            return null;
        }

        return $qa->review_password;
    }

    /**
     * get last character from a string
     * (excluding html tags)
     *
     * @param $string
     *
     * @return string
     */
    public static function getLastCharacter( $string ): string {
        return mb_substr( strip_tags( $string ), -1 );
    }

    /**
     * @param Projects_ProjectStruct $projectStruct
     *
     * @return int|null
     * @throws ReflectionException
     */
    public static function getSegmentTranslationsCount( Projects_ProjectStruct $projectStruct ): ?int {
        $idJobs = [];

        foreach ( $projectStruct->getJobs() as $job ) {
            $idJobs[] = $job->id;
        }

        $idJobs = array_unique( $idJobs );

        return Jobs_JobDao::getSegmentTranslationsCount( $idJobs );
    }

    /**
     * This function appends _{x} to a string.
     *
     * Example: house   ---> house_1
     *          house_1 ---> house_2
     *
     * @param string $string
     *
     * @return string
     */
    public static function upCountName( string $string ): string {

        if ( empty( $string ) ) {
            return Utils::randomString();
        }

        $a   = explode( "_", $string );
        $end = (int)end( $a );

        if ( ( $end > 0 ) and count( $a ) > 1 ) {
            array_pop( $a );
        }

        $name = implode( '_', $a );

        $return = $name;
        $return .= '_' . ( $end + 1 );

        return $return;
    }

    /**
     * @param $json
     *
     * @return false|string
     */
    public static function sanitizeJSON( $json ): string {
        $json = json_decode( $json, true );
        array_walk_recursive( $json, function ( &$item ) {
            $item = filter_var( $item, FILTER_SANITIZE_STRING );
        } );

        return json_encode( $json );
    }
}

