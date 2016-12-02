<?php include_once INIT::$UTILS_ROOT . '/Log.php';

/**
 * Class errObject
 * Object vector for error reporting.
 * json_encode facilities of public properties.
 *
 * __toString method are used for array_count_values and array_unique over container
 *
 */
class errObject {

    public $outcome;
    public $debug;
    public $tip = "";

    protected $orig_debug;

    /**
     * Output externally the original debug string, needed for occurrence count
     * @return string
     */
    public function getOrigDebug() {
        return $this->orig_debug;
    }

    /**
     * Outputs externally the error tip
     * @return string
     */
    public function getTip() {
        return $this->tip;
    }

    /**
     * Static instance constructor
     *
     * @param array $errors
     *
     * @return errObject
     */
    public static function get( array $errors ) {
        $errObj             = new self();
        $errObj->outcome    = $errors[ 'outcome' ];
        $errObj->orig_debug = $errors[ 'debug' ];
        $errObj->debug      = $errors[ 'debug' ];

        (!empty($errors[ 'tip' ])) ? $errObj->tip        = $errors[ 'tip' ] : null;

        return $errObj;
    }

    /**
     * Return string id
     * @return string
     */
    public function __toString() {
        return (string)$this->outcome;
    }

}

/**
 * Translation string quality assurance.
 *
 * Used for integrity comparison of XML ( xliff ) strings. <br />
 * Add errors/warnings to a list every time it found a mismatch between strings.
 *
 * Only <g> and <x/> are for now allowed.
 *
 * It use DOMDocument ( libxml ) for input, traversing and output of well formed XML strings.
 * Also use regular expressions to check for characters between tags
 *
 * NOTE:
 * If a not well formed XML source/target is provided, all integrity checks are skipped returning a
 * 'bad source xml/bad target xml/Tag mismatch' error.
 *
 * Use Example:
 * <br />
 * <pre>
 *      $check = new QA($source_seg, $target_seg);
 *      $check->performConsistencyCheck();
 *      print_r( $check->getErrors() );
 *
 *      if( !$check->thereAreErrors() ) {
 *          print_r( 'Normalization: ' . $check->getTrgNormalized() );
 *      } else {
 *          //raise an exception when a normalized string is requested and there are errors
 *          try {
 *              print_r( 'Normalization: ' . $check->getTrgNormalized() );
 *          } catch ( Exception $e ) {
 *              //No normalization was made on $target_seg
 *          }
 *      }
 *
 * </pre>
 *
 * Check Cases:
 *
 * 1) Bad source xml <br />
 * 2) Bad target xml <br />
 * 3) Tag count mismatch <br />
 * 4) Tag id mismatch <br />
 * 5) Tag content: Head whitespaces mismatch ( if a beginning withespace is present only in one of them ) <br />
 * 6) Tag content: Tail whitespaces mismatch ( if a trailing withespace is present only in one of them ) <br />
 * 7) Tag content: Head TAB mismatch ( if a beginning TAB is present only in one of them ) <br />
 * 8) Tag content: Tail tab mismatch ( if a trailing TAB is present only in one of them ) <br />
 * 9) Tag content: Head carriage return / new line mismatch ( if a beginning carriage return / new line is present only in one of them ) <br />
 * 10) Tag content: Tail carriage return / new line mismatch ( if a trailing carriage return / new line is present only in one of them ) <br />
 * 11) Tag boundary: Head characters mismatch ( if between tags there are different special characters [\s\t\r\n] before first normal char occurrence ) <br />
 * 12) Tag boundary: Tail characters mismatch ( if at the end of last tag there are different characters [\s\t\r\n] ) <br />
 * 13) Tag X is self-closing tag, not properly ended should be <x .... /> <br />
 * 14) Mismatch of Special chars ( and spaces ) before a tag or after a closing g tag
 */
class QA {

    /**
     * RAW Source string segment for comparison
     *
     * @var string
     */
    protected $source_seg;

    /**
     * RAW Target Segment for comparisonm
     * @var string
     */
    protected $target_seg;

    /**
     * Class Reference of DOMDocument Source created from raw string
     *
     * @var DOMDocument
     */
    protected $srcDom;

    /**
     * Class Reference of DOMDocument Target created from raw string
     *
     * @var DOMDocument
     */
    protected $trgDom;

    /**
     * Class Reference of DOMDocument for the internal normalizations
     * @var DOMDocument
     */
    protected $normalizedTrgDOM;

    /**
     * Class Reference DOMNodeList from $normalizedTrgDOM
     *
     * @var DOMNodeList
     */
    protected $normalizedTrgDOMNodeList;

    /**
     * Class reference to the Map of source DOM
     *
     * @var array
     */
    protected $srcDomMap;// = array( 'elemCount' => 0, 'x' => array(), 'g' => array(), 'refID' => array(), 'DOMElement' => array(), 'DOMText' => array() );

    /**
     * Class reference to the Map of target DOM
     *
     * @var array
     */
    protected $trgDomMap; // = array( 'elemCount' => 0, 'x' => array(), 'g' => array(), 'refID' => array(), 'DOMElement' => array(), 'DOMText' => array() );

    /**
     * Array of Diff structure malformed xml content
     *
     * @var array
     */
    protected $malformedXmlStructDiff = array( 'source' => array(), 'target' => array() );

    /**
     * Array of Tag position errors
     *
     * @var array
     */
    protected $tagPositionError = array();

    const ERR_NONE = 0;
    const ERR_COUNT = 1;
    const ERR_SOURCE = 2;
    const ERR_TARGET = 3;
    const ERR_TAG_ID = 4;
    const ERR_WS_HEAD = 5;
    const ERR_WS_TAIL = 6;
    const ERR_TAB_HEAD = 7;
    const ERR_TAB_TAIL = 8;
    const ERR_CR_HEAD = 9;
    const ERR_CR_TAIL = 10;
    const ERR_BOUNDARY_HEAD = 11;
    const ERR_BOUNDARY_TAIL = 12;
    const ERR_UNCLOSED_X_TAG = 13;
    const ERR_BOUNDARY_HEAD_TEXT = 14;
    const ERR_TAG_ORDER = 15;
    const ERR_NEWLINE_MISMATCH = 16;
    const ERR_DOLLAR_MISMATCH = 17;
    const ERR_AMPERSAND_MISMATCH = 18;
    const ERR_AT_MISMATCH = 19;
    const ERR_HASH_MISMATCH = 20;
    const ERR_POUNDSIGN_MISMATCH = 21;
    const ERR_PERCENT_MISMATCH = 22;
    const ERR_EQUALSIGN_MISMATCH = 23;
    const ERR_TAB_MISMATCH = 24;
    const ERR_STARSIGN_MISMATCH = 25;
    const ERR_GLOSSARY_MISMATCH = 26;
    const ERR_SPECIAL_ENTITY_MISMATCH = 27;
    const ERR_EUROSIGN_MISMATCH = 28;

    const ERR_TAG_MISMATCH = 1000;

    const ERR_SPACE_MISMATCH = 1100;

    const ERR_SPACE_MISMATCH_TEXT = 1101;

    const ERR_SYMBOL_MISMATCH = 1200;
    /**
     * Human Readable error map.
     * Created accordly with Error constants
     *
     * <pre>
     * array (
     *     ERR_NONE                =>  '',
     *     ERR_COUNT               =>  'Tag count mismatch',
     *     ERR_SOURCE              =>  'bad source xml',
     *     ERR_TARGET              =>  'bad target xml',
     *     ERR_TAG_ID              =>  'Tag id mismatch',
     *     ERR_WS_HEAD             =>  'Heading whitespaces mismatch',
     *     ERR_WS_TAIL             =>  'Tail whitespaces mismatch',
     *     ERR_TAB_HEAD            =>  'Heading tab mismatch',
     *     ERR_TAB_TAIL            =>  'Tail tab mismatch',
     *     ERR_CR_HEAD             =>  'Heading carriage return mismatch',
     *     ERR_CR_TAIL             =>  'Tail carriage return mismatch',
     *     ERR_BOUNDARY_HEAD       =>  'Mismatch of special chars between G TAGS before first char occurrence',
     *     ERR_BOUNDARY_TAIL       =>  'End line char mismatch',
     *     ERR_UNCLOSED_X_TAG      =>  'Wrong format for x tag.Should be <x .... />'
     *     ERR_BOUNDARY_HEAD_TEXT  =>  'Mismatch of Special chars ( and spaces ) before a tag or after a closing G tag'
     *     ERR_TAG_ORDER           =>  'Mismatch of position between source and target tag'
     * );
     * </pre>
     * @var array(string)
     */
    protected $_errorMap = array(
            0    => '',
            1    => 'Tag count mismatch',
            2    => 'bad source xml',
            3    => 'bad target xml',
            4    => 'Tag ID mismatch: Check and edit tags with differing IDs.',
            5    => 'Heading whitespaces mismatch',
            6    => 'Tail whitespaces mismatch',
            7    => 'Heading tab mismatch',
            8    => 'Tail tab mismatch',
            9    => 'Heading carriage return mismatch',
            10   => 'Tail carriage return mismatch',
            11   => 'Char mismatch between tags',
            12   => 'End line char mismatch',
            13   => 'Wrong format for x tag. Should be < x .... />',
            14   => 'Char mismatch before a tag',
            15   => 'Tag order mismatch',
            16   => 'New line mismatch',
            17   => 'Dollar sign mismatch',
            18   => 'Ampersand sign mismatch',
            19   => 'At sign mismatch',
            20   => 'Hash sign mismatch',
            21   => 'Pound sign mismatch',
            22   => 'Percent sign mismatch',
            23   => 'Equalsign sign mismatch',
            24   => 'Tab sign mismatch',
            25   => 'Star sign mismatch',
            26   => 'Glossary mismatch',
            27   => 'Special char entity mismatch',

            /*
             * grouping
             *  1 =>  'Tag count mismatch',
             *  2 =>  'bad source xml',
             *  3 =>  'bad target xml',
             */
            1000 => 'Tag mismatch.',

            /*
             * grouping
             *  5 =>  'Heading whitespaces mismatch',
             *  6 =>  'Tail whitespaces mismatch',
             *  7 =>  'Heading tab mismatch',
             *  8 =>  'Tail tab mismatch',
             *  9 =>  'Heading carriage return mismatch',
             *  11 => 'Char mismatch between tags',
             *  12 => 'End line char mismatch',
             *  14 => 'Char mismatch before a tag',
             */
            1100 => 'More/fewer whitespaces found next to the tags.',

            1101 => 'More/fewer whitespaces found in the text.',
            /*
             * grouping 
             * 17 => 'Dollar sign mismatch',
             * 18 => 'Ampersand sign mismatch',
             * 19 => 'At sign mismatch',
             * 20 => 'Hash sign mismatch',
             * 21 => 'Pound sign mismatch',
             * 22 => 'Percent sign mismatch',
             * 23 => 'Equalsign sign mismatch',
             * 24 => 'Tab sign mismatch',
             * 25 => 'Star sign mismatch',
             */
            1200 => 'Symbol mismatch',
    );

