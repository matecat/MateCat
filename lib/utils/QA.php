<?php include_once INIT::$UTILS_ROOT . '/log.class.php';

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

    /**
     * Static instance constructor
     *
     * @param array $errors
     * @return errObject
     */
    public static function get( array $errors ) {
        $errObj = new self();
        $errObj->outcome = $errors['outcome'];
        $errObj->debug = $errors['debug'];
        return $errObj;
    }

    /**
     * Return string id
     * @return string
     */
    public function __toString(){
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

    const ERR_NONE               = 0;
    const ERR_COUNT              = 1;
    const ERR_SOURCE             = 2;
    const ERR_TARGET             = 3;
    const ERR_TAG_ID             = 4;
    const ERR_WS_HEAD            = 5;
    const ERR_WS_TAIL            = 6;
    const ERR_TAB_HEAD           = 7;
    const ERR_TAB_TAIL           = 8;
    const ERR_CR_HEAD            = 9;
    const ERR_CR_TAIL            = 10;
    const ERR_BOUNDARY_HEAD      = 11;
    const ERR_BOUNDARY_TAIL      = 12;
    const ERR_UNCLOSED_X_TAG     = 13;
    const ERR_BOUNDARY_HEAD_TEXT = 14;

    const ERR_TAG_MISMATCH       = 1000;

    const ERR_SPACE_MISMATCH     = 1100;

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
     * );
     * </pre>
     * @var array(string) 
     */
    protected $_errorMap = array(
        0 =>  '',
        1 =>  'Tag count mismatch',
        2 =>  'bad source xml',
        3 =>  'bad target xml',
        4 =>  'Tag ID mismatch: Check and edit tags with differing IDs.',
        5 =>  'Heading whitespaces mismatch',
        6 =>  'Tail whitespaces mismatch',
        7 =>  'Heading tab mismatch',
        8 =>  'Tail tab mismatch',
        9 =>  'Heading carriage return mismatch',
        10 => 'Tail carriage return mismatch',
        11 => 'Char mismatch between tags',
        12 => 'End line char mismatch',
        13 => 'Wrong format for x tag. Should be < x .... />',
        14 => 'Char mismatch before a tag',

        /*
         * grouping
         *  1 =>  'Tag count mismatch',
         *  2 =>  'bad source xml',
         *  3 =>  'bad target xml',
         */
        1000 => 'Tag mismatch',

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
    );
    
    /**
     * List of Errors from  check analysis
     * 
     * @var array(errObject(number:string))
     */
    protected $exceptionList = array();

    /**
     * List of warnings from check analysis
     *
     * @var array(errObject(number,string))
     */
    protected $warningList = array();

    /**
     * Add an error to error List.
     * Internal CodeMap
     * 
     * @param int $errCode
     */
    protected function _addError($errCode) {

        //Real error Code log
        Log::doLog( $errCode . " :: " . $this->_errorMap[$errCode]);

        switch( $errCode ) {
            case self::ERR_COUNT:
            case self::ERR_SOURCE:
            case self::ERR_TARGET:
                $this->exceptionList[] = errObject::get( array( 'outcome' => self::ERR_TAG_MISMATCH, 'debug' => $this->_errorMap[self::ERR_TAG_MISMATCH] ) );
            break;
            case self::ERR_TAG_ID:
            	$this->exceptionList[] = errObject::get( array( 'outcome' => self::ERR_TAG_ID, 'debug' => $this->_errorMap[self::ERR_TAG_ID] ) );
            break;
            case self::ERR_UNCLOSED_X_TAG:
                $this->exceptionList[] = errObject::get( array( 'outcome' => $errCode, 'debug' => $this->_errorMap[$errCode] ) );
            break;

            case self::ERR_WS_HEAD:
            case self::ERR_WS_TAIL:
            case self::ERR_TAB_HEAD:
            case self::ERR_TAB_TAIL:
            case self::ERR_BOUNDARY_HEAD:
            case self::ERR_BOUNDARY_TAIL:
            case self::ERR_BOUNDARY_HEAD_TEXT:
                $this->warningList[] = errObject::get( array( 'outcome' => self::ERR_SPACE_MISMATCH, 'debug' => $this->_errorMap[self::ERR_SPACE_MISMATCH] ) );
            break;
            default:
                $this->warningList[] = errObject::get( array( 'outcome' => $errCode, 'debug' => $this->_errorMap[$errCode] ) );
            break;
        }

    }

    /**
     * Check for found Errors
     * 
     * @return bool
     */
    public function thereAreErrors(){
        return !empty($this->exceptionList);
    }
    
    /**
     * Check For Warnings
     *
     * return bool
     */
    public function thereAreWarnings(){
    	$warnings = array_merge( $this->exceptionList, $this->warningList );
    	return !empty($warnings);
    }

	/**
	 * Get Warning level errors
	 *
	 * @return errObject[]
	 */
    public function getWarnings(){
    	return $this->checkErrorNone( array_merge( $this->warningList, $this->exceptionList ) );
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
     * @param errObject[] $list
     * @param bool $count
     * @return errObject[]
     */
    protected function checkErrorNone( array $list, $count = false ){
    	if( empty( $list ) ){
    		return array( errObject::get( array( 'outcome' => self::ERR_NONE, 'debug' => $this->_errorMap[self::ERR_NONE] . " [ 0 ]" ) ) );
    	}

        if ($count) {
            /**
             * count same values in array of errors.
             * we use array_map with strval callback function because array_count_values can count only strings or int
             * so:
             * __toString was made internally in errObject class
             *
             * @see http://www.php.net/manual/en/function.array-count-values.php
             **/
            $errorCount = array_count_values(array_map('strval', $list));

            /**
             * array_unique remove duplicated values in array,
             * Two elements are considered equal if and only if (string) $elem1 === (string) $elem2
             * so:
             * __toString was made internally in errObject class
             *
             * @see http://www.php.net/manual/en/function.array-unique.php
             */
            $list = array_unique($list);
            foreach ($list as $errObj) {
                $errObj->debug = $errObj->debug . " ( " . $errorCount[$errObj->outcome] . " )";
            }
        }
        return $list;

    }
    
    /**
     * Export Error List
     *
     */
    public function getErrors() {
        return $this->checkErrorNone($this->exceptionList);
    }

    /**
     * Get error list in json format
     * 
     * @return string Json
     */
    public function getErrorsJSON() {
        return json_encode( $this->checkErrorNone($this->exceptionList, true) );
    }
    
    /**
     * Get warning list in json format
     *
     * @return string Json
     */
    public function getWarningsJSON() {
    	return json_encode( $this->checkErrorNone( array_merge( $this->warningList, $this->exceptionList ), true ) );
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
    public function __construct($source_seg, $target_seg) {

        mb_regex_encoding('UTF-8');
        mb_internal_encoding("UTF-8");

        $src_enc = mb_detect_encoding($source_seg);
        $trg_enc = mb_detect_encoding($target_seg);

        $source_seg = mb_convert_encoding( $source_seg, 'UTF-8', $src_enc );
        $target_seg = mb_convert_encoding( $target_seg, 'UTF-8', $trg_enc );

        $source_seg = preg_replace( '#\n#u', chr( 0xc2 ) . chr( 0xa0 ), $source_seg );


        //Log::doLog($_GET);
//        Log::doLog($source_seg);
//        Log::doLog($target_seg);
//        Log::hexDump($source_seg);
//        Log::hexDump($target_seg);


        $this->source_seg = $source_seg;
        $this->target_seg = $target_seg;

        $this->srcDom = $this->_loadDom($source_seg, self::ERR_SOURCE);
        $this->trgDom = $this->_loadDom($target_seg, self::ERR_TARGET);

        $this->_resetDOMMaps();

    }

    /**
     * After initialization by Constructor, the dom is parsed and map structures are built
     *
     * @throws Exception
     */
    protected function _prepareDOMStructures(){

        $srcNodeList = @$this->srcDom->getElementsByTagName( 'root' )->item( 0 )->childNodes;
        $trgNodeList = @$this->trgDom->getElementsByTagName( 'root' )->item( 0 )->childNodes;

        if ( !$srcNodeList instanceof DOMNodeList || !$trgNodeList instanceof DOMNodeList ) {
            throw new DOMException('Bad DOMNodeList');
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
    protected function _mapDom( DOMNodeList $srcNodeList, DOMNodeList $trgNodeList ){

        if( empty($this->srcDomMap['elemCount']) || empty($this->trgDomMap['elemCount']) ){
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
     * @param DOMNodeList $elementList
     * @param array &$srcDomElements
     * @param int $depth
     * @param null $parentID
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
                );

                //set depth and increment for next occurrence
                $srcDomElements['DOMElement'][ $depth++ ] = $plainRef;

                //count occurrences of this tag name when needed, also transport id reference.
                @$srcDomElements[$element->tagName][] = $elementID;

                //reverse Lookup, from id to tag name
                @$srcDomElements['refID'][$elementID] = $element->tagName;

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
                $srcDomElements['DOMText'][$depth++] = $plainRef;
                //Log::doLog( "Found DOMText in Source " . var_export($plainRef,TRUE) );
            }

            $srcDomElements['elemCount']++;

        }
        //Log::doLog($srcDomElements);
    }

    /**
     * Method to reset the target DOM Map
     * when an internal substitution ( Tag ID Realign ) is made
     *
     */
    protected function _resetDOMMaps(){
        $this->srcDomMap = array( 'elemCount' => 0, 'x' => array(), 'g' => array(), 'refID' => array(), 'DOMElement' => array(), 'DOMText' => array() );
        $this->trgDomMap = array( 'elemCount' => 0, 'x' => array(), 'g' => array(), 'refID' => array(), 'DOMElement' => array(), 'DOMText' => array() );
    }

    /**
     * Load an XML String into DOMDocument Object and add a global Error if not valid
     *
     * @param $xmlString
     * @param int $targetErrorType
     *
     * @return DOMDocument
     */
    protected function _loadDom( $xmlString, $targetErrorType ){
        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'utf-8');
        $trg_xml_valid = @$dom->loadXML("<root>$xmlString</root>", LIBXML_NOBLANKS | LIBXML_NOENT );
        if ($trg_xml_valid === FALSE) {

            $rrorList = libxml_get_errors();
            foreach( $rrorList as $error ){
                if( $error->code == 76 /* libxml _xmlerror XML_ERR_TAG_NOT_FINISHED */ ){
                    if( preg_match( '#<x[^/>]+>#', $xmlString  ) ){
                        $this->_addError(self::ERR_UNCLOSED_X_TAG);
                    }
                }

            }

            $this->_addError($targetErrorType);
        }
        return $dom;
    }

    /**
     * Perform a replacement of all non-breaking spaces with a simple space char
     * 
     * manage the consistency of non breaking spaces,
     * chars coming, expecially,from MS Word
     * @link https://en.wikipedia.org/wiki/Non-breaking_space Wikipedia
     * 
     * @param string $s Source String to normalize
     * @return string Normalized
     */
    protected function _nbspToSpace($s) {
        return preg_replace("/\x{a0}/u", chr(0x20), $s);
    }

    /**
     * Perform a replacement of all simple space chars with non-breaking spaces
     * 
     * @param string $s
     * @return string
     */
    protected function _spaceToNonBreakingSpace($s) {
        return preg_replace("/\x{20}/u", chr(0xa0), $s);
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

        $this->_checkContentConsistency( $srcNodeList, $trgNodeList );
        $this->_checkTagsBoundary();

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

    /**
     * Performs a check for differences on first and last tags boundaries
     * All withespaces, tabs, carriage return, new lines between tags are checked
     * 
     */
    protected function _checkTagsBoundary() {

        //perform first char Line check if tags are not presents
        preg_match_all('#^[\s\t\x{a0}\r\n]+[^<]+#u', $this->source_seg, $source_tags);
        preg_match_all('#^[\s\t\x{a0}\r\n]+[^<]+#u', $this->target_seg, $target_tags);
        $source_tags = $source_tags[0];
        $target_tags = $target_tags[0];
        if( count($source_tags) != count($target_tags) ){
            $num = abs( count($source_tags) - count($target_tags) );
            for( $i=0; $i<$num ; $i++ ){
                $this->_addError(self::ERR_WS_HEAD);
            }
        }

        //get all special chars ( and spaces ) before a tag or after a closing g tag
        //</g> ...
        // <g ... >
        // <x ... />
        preg_match_all('#</g>[\s\t\x{a0}\r\n]+|[\s\t\x{a0}\r\n]+<(?:x[^>]+|[^/>]+)>#u', rtrim($this->source_seg), $source_tags);
        preg_match_all('#</g>[\s\t\x{a0}\r\n]+|[\s\t\x{a0}\r\n]+<(?:x[^>]+|[^/>]+)>#u', rtrim($this->target_seg), $target_tags);
        $source_tags = $source_tags[0];
        $target_tags = $target_tags[0];
        if( count($source_tags) != count($target_tags) ){
            $num = abs( count($source_tags) - count($target_tags) );
//            Log::doLog($source_tags);
//            Log::doLog($target_tags);
//            Log::hexDump($this->source_seg);
//            Log::hexDump($this->target_seg);
            for( $i=0; $i<$num ; $i++ ){
                $this->_addError(self::ERR_BOUNDARY_HEAD_TEXT);
            }
        }

        //get All special chars between G TAGS before first char occurrence
        //</g> nnn<g ...>
        preg_match_all('#</[^>]+>[\s\t\x{a0}\r\n]+.*<[^/>]+>#u', $this->source_seg, $source_tags);
        preg_match_all('#</[^>]+>[\s\t\x{a0}\r\n]+.*<[^/>]+>#u', $this->target_seg, $target_tags);
        $source_tags = $source_tags[0];
        $target_tags = $target_tags[0];
        if( ( count($source_tags) != count($target_tags) ) ){
            $num = abs( count($source_tags) - count($target_tags) );

//            Log::doLog($this->source_seg);
//            Log::doLog($this->target_seg);
//            Log::hexDump($this->source_seg);
//            Log::hexDump($this->target_seg);
//            Log::doLog($source_tags);
//            Log::doLog($target_tags);

            for( $i=0; $i<$num ; $i++ ){
                $this->_addError(self::ERR_BOUNDARY_HEAD);
            }
        }

        //get All special chars after LAST tag at the end of line if there are
        preg_match_all('/<[^>]+>[\s\t\x{a0}\r\n]+$/u', $this->source_seg, $source_tags);
        preg_match_all('/<[^>]+>[\s\t\x{a0}\r\n]+$/u', $this->target_seg, $target_tags);
        $source_tags = $source_tags[0];
        $target_tags = $target_tags[0];

        //so, if we found a last char mismatch, and if it is in the source: add to the target else trim it
        if( ( count($source_tags) != count($target_tags) ) && !empty( $source_tags ) ){

        	//Append a space to target for normalization.
            $this->target_seg .= " ";

            //Suppress Warning
            //$this->_addError(self::ERR_BOUNDARY_TAIL);

        } else {
            $this->target_seg = preg_replace( '#[\s\t\r\n]+$#u', "", $this->target_seg );
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
     *
     *
     * @return errObject[]
     */
    public function tryRealignTagID() {

        try {
            $this->_prepareDOMStructures();
        } catch ( DOMException $ex ) {
            return $this->getErrors();
        }

        $targetNumDiff = count($this->trgDomMap['DOMElement']) - count($this->srcDomMap['DOMElement']);
        $diffTagG  = count(@$this->trgDomMap['g']) - count(@$this->srcDomMap['g']);
        $diffTagX  = count(@$this->trgDomMap['x']) - count(@$this->srcDomMap['x']);

        //there are the same number of tags in source and target
        if( $targetNumDiff == 0 && !empty($this->srcDomMap['refID']) ){

            //if tags are in exact number
            if( $diffTagG == 0 && $diffTagX == 0  ){

                //Steps:

                //- re-align ids
                foreach( $this->trgDomMap['g'] as $pos => $tagID ){
                    $pattern[] = '|<g id=["\']{1}(' . $tagID . ')["\']{1}>|ui';
                    $replacement[] = '<g id="###' . $this->srcDomMap['g'][$pos] . '###">';
                }

                foreach( $this->trgDomMap['x'] as $pos => $tagID ){
                    $pattern[] = '|<x id=["\']{1}(' . $tagID . ')["\']{1} />|ui';
                    $replacement[] = '<x id="###' . $this->srcDomMap['x'][$pos] . '###" />';
                }

                $result = preg_replace( $pattern, $replacement, $this->target_seg, 1 );

                $result = str_replace( "###", "", $result);

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
    protected function _checkTagMismatch(){

        $targetNumDiff = $this->_checkTagCountMismatch( count($this->srcDomMap['DOMElement']), count($this->trgDomMap['DOMElement']) );
        if( $targetNumDiff == 0 ){
            $deepDiffTagG  = $this->_checkTagCountMismatch( count(@$this->srcDomMap['g']), count(@$this->trgDomMap['g']) );
        }

        //check for Tag ID MISMATCH
        $diffArray = array_diff_assoc($this->srcDomMap['refID'], $this->trgDomMap['refID']);
        if( !empty($diffArray) && !empty($this->trgDomMap['DOMElement']) ){
            $this->_addError(self::ERR_TAG_ID);
            //Log::doLog($diffArray);
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

        //* Fix error undefined variable trgTagReference when source target contains tags and target not
        $trgTagReference = array('node_idx' => null);

        foreach( $this->srcDomMap['DOMElement'] as $srcTagReference ){

            if( $srcTagReference['name'] == 'x' ){
                continue;
            }

            if( !is_null( $srcTagReference['parent_id'] ) ){

                $srcNodeContent = $this->_queryDOMElement( $this->srcDom, $srcTagReference )->textContent;

                foreach( $this->trgDomMap['DOMElement'] as $k => $elements ){
                    if( $elements['id'] == $srcTagReference['id'] ){
                        $trgTagReference = $this->trgDomMap['DOMElement'][$k];
                    }
                }

                $trgNodeContent = $this->_queryDOMElement( $this->trgDom, $trgTagReference )->textContent;

            } else {

                $srcNodeContent = $srcNodeList->item($srcTagReference['node_idx'])->textContent;

                foreach( $this->trgDomMap['DOMElement'] as $k => $elements ){
                    if( $elements['id'] == $srcTagReference['id'] ){
                        $trgTagReference = $this->trgDomMap['DOMElement'][$k];
                    }
                }

                $trgTagPos = $trgTagReference['node_idx'];
                $trgNodeContent = $trgNodeList->item( $trgTagPos )->textContent;

            }

            $this->_checkHeadWhiteSpaces($srcNodeContent, $trgNodeContent, $trgTagReference);
            $this->_checkTailWhiteSpaces($srcNodeContent, $trgNodeContent, $trgTagReference);
            $this->_checkHeadTabs($srcNodeContent, $trgNodeContent);
            $this->_checkTailTabs($srcNodeContent, $trgNodeContent);
            $this->_checkHeadCRNL($srcNodeContent, $trgNodeContent);
            $this->_checkTailCRNL($srcNodeContent, $trgNodeContent);

        }

    }

    /**
     * Find in a DOMDocument an Element by its Reference
     *
     * @param DOMDocument $domDoc
     * @param $TagReference
     * @return DOMElement
     */
    protected function _queryDOMElement( DOMDocument $domDoc, $TagReference ) {

        //Old implementation
        //        $Node = new DOMElement( 'g' );
        //
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
        //                //Log::doLog( 'Found: ' . $availableParentList->item($i)->textContent );
        //            }
        //        }

        $Node = $domDoc->getElementById( $TagReference['id'] );
        return ( !is_null($Node) ? $Node : new DOMElement( 'g' ) );

        return $Node;

    }

    /**
     * Check for number of tags in NodeList of Segment
     * 
     * @param int $srcNodeCount
     * @param int $trgNodeCount
     *
     * @return int
     */
    protected function _checkTagCountMismatch( $srcNodeCount, $trgNodeCount) {
        if ($srcNodeCount != $trgNodeCount) {
            $this->_addError(self::ERR_COUNT);
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
    protected function _checkHeadWhiteSpaces($srcNodeContent, $trgNodeContent, $trgTagReference ) {

        //backup and check start string
        $_srcNodeContent = $srcNodeContent;
        $_trgNodeContent = $trgNodeContent; //not Used

//                Log::doLog($srcNodeContent);
//                Log::doLog($trgNodeContent);


        $srcHasHeadNBSP = $this->_hasHeadNBSP($srcNodeContent);
        $trgHasHeadNBSP = $this->_hasHeadNBSP($trgNodeContent);
        
        //normalize spaces
        $srcNodeContent = $this->_nbspToSpace($srcNodeContent);
        $trgNodeContent = $this->_nbspToSpace($trgNodeContent);
        
        $headSrcWhiteSpaces = mb_stripos($srcNodeContent, " ", 0, 'utf-8');
        $headTrgWhiteSpaces = mb_stripos($trgNodeContent, " ", 0, 'utf-8');      
        
        //if source or target has a space at beginning and their relative positions are different
        if ( ( $headSrcWhiteSpaces === 0 || $headTrgWhiteSpaces === 0 ) && $headSrcWhiteSpaces !== $headTrgWhiteSpaces) {
            $this->_addError(self::ERR_WS_HEAD);
        }

        //normalize the target first space according to the source type
        if( $srcHasHeadNBSP != $trgHasHeadNBSP && !$this->thereAreErrors() ){

            //get the string from normalized string
            if( is_null($trgTagReference['parent_id']) ){
                //get the string from normalized string
                $_nodeNormalized = $this->normalizedTrgDOMNodeList->item( $trgTagReference['node_idx'] );
                $_trgNodeContent = $_nodeNormalized->nodeValue;

            } else {

                $_nodeNormalized = $this->_queryDOMElement( $this->normalizedTrgDOM, $trgTagReference );
                $_trgNodeContent = $_nodeNormalized->nodeValue;

            }

            if( $srcHasHeadNBSP ) {
                $_trgNodeContent = preg_replace( "/^\x{20}{1}/u", Utils::unicode2chr(0Xa0), $_trgNodeContent );
            } else {
                $_trgNodeContent = preg_replace( "/^\x{a0}{1}/u", Utils::unicode2chr(0X20), $_trgNodeContent );
            }

            $_nodeNormalized->nodeValue = $_trgNodeContent;

        }
 
    }

    /**
     * Search for trailing whitespaces ( comparison of strings )
     *
     * @param $srcNodeContent
     * @param $trgNodeContent
     * @param $trgTagReference
     */
    protected function _checkTailWhiteSpaces($srcNodeContent, $trgNodeContent, $trgTagReference ) {

    	//backup and check start string
    	$_srcNodeContent = $srcNodeContent;
    	$_trgNodeContent = $trgNodeContent; //not used

    	$srcHasTailNBSP = $this->_hasTailNBSP($srcNodeContent);
    	$trgHasTailNBSP = $this->_hasTailNBSP($trgNodeContent);

    	//normalize spaces
    	$srcNodeContent = $this->_nbspToSpace($srcNodeContent);
    	$trgNodeContent = $this->_nbspToSpace($trgNodeContent);

    	$srcLen = mb_strlen($srcNodeContent);
    	$trgLen = mb_strlen($trgNodeContent);

    	$trailingSrcChar = mb_substr($srcNodeContent, $srcLen - 1, 1, 'utf-8');
    	$trailingTrgChar = mb_substr($trgNodeContent, $trgLen - 1, 1, 'utf-8');
    	if ( ( $trailingSrcChar == " " || $trailingTrgChar == " " ) && $trailingSrcChar != $trailingTrgChar) {
    		$this->_addError(self::ERR_WS_TAIL);
    	}

    	//normalize the target first space according to the source type
    	if( $srcHasTailNBSP != $trgHasTailNBSP && !$this->thereAreErrors() ){

            //get the string from normalized string
            if( is_null($trgTagReference['parent_id']) ){
                //get the string from normalized string
                $_nodeNormalized = $this->normalizedTrgDOMNodeList->item( $trgTagReference['node_idx'] );
                $_trgNodeContent = $_nodeNormalized->nodeValue;

            } else {

                $_nodeNormalized = $this->_queryDOMElement( $this->normalizedTrgDOM, $trgTagReference );
                $_trgNodeContent = $_nodeNormalized->nodeValue;

            }

    		if( $srcHasTailNBSP ) {
    			$_trgNodeContent = preg_replace( "/\x{20}{1}$/u", Utils::unicode2chr(0Xa0), $_trgNodeContent );
    		} else {
    			$_trgNodeContent = preg_replace( "/\x{a0}{1}$/u", Utils::unicode2chr(0X20), $_trgNodeContent );
    		}

            $_nodeNormalized->nodeValue = $_trgNodeContent;

    	}

    }

    /**
     * Check if head character is a non-breaking space
     * 
     * @param string $s
     * @return bool
     */
    protected function _hasHeadNBSP($s) {
        return preg_match("/^\x{a0}{1}/u", $s);
    }
    
    /**
     * Check if tail character is a non-breaking space
     * 
     * @param string $s
     * @return bool
     */
    protected function _hasTailNBSP($s){
        return preg_match("/\x{a0}{1}$/u", $s);
    }
    
    /**
     * Return the target html string normalized in head and tail spaces according to Source
     * 
     * @return string
     * @throws LogicException
     */
    public function getTrgNormalized(){

        if( !$this->thereAreErrors() ){            
            preg_match('/<root>(.*)<\/root>/u', $this->normalizedTrgDOM->saveXML(), $matches );
            return stripslashes( $matches[1] );
        }
        
        throw new LogicException( __METHOD__ . " call when errors found in Source/Target integrity check & comparison.");
    }

    /**
     * Check for tabs differences in head part of string content
     * 
     * @param string $srcNodeContent
     * @param string $trgNodeContent
     * 
     */
    protected function _checkHeadTabs($srcNodeContent, $trgNodeContent) {
        $headSrcTabs = mb_stripos($srcNodeContent, "\t", 0, 'utf-8');
        $headTrgTabs = mb_stripos($trgNodeContent, "\t", 0, 'utf-8');
        if ( ( $headSrcTabs === 0 || $headTrgTabs === 0 ) && $headSrcTabs !== $headTrgTabs) {
            $this->_addError(self::ERR_TAB_HEAD);
        }
    }
    
    /**
     * Search for trailing tabs ( comparison of strings )
     * 
     * @param string $srcNodeContent
     * @param string $trgNodeContent
     */
    protected function _checkTailTabs($srcNodeContent, $trgNodeContent) {
        
        $srcLen = mb_strlen($srcNodeContent);
        $trgLen = mb_strlen($trgNodeContent);
        
        $trailingSrcChar = mb_substr($srcNodeContent, $srcLen - 1, 1, 'utf-8');
        $trailingTrgChar = mb_substr($trgNodeContent, $trgLen - 1, 1, 'utf-8');
        if ( ( $trailingSrcChar == "\t" || $trailingTrgChar == "\t" ) && $trailingSrcChar != $trailingTrgChar) {
            $this->_addError(self::ERR_TAB_TAIL);
        }

    }
    
    /**
     * Check for new line/carriage return differences in head part of string content
     * 
     * @param string $srcNodeContent
     * @param string $trgNodeContent
     * 
     */
    protected function _checkHeadCRNL($srcNodeContent, $trgNodeContent) {
        
        $headSrcCRNL = mb_split('^[\r\n]+',$srcNodeContent);
        $headTrgCRNL = mb_split('^[\r\n]+',$trgNodeContent);
        if ( ( count($headSrcCRNL) > 1 || count($headTrgCRNL) > 1 ) && $headSrcCRNL[0] !== $headTrgCRNL[0] ) {
            $this->_addError(self::ERR_CR_HEAD);
        }
        
    }
    
        
    /**
     * Check for new line/carriage return differences in tail part of string content
     * 
     * @param string $srcNodeContent
     * @param string $trgNodeContent
     * 
     */
    protected function _checkTailCRNL($srcNodeContent, $trgNodeContent) {

        $headSrcCRNL = mb_split('[\r\n]+$',$srcNodeContent);
        $headTrgCRNL = mb_split('^[\r\n]+$',$trgNodeContent);
        if ( ( count($headSrcCRNL) > 1 || count($headTrgCRNL) > 1 ) && end($headSrcCRNL) !== end($headTrgCRNL) ) {
            $this->_addError(self::ERR_CR_TAIL);
        }
        
    }
    
}

?>