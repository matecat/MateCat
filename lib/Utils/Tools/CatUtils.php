<?php

namespace Utils\Tools;

use Exception;
use InvalidArgumentException;
use Matecat\SubFiltering\Enum\CTypeEnum;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\FilesStorage\AbstractFilesStorage;
use Model\Filters\DTO\IDto;
use Model\Filters\FiltersConfigTemplateDao;
use Model\Filters\FiltersConfigTemplateStruct;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;
use Model\Translations\SegmentTranslationDao;
use Model\Translations\SegmentTranslationStruct;
use Model\WordCount\CounterModel;
use Model\WordCount\WordCountStruct;
use ReflectionException;
use Utils\Constants\Constants;
use Utils\Constants\ProjectStatus;
use Utils\Constants\TranslationStatus;
use Utils\Logger\LoggerFactory;
use Utils\Validator\Contracts\ValidatorObject;
use Utils\Validator\IsJobRevisionValidator;

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
                if ( substr( $chunk, -1 ) == $separateWithChar || substr( $split_chunks[ $pos + 1 ] ?? "", 0, 1 ) == $separateWithChar ) {
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
     * @param SegmentTranslationStruct $translation
     * @param bool                     $is_revision
     *
     * @return void
     * @throws Exception
     */
    public static function addSegmentTranslation( SegmentTranslationStruct $translation, bool $is_revision ) {
        SegmentTranslationDao::addTranslation( $translation, $is_revision );
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

        $last_10_worked_ids = SegmentTranslationDao::getLast10TranslatedSegmentIDsInLastHour( $id_job );
        if ( !empty( $last_10_worked_ids ) and count( $last_10_worked_ids ) === 10 ) {

            // Calculating words per hour and estimated completion
            $estimation_temp  = SegmentTranslationDao::getWordsPerSecond( $id_job, $last_10_worked_ids );
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

        //return empty on string composed only by spaces
        //do nothing
        if ( preg_replace( '#\p{Z}+#u', '', $string ) == '' ) {
            return '';
        }

        //first two letter of code lang
        $source_lang_two_letter = explode( "-", $source_lang )[ 0 ];

        if ( $Filter === null ) {
            $Filter = MateCatFilter::getInstance( new FeatureSet(), $source_lang );
        }

        /**
         * Count links as one word.
         *
         * Heuristic
         * This regular expression is intentionally imperfect; its purpose is not to validate URLs but to match as many forms as possible.
         * False positives are acceptable in favor of simplifying it and reducing computational cost (for example, the part related to IPs).
         *
         * Disabled for now until better tests
         *
         * @see https://regex101.com/r/oQFKn8/5
         *
         */
        $linkRegexp = '%(?:[a-z]+://|//)?(?:[\p{Latin}\d\-_]+)?[\p{Latin}\d\-_]+\.[\p{Latin}\d\-_]+\.[\p{Latin}\d#?=.\-_]+%ui';

        $link_placeholder      = ' L ';
        $word_placeholder      = ' W ';
        $number_placeholder    = ' N ';
        $space_placeholder     = ' ';
        $variables_placeholder = ' P ';

        /**
         * Count as One Word fo CJK
         */
        if ( array_key_exists( $source_lang_two_letter, self::$cjk ) ) {
            $link_placeholder      = 'L';
            $word_placeholder      = 'W';
            $number_placeholder    = 'N';
            $variables_placeholder = 'P';
        }

        //Remove ampersands and entities.
        //Converters return entities in XML, we want raw strings.
        //
        //Take a look at this string:
        // This is a string &amp;nbsp;
        $string = html_entity_decode(
                html_entity_decode( $string, ENT_HTML401 | ENT_QUOTES, 'UTF-8' )
        );

        $string = preg_replace( $linkRegexp, $link_placeholder, $string );

        //Refine links like "php://filter/read=string.strip_tags/resource=php://input" not available in CJK because we can't use \s identifier
        $string = preg_replace( '#[a-z]+://\S+#u', $link_placeholder, $string );

        $string = $Filter->fromLayer0ToLayer1( $string );
        $string = self::replacePlaceholders( $string, $variables_placeholder );

        // replace all numbers with a placeholder, so they will be counted as 1 word
        $string = preg_replace( '/\b[0-9]+(?:[.,][0-9]+)*\b/', $number_placeholder, $string );

        /**
         * Lock Hyphenated Words and underscore composed word; count them as one word
         *
         * https://regex101.com/r/t5AG6a/3
         *
         */
        $string = preg_replace( '#(?![.\s])\p{L}+[_\p{Pd}]\p{L}+(?:[_\p{Pd}]\p{L}+)*\S+#u', $word_placeholder, $string ); // W count as one

        /**
         * Remove Unicode:
         * @see http://php.net/manual/en/regexp.reference.unicode.php
         * P -> Punctuation
         * Z -> Separator (but not spaces)
         * C -> Other
         */
        $string = preg_replace( '#[\p{P}\p{Zl}\p{Zp}\p{C}]+#u', $space_placeholder, $string );

        /**
         * Remove english possessive word count
         */
        if ( $source_lang_two_letter == "en" ) {
            $string = str_replace( ' s ', $space_placeholder, $string );
        }

        //check for a string made of spaces only, after the string was cleaned
        $no_spaces_string = preg_replace( '#[\p{Z}\p{C}]+#u', "", $string );
        if ( $no_spaces_string == "" ) {
            return "";
        }

        return !array_key_exists( $source_lang_two_letter, self::$cjk ) ? $string : $no_spaces_string;

    }

    /**
     * @param string $string
     * @param string $variables_placeholder
     *
     * @return string
     */
    private static function replacePlaceholders( string $string, string $variables_placeholder ): string {
        $pattern = '|<ph id ?= ?["\'](mtc_[0-9]+)["\'] ?(ctype=["\'].+?["\'] ?) ?(equiv-text=["\'].+?["\'] ?)/>|ui';

        preg_match_all( $pattern, $string, $matches, PREG_SET_ORDER );

        foreach ( $matches as $match ) {
            $ctype = trim( $match[ 2 ] );
            $ctype = str_replace( '"', '', $ctype );
            $ctype = str_replace( 'ctype=', '', $ctype );

            if ( in_array( $ctype, [ CTypeEnum::HTML, CTypeEnum::XML ] ) ) {
                $string = str_replace( $match[ 0 ], '', $string ); // count html snippets as zero words
            } else {
                $string = str_replace( $match[ 0 ], $variables_placeholder, $string ); // count variables as one word
            }

        }

        // remove all residual xliff tags
        if ( preg_match_all( '#</?(?![0-9]+)[a-z0-9\-._]+?(?:\s[:_a-z]+=.+?)?\s*/?>#i', $string, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $tag ) {
                $string = str_replace( $tag[ 0 ], " ", $string );
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

        if ( array_key_exists( $source_lang_two_letter, self::$cjk ) ) {
            $res = mb_strlen( $string, 'UTF-8' );
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
        LoggerFactory::doJsonLog( $cmd );

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
        $entityDecoded = html_entity_decode( $str, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        // parse and extract CDATA
        preg_match_all( '/<!\[CDATA\[((?:[^]]|](?!]>))*)]]>/', $entityDecoded, $cdataMatches );

        if ( isset( $cdataMatches[ 1 ] ) and !empty( $cdataMatches[ 1 ] ) ) {
            foreach ( $cdataMatches[ 1 ] as $k => $m ) {
                $entityDecoded = str_replace( $cdataMatches[ 0 ][ $k ], $m, $entityDecoded );
            }
        }

        return trim( strip_tags( $entityDecoded ) );
    }

    /**
     * @param JobStruct     $job
     *
     * @param ProjectStruct $projectStruct
     *
     * @return WordCountStruct
     * @throws Exception
     */
    public static function getWStructFromJobArray( JobStruct $job, ProjectStruct $projectStruct ): WordCountStruct {

        $wStruct = WordCountStruct::loadFromJob( $job );

        // For projects created with No tm analysis enabled
        if ( $wStruct->getTotal() == 0 && ( $projectStruct[ 'status_analysis' ] == ProjectStatus::STATUS_DONE || $projectStruct[ 'status_analysis' ] == ProjectStatus::STATUS_NOT_TO_ANALYZE ) ) {
            $wCounter = new CounterModel();
            $wStruct  = $wCounter->initializeJobWordCount( $job[ 'id' ], $job[ 'password' ] );
            LoggerFactory::doJsonLog( "BackWard compatibility set Counter." );

            return $wStruct;
        }

        return $wStruct;
    }

    /**
     * Returns the string representing the overall quality for a job,
     *
     * @param JobStruct $job
     *
     * @param array     $chunkReviews
     *
     * @return string
     * @throws ReflectionException
     */
    public static function getQualityOverallFromJobStruct( JobStruct $job, array $chunkReviews = [] ): ?string {
        $values = self::getChunkReviewStructFromJobStruct( $job, $chunkReviews );

        if ( !isset( $values ) ) {
            return null;
        }

        if ( !isset( $values->is_pass ) ) {
            return null;
        }

        $is_pass = $values->is_pass;

        if ( $is_pass ) {
            return 'excellent';
        }

        return 'fail';
    }

    /**
     * @param JobStruct $job
     * @param array     $chunkReviews
     *
     * @return ChunkReviewStruct|null
     * @throws ReflectionException
     */
    public static function getChunkReviewStructFromJobStruct( JobStruct $job, array $chunkReviews = [] ): ?ChunkReviewStruct {
        return ( !empty( $chunkReviews ) ) ? $chunkReviews[ 0 ] : ( new ChunkReviewDao() )->findChunkReviews( $job )[ 0 ] ?? null;
    }

    /**
     * @param int    $sid
     * @param        $results array The resultset from previous getNextSegment()
     * @param string $status
     *
     * @return null|int
     */
    public static function fetchStatus( int $sid, array $results, string $status = TranslationStatus::STATUS_NEW ): ?int {

        $statusWeight = [
                TranslationStatus::STATUS_NEW        => 10,
                TranslationStatus::STATUS_DRAFT      => 10,
                TranslationStatus::STATUS_REJECTED   => 10,
                TranslationStatus::STATUS_TRANSLATED => 40,
                TranslationStatus::STATUS_APPROVED   => 50
        ];

        $nSegment = null;
        if ( isset( $results[ 0 ][ 'id' ] ) ) {
            //Check if there is a translated segment with $seg[ 'id' ] > $sid
            foreach ( $results as $seg ) {
                if ( $seg[ 'status' ] == null ) {
                    $seg[ 'status' ] = TranslationStatus::STATUS_NEW;
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
                        $seg[ 'status' ] = TranslationStatus::STATUS_NEW;
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
     * @return bool
     */
    public static function isRevisionFromIdJobAndPassword( $jid, $password ): bool {

        $jobValidator = new IsJobRevisionValidator();

        try {

            return !empty( $jobValidator->validate(
                    ValidatorObject::fromArray( [
                            'jid'      => $jid,
                            'password' => $password
                    ] )
            ) );

        } catch ( Exception $ignore ) {
        }

        return false;
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
     * Determines if the current request originates from a "revise" path based on the HTTP referer.
     *
     * This function checks the `HTTP_REFERER` server variable to parse the URL and
     * determine if the path corresponds to a "revise" operation.
     *
     * @return bool Returns `true` if the referer path is a "revise" path, otherwise `false`.
     */
    public static function getIsRevisionFromReferer(): bool {

        // Check if the HTTP_REFERER server variable is set
        if ( !isset( $_SERVER[ 'HTTP_REFERER' ] ) ) {
            return false;
        }

        // Parse the referer URL to extract its components
        $_from_url = parse_url( $_SERVER[ 'HTTP_REFERER' ] );

        // Check if the path corresponds to a "revise" operation
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
     * @return null|JobStruct
     * @throws ReflectionException
     */
    public static function getJobFromIdAndAnyPassword( $jobId, $jobPassword ): ?JobStruct {
        $job = JobDao::getByIdAndPassword( $jobId, $jobPassword );

        if ( !$job ) {

            $chunkReview = ChunkReviewDao::findByReviewPasswordAndJobId( $jobPassword, $jobId );
            $job         = $chunkReview->getChunk();

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
     * @param JobStruct $job
     * @param int       $sourcePage
     *
     * @return string|null
     */
    public static function getJobPassword( JobStruct $job, int $sourcePage = 1 ): ?string {
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
     * @param ProjectStruct $projectStruct
     *
     * @return int|null
     * @throws ReflectionException
     */
    public static function getSegmentTranslationsCount( ProjectStruct $projectStruct ): ?int {
        $idJobs = [];

        foreach ( $projectStruct->getJobs() as $job ) {
            $idJobs[] = $job->id;
        }

        $idJobs = array_unique( $idJobs );

        return JobDao::getSegmentTranslationsCount( $idJobs );
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

    /**
     * This function is used to strip malicious content from
     * user's first_name and last_name
     *
     * @param $string
     *
     * @return string
     */
    public static function stripMaliciousContentFromAName( $string ): string {
        $string = preg_replace( '/\P{L}+/u', ' ', $string ); //replace all not letters (Unicode is valid) with a space
        $string = preg_replace( '/ {2,}/u', ' ', $string ); // replace all double spaces with a single space
        $string = mb_substr( $string, 0, 50 ); // max allowed characters are 50

        return trim( $string );
    }

    /**
     * Avoid race conditions by javascript multiple calls
     *
     * @param string      $file_path
     * @param string      $source
     * @param string|null $segmentationRule
     * @param int|null    $filtersTemplateId
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public static function deleteSha( string $file_path, string $source, ?string $segmentationRule = null, ?int $filtersTemplateId = 0 ) {

        $extraction_parameters = null;

        if ( $filtersTemplateId > 0 ) {
            $filtersTemplateStruct = FiltersConfigTemplateDao::getById( $filtersTemplateId );

            if ( $filtersTemplateStruct !== null ) {
                $extraction_parameters = self::getRightExtractionParameter( $file_path, $filtersTemplateStruct );
            }
        }

        $segmentationRule = Constants::validateSegmentationRules( $segmentationRule );

        $hash_name_for_disk =
                sha1_file( $file_path )
                . "_" .
                sha1( ( $segmentationRule ?? '' ) . ( $extraction_parameters ? json_encode( $extraction_parameters ) : '' ) )
                . "|" .
                $source;

        if ( !$hash_name_for_disk ) {
            return;
        }

        $path_parts     = pathinfo( $file_path );
        $hash_file_path = $path_parts[ 'dirname' ] . DIRECTORY_SEPARATOR . $hash_name_for_disk;

        if ( !file_exists( $hash_file_path ) ) {
            return;
        }

        //can be present more than one file with the same sha
        //so in the sha1 file there could be more than one row
        //  $file_sha = glob( $hash_name_for_disk . "*" ); //delete sha1 also

        $fp = fopen( $hash_file_path, "r+" );

        // no file found
        if ( !$fp ) {
            return;
        }

        $i = 0;
        while ( !flock( $fp, LOCK_EX | LOCK_NB ) ) {  // acquire an exclusive lock
            $i++;
            if ( $i == 40 ) {
                return;
            } //exit the loop after 2 seconds, can not acquire the lock
            usleep( 50000 );
        }

        $file_content       = fread( $fp, filesize( $hash_file_path ) );
        $file_content_array = explode( "\n", $file_content );

        //remove the last line ( is an empty string )
        array_pop( $file_content_array );

        $fileName = AbstractFilesStorage::basename_fix( $file_path );

        $key = array_search( $fileName, $file_content_array );
        unset( $file_content_array[ $key ] );

        if ( !empty( $file_content_array ) ) {
            fseek( $fp, 0 ); //rewind
            ftruncate( $fp, 0 ); //truncate to zero bytes length
            fwrite( $fp, implode( "\n", $file_content_array ) . "\n" );
            fflush( $fp );
            flock( $fp, LOCK_UN );    // release the lock
            fclose( $fp );
        } else {
            flock( $fp, LOCK_UN );    // release the lock
            fclose( $fp );
            @unlink( @$hash_file_path );
        }

    }

    /**
     * @param string                      $filePath
     * @param FiltersConfigTemplateStruct $filters_extraction_parameters
     *
     * @return IDto|null
     */
    private static function getRightExtractionParameter( string $filePath, FiltersConfigTemplateStruct $filters_extraction_parameters ): ?IDto {

        $extension = AbstractFilesStorage::pathinfo_fix( $filePath, PATHINFO_EXTENSION );
        $params    = null;

        // send extraction params based on the file extension
        switch ( $extension ) {
            case "json":
                if ( isset( $filters_extraction_parameters->json ) ) {
                    $params = $filters_extraction_parameters->json;
                }
                break;
            case "xml":
                if ( isset( $filters_extraction_parameters->xml ) ) {
                    $params = $filters_extraction_parameters->xml;
                }
                break;
            case "yml":
            case "yaml":
                if ( isset( $filters_extraction_parameters->yaml ) ) {
                    $params = $filters_extraction_parameters->yaml;
                }
                break;
            case "doc":
            case "docx":
                if ( isset( $filters_extraction_parameters->ms_word ) ) {
                    $params = $filters_extraction_parameters->ms_word;
                }
                break;
            case "xls":
            case "xlsx":
                if ( isset( $filters_extraction_parameters->ms_excel ) ) {
                    $params = $filters_extraction_parameters->ms_excel;
                }
                break;
            case "ppt":
            case "pptx":
                if ( isset( $filters_extraction_parameters->ms_powerpoint ) ) {
                    $params = $filters_extraction_parameters->ms_powerpoint;
                }
                break;
            case "dita":
            case "ditamap":
                if ( isset( $filters_extraction_parameters->dita ) ) {
                    $params = $filters_extraction_parameters->dita;
                }
                break;
        }

        return $params;
    }

    /**
     * This functions removes symbols from a string
     *
     * @param string $name
     *
     * @return string
     */
    public static function sanitizeProjectName( string $name ): string {
        return preg_replace( '/[^\p{L}\p{N}\s]/u', '', $name );
    }

    /**
     * This functions check if the name contains any symbol
     *
     * @param $name
     *
     * @return bool
     */
    public static function validateProjectName( string $name ): bool {
        return self::sanitizeProjectName( $name ) === $name;
    }

    /**
     * This method can be use as polyfill of FILTER_SANITIZE_STRING,
     * which is DEPRECATED in PHP >= 8.1
     *
     * @param string $string
     *
     * @return string
     */
    public static function filter_string_polyfill( string $string ): string {
        $str = preg_replace( '/\x00|<[^>]*>?/', '', $string );

        return str_replace( [ "'", '"' ], [ '&#39;', '&#34;' ], $str );
    }

    /**
     * @param $filename
     *
     * @return string
     */
    public static function encodeFileName( $filename ) {
        return rtrim( strtr( base64_encode( gzdeflate( $filename, 9 ) ), '+/', '-_' ), '=' );
    }

    /**
     * @param $filename
     *
     * @return false|string
     */
    public static function decodeFileName( $filename ) {
        return gzinflate( base64_decode( strtr( $filename, '-_', '+/' ) ) );
    }
}