    protected $_tipMap = array(
        /*
         * grouping
         *  1 =>  'Tag count mismatch',
         *  2 =>  'bad source xml',
         *  3 =>  'bad target xml',
         */
        1000 => "Press the < key to add tags."
    );

    protected static $asciiPlaceHoldMap = array(
            '00' => array( 'symbol' => 'NULL', 'placeHold' => '##$_00$##', 'numeral' => 0x00 ),
            '01' => array( 'symbol' => 'SOH', 'placeHold' => '##$_01$##', 'numeral' => 0x01 ),
            '02' => array( 'symbol' => 'STX', 'placeHold' => '##$_02$##', 'numeral' => 0x02 ),
            '03' => array( 'symbol' => 'ETX', 'placeHold' => '##$_03$##', 'numeral' => 0x03 ),
            '04' => array( 'symbol' => 'EOT', 'placeHold' => '##$_04$##', 'numeral' => 0x04 ),
            '05' => array( 'symbol' => 'ENQ', 'placeHold' => '##$_05$##', 'numeral' => 0x05 ),
            '06' => array( 'symbol' => 'ACK', 'placeHold' => '##$_06$##', 'numeral' => 0x06 ),
            '07' => array( 'symbol' => 'BEL', 'placeHold' => '##$_07$##', 'numeral' => 0x07 ),
            '08' => array( 'symbol' => 'BS', 'placeHold' => '##$_08$##', 'numeral' => 0x08 ),
            '09' => array( 'symbol' => 'HT', 'placeHold' => '##$_09$##', 'numeral' => 0x09 ),
            '0A' => array( 'symbol' => 'LF', 'placeHold' => '##$_0A$##', 'numeral' => 0x0A ),
            '0B' => array( 'symbol' => 'VT', 'placeHold' => '##$_0B$##', 'numeral' => 0x0B ),
            '0C' => array( 'symbol' => 'FF', 'placeHold' => '##$_0C$##', 'numeral' => 0x0C ),
            '0D' => array( 'symbol' => 'CR', 'placeHold' => '##$_0D$##', 'numeral' => 0x0D ),
            '0E' => array( 'symbol' => 'SO', 'placeHold' => '##$_0E$##', 'numeral' => 0x0E ),
            '0F' => array( 'symbol' => 'SI', 'placeHold' => '##$_0F$##', 'numeral' => 0x0F ),
            '10' => array( 'symbol' => 'DLE', 'placeHold' => '##$_10$##', 'numeral' => 0x10 ),
            '11' => array( 'symbol' => 'DC', 'placeHold' => '##$_11$##', 'numeral' => 0x11 ),
            '12' => array( 'symbol' => 'DC', 'placeHold' => '##$_12$##', 'numeral' => 0x12 ),
            '13' => array( 'symbol' => 'DC', 'placeHold' => '##$_13$##', 'numeral' => 0x13 ),
            '14' => array( 'symbol' => 'DC', 'placeHold' => '##$_14$##', 'numeral' => 0x14 ),
            '15' => array( 'symbol' => 'NAK', 'placeHold' => '##$_15$##', 'numeral' => 0x15 ),
            '16' => array( 'symbol' => 'SYN', 'placeHold' => '##$_16$##', 'numeral' => 0x16 ),
            '17' => array( 'symbol' => 'ETB', 'placeHold' => '##$_17$##', 'numeral' => 0x17 ),
            '18' => array( 'symbol' => 'CAN', 'placeHold' => '##$_18$##', 'numeral' => 0x18 ),
            '19' => array( 'symbol' => 'EM', 'placeHold' => '##$_19$##', 'numeral' => 0x19 ),
            '1A' => array( 'symbol' => 'SUB', 'placeHold' => '##$_1A$##', 'numeral' => 0x1A ),
            '1B' => array( 'symbol' => 'ESC', 'placeHold' => '##$_1B$##', 'numeral' => 0x1B ),
            '1C' => array( 'symbol' => 'FS', 'placeHold' => '##$_1C$##', 'numeral' => 0x1C ),
            '1D' => array( 'symbol' => 'GS', 'placeHold' => '##$_1D$##', 'numeral' => 0x1D ),
            '1E' => array( 'symbol' => 'RS', 'placeHold' => '##$_1E$##', 'numeral' => 0x1E ),
            '1F' => array( 'symbol' => 'US', 'placeHold' => '##$_1F$##', 'numeral' => 0x1F ),
            '7F' => array( 'symbol' => 'DEL', 'placeHold' => '##$_7F$##', 'numeral' => 0x7F ),
    );

    protected static $regexpAscii = '/([\x{00}-\x{1F}\x{7F}]{1})/u';

    protected static $regexpEntity = '/&#x([0-1]{0,1}[0-9A-F]{1,2})/u'; //&#x1E;  &#xE;

    protected static $regexpPlaceHoldAscii = '/##\$_([0-1]{0,1}[0-9A-F]{1,2})\$##/u';


    const ERROR = 'ERROR';
    const WARNING = 'WARNING';
    const INFO = 'INFO';

    /**
     * List of Errors from  check analysis
     *
     * @var array(errObject(number:string))
     */
    protected $exceptionList = array( self::ERROR => array(), self::WARNING => array(), self::INFO => array() );

    /**
     * List of warnings from check analysis
     *
     * @var array(errObject(number,string))
     */
    protected $warningList = array();

    protected function _getTipValue( $errorID ){
        if( array_key_exists( $errorID, $this->_tipMap ) ){
            return $this->_tipMap[ $errorID ];
        }
    }

    /**
     * Add an error to error List.
     * Internal CodeMap
     *
     * @param int $errCode
     */
    protected function _addError( $errCode ) {

        //Real error Code log
//        try {
//            throw new Exception('');
//        } catch( Exception $e ){
//            Log::doLog( $errCode . " :: " . $this->_errorMap[$errCode]);
//            $trace = $e->getTrace();
//            Log::doLog( $trace[1] );
//        }

        switch ( $errCode ) {
            case self::ERR_COUNT:
            case self::ERR_SOURCE:
            case self::ERR_TARGET:
                $this->exceptionList[ self::ERROR ][] = errObject::get( array(
                        'outcome' => self::ERR_TAG_MISMATCH,
                        'debug'   => $this->_errorMap[ self::ERR_TAG_MISMATCH ],
                        'tip'     => $this->_getTipValue( self::ERR_TAG_MISMATCH )
                ) );
                break;
            case self::ERR_TAG_ID:
                $this->exceptionList[ self::ERROR ][] = errObject::get( array(
                        'outcome' => self::ERR_TAG_ID,
                        'debug'   => $this->_errorMap[ self::ERR_TAG_ID ],
                        'tip'     => $this->_getTipValue( self::ERR_TAG_ID )
                ) );
                break;
            case self::ERR_UNCLOSED_X_TAG:
                $this->exceptionList[ self::ERROR ][] = errObject::get( array(
                        'outcome' => $errCode,
                        'debug'   => $this->_errorMap[ $errCode ],
                        'tip'     => $this->_getTipValue( $errCode )
                ) );
                break;

            case self::ERR_WS_HEAD:
            case self::ERR_WS_TAIL:
            $this->exceptionList[ self::INFO ][] = errObject::get( array(
                    'outcome' => self::ERR_SPACE_MISMATCH_TEXT,
                    'debug'   => $this->_errorMap[ self::ERR_SPACE_MISMATCH_TEXT ],
                    'tip'     => $this->_getTipValue( self::ERR_SPACE_MISMATCH_TEXT )
            ) );
            break;


            case self::ERR_TAB_HEAD:
            case self::ERR_TAB_TAIL:
            $this->exceptionList[ self::INFO ][] = errObject::get( array(
                    'outcome' => self::ERR_TAB_MISMATCH,
                    'debug'   => $this->_errorMap[ self::ERR_TAB_MISMATCH ],
                    'tip'     => $this->_getTipValue( self::ERR_TAB_MISMATCH )
            ) );
            break;

            case self::ERR_BOUNDARY_HEAD:
            case self::ERR_BOUNDARY_TAIL:
            case self::ERR_BOUNDARY_HEAD_TEXT:
                $this->exceptionList[ self::INFO ][] = errObject::get( array(
                        'outcome' => self::ERR_SPACE_MISMATCH,
                        'debug'   => $this->_errorMap[ self::ERR_SPACE_MISMATCH ],
                        'tip'     => $this->_getTipValue( self::ERR_SPACE_MISMATCH )
                ) );
                break;

            case self::ERR_DOLLAR_MISMATCH :
            case self::ERR_AMPERSAND_MISMATCH :
            case self::ERR_AT_MISMATCH :
            case self::ERR_HASH_MISMATCH :
            case self::ERR_POUNDSIGN_MISMATCH :
            case self::ERR_EUROSIGN_MISMATCH :
            case self::ERR_PERCENT_MISMATCH :
            case self::ERR_EQUALSIGN_MISMATCH :
            case self::ERR_TAB_MISMATCH :
            case self::ERR_STARSIGN_MISMATCH :
            case self::ERR_SPECIAL_ENTITY_MISMATCH :
                $this->exceptionList[ self::INFO ][] = errObject::get( array(
                        'outcome' => self::ERR_SYMBOL_MISMATCH,
                        'debug'   => $this->_errorMap[ self::ERR_SYMBOL_MISMATCH ],
                        'tip'     => $this->_getTipValue( self::ERR_SYMBOL_MISMATCH )
                ) );
                break;

            case self::ERR_NEWLINE_MISMATCH:
                $this->exceptionList[ self::INFO ][] = errObject::get( array(
                        'outcome' => self::ERR_NEWLINE_MISMATCH,
                        'debug'   => $this->_errorMap[ self::ERR_NEWLINE_MISMATCH ],
                        'tip'     => $this->_getTipValue( self::ERR_NEWLINE_MISMATCH )
                ) );
                break;

            case self::ERR_TAG_ORDER:
            default:
                $this->exceptionList[ self::WARNING ][] = errObject::get( array(
                        'outcome' => $errCode,
                        'debug'   => $this->_errorMap[ $errCode ],
                        'tip'     => $this->_getTipValue( $errCode )
                ) );
                break;
        }

    }

    /**
     * Get Information about Error on the basis of the required level
     *
     * @param $level
     *
     * @return bool
     */
    protected function _thereAreErrorLevel( $level ) {

        switch ( $level ) {
            case self::ERROR:
                return !empty( $this->exceptionList[ self::ERROR ] );
                break;
            case self::WARNING:
                $warnings = array_merge( $this->exceptionList[ self::ERROR ], $this->exceptionList[ self::WARNING ] );

                return !empty( $warnings );
                break;
            case self::INFO:
                $warnings = array_merge( $this->exceptionList[ self::INFO ], $this->exceptionList[ self::ERROR ], $this->exceptionList[ self::WARNING ] );

                return !empty( $warnings );
                break;
        }

    }

    /**
     * Check for found Errors
     *
     * @return bool
     */
    public function thereAreErrors() {
        return $this->_thereAreErrorLevel( self::ERROR );
    }

    /**
     * Export Error List
     *
     */
    public function getErrors() {
        return $this->checkErrorNone( self::ERROR );
    }

    /**
     * Get error list in json format
     *
     * @return string Json
     */
    public function getErrorsJSON() {
        return json_encode( $this->checkErrorNone( self::ERROR, true ) );
    }

    /**
     * Check For Warnings
     *
     * return bool
     */
    public function thereAreWarnings() {
        return $this->_thereAreErrorLevel( self::WARNING );
    }

    /**
     * Get Warning level errors
     *
     * @return errObject[]
     */
    public function getWarnings() {
        return $this->checkErrorNone( self::WARNING );
    }

    /**
     * Get warning list in json format
     *
     * @return string Json
     */
    public function getWarningsJSON() {
        return json_encode( $this->checkErrorNone( self::WARNING, true ) );
    }

    /**
     * Check For Notices
     *
     * return bool
     */
    public function thereAreNotices() {
        return $this->_thereAreErrorLevel( self::INFO );
    }

    /**
     * Get Notice level errors
     *
     * @return errObject[]
     */
    public function getNotices() {
        return $this->checkErrorNone( self::INFO );
    }

    /**
     * Get Notices list in json format
     *
     * @return string Json
     */
    public function getNoticesJSON() {
        return json_encode( $this->checkErrorNone( self::INFO, true ) );
    }

    /**
     * Display an ERR_NONE if array is empty
     *
     * <pre>
     * Array
     * (
     *     [0] => errObject Object
     *         (
     *             [outcome] => 0
     *             [debug] =>
     *         ),
     * )
     * </pre>
     *
     * @param string $level
     * @param bool   $count
     *
     * @return errObject[]
     */
    protected function checkErrorNone( $level = self::ERROR, $count = false ) {

        if ( !$this->_thereAreErrorLevel( $level ) ) {
            return array(
                    errObject::get( array(
                            'outcome' => self::ERR_NONE, 'debug' => $this->_errorMap[ self::ERR_NONE ] . " [ 0 ]"
                    ) )
            );
        }

        switch ( $level ) {
            case self::INFO:
                $list = array_merge( $this->exceptionList[ self::INFO ], $this->exceptionList[ self::WARNING ], $this->exceptionList[ self::ERROR ] );
                break;
            case self::WARNING:
                $list = array_merge( $this->exceptionList[ self::WARNING ], $this->exceptionList[ self::ERROR ] );
                break;
            case self::ERROR:
            default:
                $list = $this->exceptionList[ self::ERROR ];
                break;
        }

        if ( $count ) {
            /**
             * count same values in array of errors.
             * we use array_map with strval callback function because array_count_values can count only strings or int
             * so:
             * __toString was made internally in errObject class
             *
             * @see http://www.php.net/manual/en/function.array-count-values.php
             **/
            $errorCount = array_count_values( array_map( 'strval', $list ) );
            /**
             * array_unique remove duplicated values in array,
             * Two elements are considered equal if and only if (string) $elem1 === (string) $elem2
             * so:
             * __toString was made internally in errObject class
             *
             * @see http://www.php.net/manual/en/function.array-unique.php
             */
            $list = array_values( array_unique( $list ) );

            /**
             * @var $errObj errObject
             */
            foreach ( $list as $errObj ) {
                $errObj->debug = $errObj->getOrigDebug() .
                            " ( " . $errorCount[ $errObj->outcome ] . " )" ;
            }

        }

        return $list;

    }

    /**
     * Class constructor
     *
     * Arguments: raw XML source string and raw XML target string
     *
     * @param string $source_seg
     * @param string $target_seg
     *
     */
    public function __construct( $source_seg, $target_seg ) {
        mb_regex_encoding( 'UTF-8' );
        mb_internal_encoding( "UTF-8" );

        $src_enc = mb_detect_encoding( $source_seg );
        $trg_enc = mb_detect_encoding( $target_seg );

        $source_seg = mb_convert_encoding( $source_seg, 'UTF-8', $src_enc );
        $target_seg = mb_convert_encoding( $target_seg, 'UTF-8', $trg_enc );

        /**
         * Why i do this?? I'm replacing non printable chars with a placeholder.
         * this because DomDocument can not handle not printable chars
         *
         * @see getTrgNormalized
         */
        preg_match_all( self::$regexpAscii, $source_seg, $matches_src );
        preg_match_all( self::$regexpAscii, $target_seg, $matches_trg );

//        Log::doLog($source_seg);
//        Log::doLog($target_seg);

        if ( !empty( $matches_src[ 1 ] ) ) {
            $test_src = $source_seg;
            foreach ( $matches_src[ 1 ] as $v ) {
                $key      = "" . sprintf( "%02X", ord( $v ) ) . "";
                $test_src = preg_replace( '/(\x{' . sprintf( "%02X", ord( $v ) ) . '}{1})/u', self::$asciiPlaceHoldMap[ $key ][ 'placeHold' ], $test_src, 1 );
            }
            //Source Content wrong use placeholded one
            $source_seg = $test_src;
        }

        if ( !empty( $matches_trg[ 1 ] ) ) {
            $test_trg = $target_seg;
            foreach ( $matches_trg[ 1 ] as $v ) {
                $key      = "" . sprintf( "%02X", ord( $v ) ) . "";
                $test_trg = preg_replace( '/(\x{' . sprintf( "%02X", ord( $v ) ) . '}{1})/u', self::$asciiPlaceHoldMap[ $key ][ 'placeHold' ], $test_trg, 1 );
            }
            //Target Content wrong use placeholded one
            $target_seg = $test_trg;
        }

//        Log::hexDump($source_seg);
//        Log::hexDump($target_seg);

        /**
         * Do it again for entities because
         *
         * $segment = html_entity_decode($segment, ENT_NOQUOTES | ENT_XML1, 'UTF-8');
         *
         * does not works for not printable chars
         *
         */
        preg_match_all( self::$regexpEntity, $source_seg, $matches_src );
        preg_match_all( self::$regexpEntity, $target_seg, $matches_trg );

        if ( !empty( $matches_src[ 1 ] ) ) {
            $test_src = $source_seg;
            foreach ( $matches_src[ 1 ] as $v ) {
                $byte = sprintf( "%02X", hexdec( $v ) );
                if ( $byte[ 0 ] == '0' ) {
                    $regexp = '/&#x([' . $byte[ 0 ] . ']{0,1}' . $byte[ 1 ] . ');/u';
                } else {
                    $regexp = '/&#x(' . $byte . ');/u';
                }

                $key = "" . sprintf( "%02X", hexdec( $v ) ) . "";
                if ( array_key_exists( $key, self::$asciiPlaceHoldMap ) ) {
                    $test_src = preg_replace( $regexp, self::$asciiPlaceHoldMap[ $key ][ 'placeHold' ], $test_src );
                }

            }
            //Source Content wrong use placeholded one
            $source_seg = $test_src;
        }

        if ( !empty( $matches_trg[ 1 ] ) ) {
            $test_trg = $target_seg;
            foreach ( $matches_trg[ 1 ] as $v ) {
                $byte = sprintf( "%02X", hexdec( $v ) );
                if ( $byte[ 0 ] == '0' ) {
                    $regexp = '/&#x([' . $byte[ 0 ] . ']{0,1}' . $byte[ 1 ] . ');/u';
                } else {
                    $regexp = '/&#x(' . $byte . ');/u';
                }

                $key = "" . sprintf( "%02X", hexdec( $v ) ) . "";
                if ( array_key_exists( $key, self::$asciiPlaceHoldMap ) ) {
                    $test_trg = preg_replace( $regexp, self::$asciiPlaceHoldMap[ $key ][ 'placeHold' ], $test_trg );
                }

            }
            //Target Content wrong use placeholded one
            $target_seg = $test_trg;
        }

//        Log::doLog($_POST);
//        Log::doLog($source_seg);
//        Log::doLog($target_seg);
//        Log::hexDump($source_seg);
//        Log::hexDump($target_seg);

        $this->source_seg = $source_seg;
        $this->target_seg = $target_seg;

        $this->srcDom = $this->_loadDom( $source_seg, self::ERR_SOURCE );
        $this->trgDom = $this->_loadDom( $target_seg, self::ERR_TARGET );


        if ( $this->thereAreErrors() ) {
            $this->_getTagDiff();
        }

        $this->_resetDOMMaps();

    }

    /**
     * After initialization by Constructor, the dom is parsed and map structures are built
     *
     * @throws Exception
     */
    protected function _prepareDOMStructures() {

        $srcNodeList = @$this->srcDom->getElementsByTagName( 'root' )->item( 0 )->childNodes;
        $trgNodeList = @$this->trgDom->getElementsByTagName( 'root' )->item( 0 )->childNodes;

        if ( !$srcNodeList instanceof DOMNodeList || !$trgNodeList instanceof DOMNodeList ) {
            throw new DOMException( 'Bad DOMNodeList' );
        }

        //Create a dom node map
        $this->_mapDom( $srcNodeList, $trgNodeList );

        //Save normalized dom Element
        $this->normalizedTrgDOM = clone $this->trgDom;

        //Save normalized Dom Node list
        $this->normalizedTrgDOMNodeList = @$this->normalizedTrgDOM->getElementsByTagName( 'root' )->item( 0 )->childNodes;

        return array( $srcNodeList, $trgNodeList );

    }

    /**
     *
     * Build a node map tree of XML source and XML target
     * parsing the node list instances.
     *
     * @param DOMNodeList $srcNodeList
     * @param DOMNodeList $trgNodeList
     *
     * @return array
     */
    protected function _mapDom( DOMNodeList $srcNodeList, DOMNodeList $trgNodeList ) {

        if ( empty( $this->srcDomMap[ 'elemCount' ] ) || empty( $this->trgDomMap[ 'elemCount' ] ) ) {
            $this->_mapElements( $srcNodeList, $this->srcDomMap );
            $this->_mapElements( $trgNodeList, $this->trgDomMap );
        }

        return array( $this->srcDomMap, $this->trgDomMap );
    }

    /**
     * Create a map of NodeTree walking recursively a DOMNodeList
     *
     * <pre>
     * EX:
     * <g id="23e"><g id="pt26">what is that</g><g id="pt27">?</g></g>
     *
     * array (
     *   'elemCount' => 5,
     *   'x' =>
     *       array (
     *       ),
     *   'g' =>
     *       array (
     *         0 => '23e',
     *         1 => 'pt26',
     *         2 => 'pt27',
     *       ),
     *   'refID' =>
     *       array (
     *         '23e' => 'g',
     *         'pt26' => 'g',
     *         'pt27' => 'g',
     *       ),
     *   'DOMElement' =>
     *   array (
     *     0 =>
     *         array (
     *           'type' => 'DOMElement',
     *           'name' => 'g',
     *           'id' => '23e',
     *           'parent_id' => NULL,
     *           'node_idx' => 0,
     *         ),
     *     1 =>
     *         array (
     *           'type' => 'DOMElement',
     *           'name' => 'g',
     *           'id' => 'pt26',
     *           'parent_id' => '23e',
     *           'node_idx' => 0,
     *         ),
     *     2 =>
     *         array (
     *           'type' => 'DOMElement',
     *           'name' => 'g',
     *           'id' => 'pt27',
     *           'parent_id' => '23e',
     *           'node_idx' => 1,
     *         ),
     *   ),
     *   'DOMText' =>
     *   array (
     *     2 =>
     *         array (
     *           'type' => 'DOMText',
     *           'name' => NULL,
     *           'id' => NULL,
     *           'parent_id' => 'pt26',
     *           'node_idx' => 0,
     *           'content' => 'what is that',
     *         ),
     *     3 =>
     *         array (
     *           'type' => 'DOMText',
     *           'name' => NULL,
     *           'id' => NULL,
     *           'parent_id' => 'pt27',
     *           'node_idx' => 0,
     *           'content' => '?',
     *         ),
     *   ),
     * )
     * </pre>
     *
     * @param DOMNodeList $elementList
     * @param array       &$srcDomElements
     * @param int         $depth
     * @param null        $parentID
     */
    protected function _mapElements( DOMNodeList $elementList, array &$srcDomElements = array(), $depth = 0, $parentID = null ) {

        $elementsListLen = $elementList->length;

        for ( $i = 0; $i < $elementsListLen; $i++ ) {

            $element = $elementList->item( $i );

            if ( $element instanceof DOMElement ) {

                $elementID = $element->getAttribute( 'id' );

                $plainRef = array(
                        'type'      => 'DOMElement',
                        'name'      => $element->tagName,
                        'id'        => $elementID,
                        'parent_id' => $parentID,
                        'node_idx'  => $i,
                        'innerHTML' => $element->ownerDocument->saveXML( $element )
                );

                //set depth and increment for next occurrence
                $srcDomElements[ 'DOMElement' ][] = $plainRef;

                //count occurrences of this tag name when needed, also transport id reference.
                @$srcDomElements[ $element->tagName ][] = $elementID;

                //reverse Lookup, from id to tag name
                @$srcDomElements[ 'refID' ][ $elementID ] = $element->tagName;

                if ( $element->hasChildNodes() ) {
                    $this->_mapElements( $element->childNodes, $srcDomElements, $depth, $elementID );
                }

            } else {

                $plainRef = array(
                        'type'      => 'DOMText',
                        'name'      => null,
                        'id'        => null,
                        'parent_id' => $parentID,
                        'node_idx'  => $i,
                        'content'   => $elementList->item( $i )->textContent,
                );

                //set depth and increment for next occurrence
                $srcDomElements[ 'DOMText' ][ $depth++ ] = $plainRef;
                //Log::doLog( "Found DOMText in Source " . var_export($plainRef,TRUE) );
            }

            $srcDomElements[ 'elemCount' ]++;

        }
        //Log::doLog($srcDomElements);
    }

    /**
     * Method to reset the target DOM Map
     * when an internal substitution ( Tag ID Realign ) is made
     *
     */
    protected function _resetDOMMaps() {
        $this->srcDomMap = array( 'elemCount' => 0, 'x' => array(), 'bx' => array(), 'ex' => array(), 'g' => array(), 'refID' => array(), 'DOMElement' => array(), 'DOMText' => array() );
        $this->trgDomMap = array( 'elemCount' => 0, 'x' => array(), 'bx' => array(), 'ex' => array(), 'g' => array(), 'refID' => array(), 'DOMElement' => array(), 'DOMText' => array() );
    }

    /**
     * Load an XML String into DOMDocument Object and add a global Error if not valid
     *
     * @param     $xmlString
     * @param int $targetErrorType
     *
     * @return DOMDocument
     */
    protected function _loadDom( $xmlString, $targetErrorType ) {
        libxml_use_internal_errors( true );
        $dom           = new DOMDocument( '1.0', 'utf-8' );
        $trg_xml_valid = @$dom->loadXML( "<root>$xmlString</root>", LIBXML_NOENT );
        if ( $trg_xml_valid === false ) {

            $errorList = libxml_get_errors();
            foreach ( $errorList as $error ) {
                if ( $error->code == 76 /* libxml _xmlerror XML_ERR_TAG_NOT_FINISHED */ ) {
                    if ( preg_match( '#<x[^/>]+>#', $xmlString ) && preg_match( '# x #', $error->message ) ) {
                        $this->_addError( self::ERR_UNCLOSED_X_TAG );
                    }
                }
            }
//            Log::doLog($xmlString);
//            Log::doLog($errorList);

            $this->_addError( $targetErrorType );
        }

        return $dom;
    }

    public function getMalformedXmlStructs() {
        return $this->malformedXmlStructDiff;
    }

    /**
     * Get deep information about xml loading failure for tag mismatch
     *
     */
    protected function _getTagDiff() {

//        Log::doLog( $this->source_seg );
//        Log::doLog( $this->target_seg );

        preg_match_all( '/(<[^\/>]+[\/]{0,1}>)/', $this->source_seg, $matches );
        $malformedXmlSrcStruct = $matches[ 1 ];
        preg_match_all( '/(<[^\/>]+[\/]{0,1}>)/', $this->target_seg, $matches );
        $malformedXmlTrgStruct = $matches[ 1 ];

//        Log::doLog( $malformedXmlSrcStruct );
//        Log::doLog( $malformedXmlTrgStruct );

        //this is for </g>
        preg_match_all( '/(<\/[a-zA-Z]+>)/', $this->source_seg, $matches );
        $_closingSrcTag = $matches[ 1 ];
//        Log::doLog(  $matches );
        preg_match_all( '/(<\/[a-zA-Z]+>)/', $this->target_seg, $matches );
//        Log::doLog(  $matches );
        $_closingTrgTag = $matches[ 1 ];

        $clonedSrc = $malformedXmlSrcStruct;
        $clonedTrg = $malformedXmlTrgStruct;

        foreach ( $malformedXmlTrgStruct as $tag ) {
            if ( ( $pos = array_search( $tag, $clonedSrc ) ) !== false ) {
                unset( $clonedSrc[ $pos ] );
            }
        }

        foreach ( $malformedXmlSrcStruct as $tag ) {
            if ( ( $pos = array_search( $tag, $clonedTrg ) ) !== false ) {
                unset( $clonedTrg[ $pos ] );
            }
        }

        $clonedClosingSrc = $_closingSrcTag;
        $clonedClosingTrg = $_closingTrgTag;
        foreach ( $_closingTrgTag as $tag ) {
            if ( ( $pos = array_search( $tag, $clonedClosingSrc ) ) !== false ) {
                unset( $clonedClosingSrc[ $pos ] );
            }
        }

        foreach ( $_closingSrcTag as $tag ) {
            if ( ( $pos = array_search( $tag, $clonedClosingTrg ) ) !== false ) {
                unset( $clonedClosingTrg[ $pos ] );
            }
        }

        $totalResult = array(
                'source' => array_merge( $clonedSrc, $clonedClosingSrc ),
                'target' => array_merge( $clonedTrg, $clonedClosingTrg ),
        );

//        Log::doLog($totalResult);

        $this->malformedXmlStructDiff = $totalResult;

    }

    /**
     * Perform a replacement of all non-breaking spaces with a simple space char
     *
     * manage the consistency of non breaking spaces,
     * chars coming, expecially,from MS Word
     * @link https://en.wikipedia.org/wiki/Non-breaking_space Wikipedia
     *
     * @param string $s Source String to normalize
     *
     * @return string Normalized
     */
    protected function _nbspToSpace( $s ) {
        return preg_replace( "/\x{a0}/u", chr( 0x20 ), $s );
    }

    /**
     * Perform a replacement of all simple space chars with non-breaking spaces
     *
     * @param string $s
     *
     * @return string
     */
    protected function _spaceToNonBreakingSpace( $s ) {
        return preg_replace( "/\x{20}/u", chr( 0xa0 ), $s );
    }

    /**
     * Perform all integrity check and comparisons on source and target string
     *
     * @return errObject[]
     *
     */
    public function performConsistencyCheck() {

        try {
            list( $srcNodeList, $trgNodeList ) = $this->_prepareDOMStructures();
        } catch ( DOMException $ex ) {
            return $this->getErrors();
        }

        $this->_checkTagsBoundary();
        $this->_checkContentConsistency( $srcNodeList, $trgNodeList );
        $this->_checkTagPositions();
        $this->_checkNewLineConsistency();
        $this->_checkSymbolConsistency();

        // all checks completed
        return $this->getErrors();

    }

    /**
     * Perform integrity check only for tag mismatch
     *
     * @return errObject[]
     */
    public function performTagCheckOnly() {

        try {
            list( $srcNodeList, $trgNodeList ) = $this->_prepareDOMStructures();
        } catch ( DOMException $ex ) {
            return $this->getErrors();
        }

        $this->_checkTagMismatch();

        // all checks completed
        return $this->getErrors();

    }

    public function getTargetTagPositionError() {
        return $this->tagPositionError;
    }

    /**
     * Check for errors in tag position
     *
     */
    protected function _checkTagPositions() {

//        Log::doLog( $this->source_seg );
//        Log::doLog( $this->target_seg );

        preg_match_all( '/(<([^\/>]+)[\/]{0,1}>|<\/([a-zA-Z]+)>)/', $this->source_seg, $matches );
        $complete_malformedSrcStruct   = $matches[ 1 ];
        $open_malformedXmlSrcStruct    = $matches[ 2 ];
        $closing_malformedXmlSrcStruct = $matches[ 3 ];

        preg_match_all( '/(<([^\/>]+)[\/]{0,1}>|<\/([a-zA-Z]+)>)/', $this->target_seg, $matches );
        $complete_malformedTrgStruct   = $matches[ 1 ];
        $open_malformedXmlTrgStruct    = $matches[ 2 ];
        $closing_malformedXmlTrgStruct = $matches[ 3 ];

//        Log::doLog($complete_malformedSrcStruct);
//        Log::doLog($open_malformedXmlSrcStruct);
//        Log::doLog($closing_malformedXmlSrcStruct);
//
//        Log::doLog($complete_malformedTrgStruct);
//        Log::doLog($open_malformedXmlTrgStruct);
//        Log::doLog($closing_malformedXmlTrgStruct);

        foreach ( $open_malformedXmlTrgStruct as $pos => $tag ) {
            if ( trim( $open_malformedXmlSrcStruct[ $pos ] ) != trim( $tag ) ) {
                $this->_addError( self::ERR_TAG_ORDER );
                $this->tagPositionError[] = $complete_malformedTrgStruct[ $pos ];

                return;
            }
        }

        foreach ( $closing_malformedXmlTrgStruct as $pos => $tag ) {
            if ( trim( $closing_malformedXmlSrcStruct[ $pos ] ) != trim( $tag ) ) {
                $this->_addError( self::ERR_TAG_ORDER );
                $this->tagPositionError[] = $complete_malformedTrgStruct[ $pos ];

                return;
            }
        }

        /*
         * Check for corresponding self closing tags like <g id="pt673"/>
         */
        preg_match_all( '#<([^>]+)/>#', $this->source_seg, $selfClosingTags_src );
        preg_match_all( '#<([^>]+)/>#', $this->target_seg, $selfClosingTags_trg );
        $selfClosingTags_src = $selfClosingTags_src[ 1 ];
        $selfClosingTags_trg = $selfClosingTags_trg[ 1 ];
        foreach ( $selfClosingTags_trg as $pos => $tag ) {
            if ( trim( $selfClosingTags_src[ $pos ] ) != trim( $tag ) ) {
                $this->_addError( self::ERR_TAG_MISMATCH );
                $this->tagPositionError[] = $selfClosingTags_trg[ $pos ];

                return;
            }
        }

    }

    /**
     * Performs a check for differences on first and last tags boundaries
     * All withespaces, tabs, carriage return, new lines between tags are checked
     *
     */
    protected function _checkTagsBoundary() {

        //perform first char Line check if tags are not presents
        preg_match_all( '#^[\s\t\x{a0}\r\n]+[^<]+#u', $this->source_seg, $source_tags );
        preg_match_all( '#^[\s\t\x{a0}\r\n]+[^<]+#u', $this->target_seg, $target_tags );
        $source_tags = $source_tags[ 0 ];
        $target_tags = $target_tags[ 0 ];
        if ( count( $source_tags ) != count( $target_tags ) ) {
            $num = abs( count( $source_tags ) - count( $target_tags ) );
            for ( $i = 0; $i < $num; $i++ ) {
                $this->_addError( self::ERR_WS_HEAD );
            }
        }

        //get all special chars ( and spaces ) before a tag or after a closing g tag
        //</g> ...
        // <g ... >
        // <x ... />
        preg_match_all( '#</g>[\s\t\x{a0}\r\n]+|[\s\t\x{a0}\r\n]+<(?:x[^>]+|[^/>]+)>#u', rtrim( $this->source_seg ), $source_tags );
        preg_match_all( '#</g>[\s\t\x{a0}\r\n]+|[\s\t\x{a0}\r\n]+<(?:x[^>]+|[^/>]+)>#u', rtrim( $this->target_seg ), $target_tags );
//        preg_match_all('#[\s\t\x{a0}\r\n]+<(?:x[^>]+|[^/>]+)>#u', rtrim($this->source_seg), $source_tags);
//        preg_match_all('#[\s\t\x{a0}\r\n]+<(?:x[^>]+|[^/>]+)>#u', rtrim($this->target_seg), $target_tags);
        $source_tags = $source_tags[ 0 ];
        $target_tags = $target_tags[ 0 ];
        if ( count( $source_tags ) != count( $target_tags ) ) {
            $num = abs( count( $source_tags ) - count( $target_tags ) );
//            Log::doLog($source_tags);
//            Log::doLog($target_tags);
//            Log::hexDump($this->source_seg);
//            Log::hexDump($this->target_seg);
            for ( $i = 0; $i < $num; $i++ ) {
                $this->_addError( self::ERR_BOUNDARY_HEAD_TEXT );
            }
        }

        //get All special chars between G TAGS before first char occurrence
        //</g> nnn<g ...>
        preg_match_all( '#</[^>]+>[\s\t\x{a0}\r\n]+.*<[^/>]+>#u', $this->source_seg, $source_tags );
        preg_match_all( '#</[^>]+>[\s\t\x{a0}\r\n]+.*<[^/>]+>#u', $this->target_seg, $target_tags );
        $source_tags = $source_tags[ 0 ];
        $target_tags = $target_tags[ 0 ];
        if ( ( count( $source_tags ) != count( $target_tags ) ) ) {
            $num = abs( count( $source_tags ) - count( $target_tags ) );

//            Log::doLog($this->source_seg);
//            Log::doLog($this->target_seg);
//            Log::hexDump($this->source_seg);
//            Log::hexDump($this->target_seg);
//            Log::doLog($source_tags);
//            Log::doLog($target_tags);

            for ( $i = 0; $i < $num; $i++ ) {
                $this->_addError( self::ERR_BOUNDARY_HEAD );
            }
        }

        //get All special chars after LAST tag at the end of line if there are
        preg_match_all( '/<[^>]+>[\s\t\x{a0}\r\n]+$/u', $this->source_seg, $source_tags );
        preg_match_all( '/<[^>]+>[\s\t\x{a0}\r\n]+$/u', $this->target_seg, $target_tags );
        $source_tags = $source_tags[ 0 ];
        $target_tags = $target_tags[ 0 ];

        //so, if we found a last char mismatch, and if it is in the source: add to the target else trim it
        if ( ( count( $source_tags ) != count( $target_tags ) ) && !empty( $source_tags ) ) {

            //Append a space to target for normalization.
            $this->target_seg .= " ";

            //Suppress Warning
            //$this->_addError(self::ERR_BOUNDARY_TAIL);

        } else {
            $this->target_seg = rtrim( $this->target_seg );
        }

        $this->trgDom = $this->_loadDom( $this->target_seg, self::ERR_TARGET );
        //Save normalized dom Element
        $this->normalizedTrgDOM = clone $this->trgDom;

    }

    //
    /**
     * Try to perform an heuristic re-align of tags id by position.
     *
     * Two XML strings are analyzed. They must have the same number of tags,
     * the same number of tag G, the same number of tag X.
     *
     * After realignment a Tag consistency check is performed ( QA::performTagCheckOnly() )
     * if no errors where found the dom is reloaded and tags map are updated.
     *
     * @return errObject[]|null
     */
    public function tryRealignTagID() {

        try {
            $this->_prepareDOMStructures();
        } catch ( DOMException $ex ) {
            Log::doLog( "tryRealignTagID: " . $ex->getMessage() );

            return $this->getErrors();
        }

        $targetNumDiff = count( $this->trgDomMap[ 'DOMElement' ] ) - count( $this->srcDomMap[ 'DOMElement' ] );
        $diffTagG      = count( @$this->trgDomMap[ 'g' ] ) - count( @$this->srcDomMap[ 'g' ] );
        $diffTagX      = count( @$this->trgDomMap[ 'x' ] ) - count( @$this->srcDomMap[ 'x' ] );
		$diffTagBX     = count(@$this->trgDomMap['bx']) - count(@$this->srcDomMap['bx']);
		$diffTagEX     = count(@$this->trgDomMap['ex']) - count(@$this->srcDomMap['ex']);

        //there are the same number of tags in source and target
        if ( $targetNumDiff == 0 && !empty( $this->srcDomMap[ 'refID' ] ) ) {

            //if tags are in exact number
            if( $diffTagG == 0 && $diffTagX == 0 && $diffTagBX == 0 && $diffTagEX == 0 ){

                //Steps:

                //- re-align ids
                foreach ( $this->trgDomMap[ 'g' ] as $pos => $tagID ) {
                    $pattern[]     = '|<g id ?= ?["\']{1}(' . $tagID . ')["\']{1} ?>|ui';
                    $replacement[] = '<g id="###' . $this->srcDomMap[ 'g' ][ $pos ] . '###">';
                }

                foreach ( $this->trgDomMap[ 'x' ] as $pos => $tagID ) {
                    $pattern[]     = '|<x id ?= ?["\']{1}(' . $tagID . ')["\']{1} ?/>|ui';
                    $replacement[] = '<x id="###' . $this->srcDomMap[ 'x' ][ $pos ] . '###" />';
                }

				foreach( $this->trgDomMap['bx'] as $pos => $tagID ){
					$pattern[] = '|<bx id ?= ?["\']{1}(' . $tagID . ')["\']{1} ?/>|ui';
					$replacement[] = '<bx id="###' . $this->srcDomMap['bx'][$pos] . '###" />';
				}

				foreach( $this->trgDomMap['ex'] as $pos => $tagID ){
					$pattern[] = '|<ex id ?= ?["\']{1}(' . $tagID . ')["\']{1} ?/>|ui';
					$replacement[] = '<ex id="###' . $this->srcDomMap['ex'][$pos] . '###" />';
				}

                $result = preg_replace( $pattern, $replacement, $this->target_seg, 1 );

                $result = str_replace( "###", "", $result );

                //- re-import in the dom target after regular expression
                //- perform check again ( recursive over the entire class )
                $qaCheck = new self( $this->source_seg, $result );
                $qaCheck->performTagCheckOnly();
                if ( !$qaCheck->thereAreErrors() ) {
                    $this->target_seg = $result;
                    $this->trgDom     = $this->_loadDom( $result, self::ERR_TARGET );
                    $this->_resetDOMMaps();
                    $this->_prepareDOMStructures();

                    return; //ALL RIGHT
                }

//                Log::doLog($result);
//                Log::doLog($pattern);
//                Log::doLog($replacement);

            }

        } else if ( $targetNumDiff < 0 ) { // the target has fewer tags than source

        } else { // the target has more tags than source

        }

        $this->_addError( self::ERR_COUNT );

    }

    /**
     * This method checks for tag mismatch,
     * it analyzes the tag count number ( by srcDomMap contents )
     * and check for id correspondence.
     *
     */
    protected function _checkTagMismatch() {

        $targetNumDiff = $this->_checkTagCountMismatch( count( $this->srcDomMap[ 'DOMElement' ] ), count( $this->trgDomMap[ 'DOMElement' ] ) );
        if ( $targetNumDiff == 0 ) {
            $deepDiffTagG = $this->_checkTagCountMismatch( count( @$this->srcDomMap[ 'g' ] ), count( @$this->trgDomMap[ 'g' ] ) );
        }

        //check for Tag ID MISMATCH
        $diffArray = array_diff_assoc( $this->srcDomMap[ 'refID' ], $this->trgDomMap[ 'refID' ] );
        if ( !empty( $diffArray ) && !empty( $this->trgDomMap[ 'DOMElement' ] ) ) {
            $this->_addError( self::ERR_TAG_ID );
//            Log::doLog($diffArray);
        }

    }

    /**
     * Wrapper
     *
     * Perform all consistency contents check Internally
     *
     * @param DOMNodeList $srcNodeList
     * @param DOMNodeList $trgNodeList
     */
    protected function _checkContentConsistency( DOMNodeList $srcNodeList, DOMNodeList $trgNodeList ) {

        $this->_checkTagMismatch( $srcNodeList, $trgNodeList );

        if ( $this->thereAreErrors() ) {
            $this->_getTagDiff();
        }

        //* Fix error undefined variable trgTagReference when source target contains tags and target not
        $trgTagReference = array( 'node_idx' => null );

        foreach ( $this->srcDomMap[ 'DOMElement' ] as $srcTagReference ) {

            if( $srcTagReference['name'] == 'x' || $srcTagReference['name'] == 'bx' || $srcTagReference['name'] == 'ex' ){
                continue;
            }

            if ( !is_null( $srcTagReference[ 'parent_id' ] ) ) {

                $srcNode        = $this->_queryDOMElement( $this->srcDom, $srcTagReference );
                $srcNodeContent = $srcNode->textContent;

                foreach ( $this->trgDomMap[ 'DOMElement' ] as $k => $elements ) {
                    if ( $elements[ 'id' ] == $srcTagReference[ 'id' ] ) {
                        $trgTagReference = $this->trgDomMap[ 'DOMElement' ][ $k ];
                    }
                }

                $trgNode        = $this->_queryDOMElement( $this->trgDom, $trgTagReference );
                $trgNodeContent = $trgNode->textContent;

            } else {

                $srcNode        = $srcNodeList->item( $srcTagReference[ 'node_idx' ] );
                $srcNodeContent = $srcNode->textContent;

                foreach ( $this->trgDomMap[ 'DOMElement' ] as $k => $elements ) {
                    if ( $elements[ 'id' ] == $srcTagReference[ 'id' ] ) {
                        $trgTagReference = $this->trgDomMap[ 'DOMElement' ][ $k ];
                    }
                }

                $trgTagPos      = $trgTagReference[ 'node_idx' ];
                $trgNode        = $trgNodeList->item( $trgTagPos );
                $trgNodeContent = $trgNode->textContent;

            }

            /**
             * Skip double check for first whitespace if there are child nodes.
             * Since this check is performed over ALL elements ( parent and childes )
             * Avoid to count 2 times a first space for nodeValue when nested
             *
             * @See     : http://www.php.net/manual/en/class.domnode.php#domnode.props.nodevalue
             *
             * nodeValue
             *   The value of this node, depending on its type
             *
             * @See     : http://www.php.net/manual/en/class.domnode.php#domnode.props.textcontent
             * textContent
             *   This attribute returns the text content of this node and its descendants.
             *
             * @example '<g id="pt231"><g id="pt232"> ELSA AND JOY'S APARTMENT </g></g>'
             * <code>
             *
             * // The space before ELSA was checked two times because:
             *
             *  ( DOMElement id pt231 )->nodeValue == ( DOMElement id pt232 )->nodeValue
             *
             * </code>
             *
             */
            $domSrcNodeString = $srcNode->ownerDocument->saveXML( $srcNode );
            if ( !preg_match( '/^<g[^>]+></', $domSrcNodeString ) ) {
                $this->_checkHeadWhiteSpaces( $srcNodeContent, $trgNodeContent, $trgTagReference );
            }

            $this->_checkTailWhiteSpaces( $srcNodeContent, $trgNodeContent, $trgTagReference );
            $this->_checkHeadTabs( $srcNodeContent, $trgNodeContent );
            $this->_checkTailTabs( $srcNodeContent, $trgNodeContent );
            $this->_checkHeadCRNL( $srcNodeContent, $trgNodeContent );
            $this->_checkTailCRNL( $srcNodeContent, $trgNodeContent );
        }

    }

    /**
     * Find in a DOMDocument an Element by its Reference
     *
     * @param DOMDocument $domDoc
     * @param             $TagReference
     *
     * @return DOMNode
     */
    protected function _queryDOMElement( DOMDocument $domDoc, $TagReference ) {

        //Old implementation
//        $availableParentList = $domDoc->getElementsByTagName( $TagReference[ 'name' ] );
//        $availableParentsLen = $availableParentList->length;
//
//        for ( $i = 0; $i < $availableParentsLen; $i++ ) {
//
//            $element = $availableParentList->item( $i );
//            if ( $element->getAttribute( 'id' ) == $TagReference[ 'id' ] ) {
//
//                /**
//                 * @var DOMElement $Node
//                 */
//                $Node = $element;
//
//                Log::doLog( 'Found: ' . $availableParentList->item($i)->textContent );
//            }
//        }

        $xpath = new DOMXPath( $domDoc );
        $query = '//*[@id="' . $TagReference[ 'id' ] . '"]';

        $Node = $xpath->query( $query );

        return ( ( $Node->length == 0 || $Node == false ) ? new DOMNode( 'g' ) : $Node->item( 0 ) );

    }

    /**
     * Check for number of tags in NodeList of Segment
     *
     * @param int $srcNodeCount
     * @param int $trgNodeCount
     *
     * @return int
     */
    protected function _checkTagCountMismatch( $srcNodeCount, $trgNodeCount ) {
        if ( $srcNodeCount != $trgNodeCount ) {
            $this->_addError( self::ERR_COUNT );
        }

        return $trgNodeCount - $srcNodeCount;
    }

    /**
     * Search for head whitespaces ( comparison of strings )
     *
     * @param $srcNodeContent
     * @param $trgNodeContent
     * @param $trgTagReference
     */
    protected function _checkHeadWhiteSpaces( $srcNodeContent, $trgNodeContent, $trgTagReference ) {

        //backup and check start string
        $_srcNodeContent = $srcNodeContent;
        $_trgNodeContent = $trgNodeContent; //not Used

//                Log::doLog($srcNodeContent);
//                Log::doLog($trgNodeContent);


        $srcHasHeadNBSP = $this->_hasHeadNBSP( $srcNodeContent );
        $trgHasHeadNBSP = $this->_hasHeadNBSP( $trgNodeContent );

        //normalize spaces
        $srcNodeContent = $this->_nbspToSpace( $srcNodeContent );
        $trgNodeContent = $this->_nbspToSpace( $trgNodeContent );

        $headSrcWhiteSpaces = mb_stripos( $srcNodeContent, " ", 0, 'utf-8' );
        $headTrgWhiteSpaces = mb_stripos( $trgNodeContent, " ", 0, 'utf-8' );

        //if source or target has a space at beginning and their relative positions are different
        if ( ( $headSrcWhiteSpaces === 0 || $headTrgWhiteSpaces === 0 ) && $headSrcWhiteSpaces !== $headTrgWhiteSpaces ) {
            $this->_addError( self::ERR_WS_HEAD );
        }

//        //normalize the target first space according to the source type
//        if( $srcHasHeadNBSP != $trgHasHeadNBSP && !$this->thereAreErrors() ){
//
//            //get the string from normalized string
//            if( is_null($trgTagReference['parent_id']) ){
//                //get the string from normalized string
//                $_nodeNormalized = $this->normalizedTrgDOMNodeList->item( $trgTagReference['node_idx'] );
//                $_trgNodeContent = $_nodeNormalized->nodeValue;
//
//            } else {
//
//                $_nodeNormalized = $this->_queryDOMElement( $this->normalizedTrgDOM, $trgTagReference );
//                $_trgNodeContent = $_nodeNormalized->nodeValue;
//
//            }
//
//            if( $srcHasHeadNBSP ) {
//                $_trgNodeContent = preg_replace( "/^\x{20}{1}/u", Utils::unicode2chr(0Xa0), $_trgNodeContent );
//            } else {
//                $_trgNodeContent = preg_replace( "/^\x{a0}{1}/u", Utils::unicode2chr(0X20), $_trgNodeContent );
//            }
//            $_nodeNormalized->nodeValue = $_trgNodeContent;
//
//            $xpath = new DOMXPath( $this->normalizedTrgDOM );
//            $query = '//*[@id="' . $trgTagReference['id'] . '"]';
//
//            $node = $xpath->query($query);
//
//            foreach( $node as $n ){
//                //only a parent node can replace it's child
//                $n->parentNode->replaceChild( $this->normalizedTrgDOM->importNode( $_nodeNormalized, true ), $n );
//            }
//
//        }

    }

    /**
     * Search for trailing whitespaces ( comparison of strings )
     *
     * @param $srcNodeContent
     * @param $trgNodeContent
     * @param $trgTagReference
     */
    protected function _checkTailWhiteSpaces( $srcNodeContent, $trgNodeContent, $trgTagReference ) {

        //backup and check start string
        $_srcNodeContent = $srcNodeContent;
        $_trgNodeContent = $trgNodeContent; //not used

        $srcHasTailNBSP = $this->_hasTailNBSP( $srcNodeContent );
        $trgHasTailNBSP = $this->_hasTailNBSP( $trgNodeContent );

        //normalize spaces
        $srcNodeContent = $this->_nbspToSpace( $srcNodeContent );
        $trgNodeContent = $this->_nbspToSpace( $trgNodeContent );

        $srcLen = mb_strlen( $srcNodeContent );
        $trgLen = mb_strlen( $trgNodeContent );

        $trailingSrcChar = mb_substr( $srcNodeContent, $srcLen - 1, 1, 'utf-8' );
        $trailingTrgChar = mb_substr( $trgNodeContent, $trgLen - 1, 1, 'utf-8' );
        if ( ( $trailingSrcChar == " " || $trailingTrgChar == " " ) && $trailingSrcChar != $trailingTrgChar ) {
            $this->_addError( self::ERR_WS_TAIL );
        }

//        //add another check for nested tag with ending spaces
//        $trailingSrcChar = mb_substr($srcNodeContent, $srcLen - 2, 2, 'utf-8');
//        $trailingTrgChar = mb_substr($trgNodeContent, $trgLen - 2, 2, 'utf-8');
//        Log::doLog('"'.$srcNodeContent.'"');
//        Log::doLog('"'.$trgNodeContent.'"');
//        Log::doLog('"'.$trailingSrcChar.'"');
//        Log::doLog('"'.$trailingTrgChar.'"');
//        if ( ( $trailingSrcChar == "  " || $trailingTrgChar == "  " ) && $trailingSrcChar != $trailingTrgChar) {
//            $this->_addError(self::ERR_WS_TAIL);
//        }

        //normalize the target first space according to the source type
//    	if( $srcHasTailNBSP != $trgHasTailNBSP && !$this->thereAreErrors() ){
//
//            //get the string from normalized string
//            if( is_null($trgTagReference['parent_id']) ){
//                //get the string from normalized string
//                $_nodeNormalized = $this->normalizedTrgDOMNodeList->item( $trgTagReference['node_idx'] );
//                $_trgNodeContent = $_nodeNormalized->nodeValue;
//
//            } else {
//
//                $_nodeNormalized = $this->_queryDOMElement( $this->normalizedTrgDOM, $trgTagReference );
//                $_trgNodeContent = $_nodeNormalized->nodeValue;
//
//            }
//
//    		if( $srcHasTailNBSP ) {
//    			$_trgNodeContent = preg_replace( "/\x{20}{1}$/u", Utils::unicode2chr(0Xa0), $_trgNodeContent );
//    		} else {
//    			$_trgNodeContent = preg_replace( "/\x{a0}{1}$/u", Utils::unicode2chr(0X20), $_trgNodeContent );
//    		}
//
//            $_nodeNormalized->nodeValue = $_trgNodeContent;
//
//            $xpath = new DOMXPath( $this->normalizedTrgDOM );
//            $query = '//*[@id="' . $trgTagReference['id'] . '"]';
//
//            $node = $xpath->query($query);
//
//            foreach( $node as $n ){
//                //only a parent node can replace it's child
//                $n->parentNode->replaceChild( $this->normalizedTrgDOM->importNode( $_nodeNormalized, true ), $n );
//            }
//
//        }

    }

    /**
     * Check if head character is a non-breaking space
     *
     * @param string $s
     *
     * @return bool
     */
    protected function _hasHeadNBSP( $s ) {
        return preg_match( "/^\x{a0}{1}/u", $s );
    }

    /**
     * Check if tail character is a non-breaking space
     *
     * @param string $s
     *
     * @return bool
     */
    protected function _hasTailNBSP( $s ) {
        return preg_match( "/\x{a0}{1}$/u", $s );
    }

    /**
     * Return the target html string normalized in head and tail spaces according to Source
     *
     * @return string
     * @throws LogicException
     */
    public function getTrgNormalized() {

        if ( !$this->thereAreErrors() ) {
            //IMPORTANT NOTE :
            //SEE http://www.php.net/manual/en/domdocument.savexml.php#88525
            preg_match( '/<root>(.*)<\/root>/us', $this->normalizedTrgDOM->saveXML( $this->normalizedTrgDOM->documentElement ), $matches );

//            try {
//                throw new Exception();
//            } catch ( Exception $e ){
//                Log::doLog( "\n" . $this->trgDom->saveXML() );
//                Log::doLog( $e->getTraceAsString() . "\n\n");
//            }

            /**
             * Why i do this?? I'm replacing Placeholders of non printable chars
             * this because DomDocument can't handle non printable chars
             * @see __construct
             */
            preg_match_all( self::$regexpPlaceHoldAscii, $matches[ 1 ], $matches_trg );
            if ( !empty( $matches_trg[ 1 ] ) ) {

                foreach ( $matches_trg[ 1 ] as $v ) {
                    $matches[ 1 ] = preg_replace( '/##\$_(' . $v . '{1})\$##/u', '&#x' . $v . ';', $matches[ 1 ], 1 );
                }
//                Log::hexDump($matches[1]);
            }

            //Substitute 4(+)-byte characters from a UTF-8 string to htmlentities
            $matches[ 1 ] = preg_replace_callback( '/([\xF0-\xF7]...)/s', 'CatUtils::htmlentitiesFromUnicode', $matches[ 1 ] );

            /*
             * BUG on windows Paths: C:\\Users\\user\\Downloads\\File per field test\\1\\gui_plancompression.html
             * return stripslashes( $matches[1] );
             */

            return $matches[ 1 ];
        }

        throw new LogicException( __METHOD__ . " call when errors found in Source/Target integrity check & comparison." );
    }

    /**
     * Check for tabs differences in head part of string content
     *
     * @param string $srcNodeContent
     * @param string $trgNodeContent
     *
     */
    protected function _checkHeadTabs( $srcNodeContent, $trgNodeContent ) {
        $headSrcTabs = mb_stripos( $srcNodeContent, "\t", 0, 'utf-8' );
        $headTrgTabs = mb_stripos( $trgNodeContent, "\t", 0, 'utf-8' );
        if ( ( $headSrcTabs === 0 || $headTrgTabs === 0 ) && $headSrcTabs !== $headTrgTabs ) {
            $this->_addError( self::ERR_TAB_HEAD );
        }
    }

    /**
     * Search for trailing tabs ( comparison of strings )
     *
     * @param string $srcNodeContent
     * @param string $trgNodeContent
     */
    protected function _checkTailTabs( $srcNodeContent, $trgNodeContent ) {

        $srcLen = mb_strlen( $srcNodeContent );
        $trgLen = mb_strlen( $trgNodeContent );

        $trailingSrcChar = mb_substr( $srcNodeContent, $srcLen - 1, 1, 'utf-8' );
        $trailingTrgChar = mb_substr( $trgNodeContent, $trgLen - 1, 1, 'utf-8' );
        if ( ( $trailingSrcChar == "\t" || $trailingTrgChar == "\t" ) && $trailingSrcChar != $trailingTrgChar ) {
            $this->_addError( self::ERR_TAB_TAIL );
        }

    }

    /**
     * Check for new line/carriage return differences in head part of string content
     *
     * @param string $srcNodeContent
     * @param string $trgNodeContent
     *
     */
    protected function _checkHeadCRNL( $srcNodeContent, $trgNodeContent ) {

        $headSrcCRNL = mb_split( '^[\r\n]+', $srcNodeContent );
        $headTrgCRNL = mb_split( '^[\r\n]+', $trgNodeContent );
        if ( ( count( $headSrcCRNL ) > 1 || count( $headTrgCRNL ) > 1 ) && $headSrcCRNL[ 0 ] !== $headTrgCRNL[ 0 ] ) {
            $this->_addError( self::ERR_CR_HEAD );
        }

    }


    /**
     * Check for new line/carriage return differences in tail part of string content
     *
     * @param string $srcNodeContent
     * @param string $trgNodeContent
     *
     */
    protected function _checkTailCRNL( $srcNodeContent, $trgNodeContent ) {

        $headSrcCRNL = mb_split( '[\r\n]+$', $srcNodeContent );
        $headTrgCRNL = mb_split( '^[\r\n]+$', $trgNodeContent );
        if ( ( count( $headSrcCRNL ) > 1 || count( $headTrgCRNL ) > 1 ) && end( $headSrcCRNL ) !== end( $headTrgCRNL ) ) {
            $this->_addError( self::ERR_CR_TAIL );
        }

    }

    protected function _checkNewLineConsistency() {
        $nrOfNewLinesInSource = mb_substr_count( $this->source_seg, self::$asciiPlaceHoldMap[ '0A' ][ 'placeHold' ] );
        $nrOfNewLinesInTarget = mb_substr_count( $this->target_seg, self::$asciiPlaceHoldMap[ '0A' ][ 'placeHold' ] );

        for ( $i = 0; $i < abs( $nrOfNewLinesInSource - $nrOfNewLinesInTarget ); $i++ ) {
            $this->_addError( self::ERR_NEWLINE_MISMATCH );
        }
    }

    protected function _checkSymbolConsistency() {

        $symbols = array(
                '€', '@', '&amp;', '£', '%', '=', self::$asciiPlaceHoldMap[ '09' ][ 'placeHold' ], '*'
        );

        $specialSymbols = array( '$', '#' );

        foreach ( $symbols as $sym ) {

            $symbolOccurrencesInSource = mb_substr_count( strip_tags( $this->source_seg ), $sym );
            $symbolOccurrencesInTarget = mb_substr_count( strip_tags( $this->target_seg ), $sym );

            for ( $i = 0; $i < abs( $symbolOccurrencesInSource - $symbolOccurrencesInTarget ); $i++ ) {

                switch ( $sym ) {
		    case '€':
                        $this->_addError( self::ERR_EUROSIGN_MISMATCH );
                        break;
                    case '@':
                        $this->_addError( self::ERR_AT_MISMATCH );
                        break;
                    case '&amp;':
                        $this->_addError( self::ERR_AMPERSAND_MISMATCH );
                        break;
                    case '£':
                        $this->_addError( self::ERR_POUNDSIGN_MISMATCH );
                        break;
                    case '%':
                        $this->_addError( self::ERR_PERCENT_MISMATCH );
                        break;
                    case '=':
                        $this->_addError( self::ERR_EQUALSIGN_MISMATCH );
                        break;
                    case self::$asciiPlaceHoldMap[ '09' ][ 'placeHold' ]:
                        $this->_addError( self::ERR_TAB_MISMATCH );
                        break;
                    case '*':
                        $this->_addError( self::ERR_STARSIGN_MISMATCH );
                        break;
                }

            }

        }

        //remove placeholders and symbols from source and target and search for special symbols mismatch
        $cleaned_source = str_replace( $symbols, "", $this->source_seg );
        $cleaned_target = str_replace( $symbols, "", $this->target_seg );

        $cleaned_source = preg_replace( '/##\$_..\$##/', "", $cleaned_source );
        $cleaned_target = preg_replace( '/##\$_..\$##/', "", $cleaned_target );

        foreach ( $specialSymbols as $sym ) {
            $symbolOccurrencesInSource = mb_substr_count( $cleaned_source, $sym );
            $symbolOccurrencesInTarget = mb_substr_count( $cleaned_target, $sym );

            for ( $i = 0; $i < abs( $symbolOccurrencesInSource - $symbolOccurrencesInTarget ); $i++ ) {
                $this->_addError( self::ERR_SPECIAL_ENTITY_MISMATCH );
            }
        }

    }

}

?>
