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
 * Used for integrity comparison of strings. <br />
 * Add errors/warnings to a list every time it found a mismatch between strings.
 * 
 * NOTE:
 * If a not well formed XML source/target is provided, all integrity checks are skipped returning a 
 * 'bad source xml/bad target xml' error.
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
 * 4) Tag id corrispondence mismatch <br />
 * 5) Tag content: Head whitespaces mismatch ( if a beginning withespace is present only in one of them ) <br />
 * 6) Tag content: Tail whitespaces mismatch ( if a trailing withespace is present only in one of them ) <br />
 * 7) Tag content: Head TAB mismatch ( if a beginning TAB is present only in one of them ) <br />
 * 8) Tag content: Tail tab mismatch ( if a trailing TAB is present only in one of them ) <br />
 * 9) Tag content: Head carriage return / new line mismatch ( if a beginning carriage return / new line is present only in one of them ) <br />
 * 10) Tag content: Tail carriage return / new line mismatch ( if a trailing carriage return / new line is present only in one of them ) <br />
 * 11) Tag boundary: Head characters mismatch ( if beetween tags there are different characters [\s\t\r\n] ) <br />
 * 12) Tag boundary: Tail characters mismatch ( if at the end of last tag there are different characters [\s\t\r\n] ) <br />
 * 
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
    
    const AMPPLACEHOLDER = "##AMPPLACEHOLDER##";

    const ERR_NONE           = 0;
    const ERR_COUNT          = 1;
    const ERR_SOURCE         = 2;
    const ERR_TARGET         = 3;
    const ERR_TAG_ID         = 4;
    const ERR_WS_HEAD        = 5;
    const ERR_WS_TAIL        = 6;
    const ERR_TAB_HEAD       = 7;
    const ERR_TAB_TAIL       = 8;
    const ERR_CR_HEAD        = 9;
    const ERR_CR_TAIL        = 10;
    const ERR_BOUNDARY_HEAD  = 11;
    const ERR_BOUNDARY_TAIL  = 12;
    
    const ERR_TAG_MISMATCH   = 1000;
    
    /**
     * Human Readable error map.
     * Created accordly with Error constants
     * 
     * <pre>
     * array (
     *     ERR_NONE            =>  '',
     *     ERR_COUNT           =>  'Tag count mismatch',
     *     ERR_SOURCE          =>  'bad source xml',
     *     ERR_TARGET          =>  'bad target xml',
     *     ERR_TAG_ID          =>  'Tag id mismatch',
     *     ERR_WS_HEAD         =>  'Heading whitespaces mismatch',
     *     ERR_WS_TAIL         =>  'Tail whitespaces mismatch',
     *     ERR_TAB_HEAD        =>  'Heading tab mismatch',
     *     ERR_TAB_TAIL        =>  'Tail tab mismatch',
     *     ERR_CR_HEAD         =>  'Heading carriage return mismatch',
     *     ERR_CR_TAIL         =>  'Tail carriage return mismatch',
     *     ERR_BOUNDARY_HEAD   =>  'Char mismatch between tags',
     *     ERR_BOUNDARY_TAIL   =>  'End line char mismatch',
     * );
     * </pre>
     * @var array(string) 
     */
    protected $_errorMap = array(
        0 =>  '',
        1 =>  'Tag count mismatch',
        2 =>  'bad source xml',
        3 =>  'bad target xml',
        4 =>  'Tag ID mismatch',
        5 =>  'Heading whitespaces mismatch',
        6 =>  'Tail whitespaces mismatch',
        7 =>  'Heading tab mismatch',
        8 =>  'Tail tab mismatch',
        9 =>  'Heading carriage return mismatch',
        10 => 'Tail carriage return mismatch',
        11 => 'Char mismatch between tags',
        12 => 'End line char mismatch',
        
        /*
         * grouping
         *  1 =>  'Tag count mismatch',
         *  2 =>  'bad source xml',
         *  3 =>  'bad target xml',
         */
        1000 => 'Tag mismatch',
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
        //Log::doLog( $errCode . " :: " . $this->_errorMap[$errCode]);
        switch( $errCode ) {
            case self::ERR_COUNT:
            case self::ERR_SOURCE:
            case self::ERR_TARGET:
                $this->exceptionList[] = errObject::get( array( 'outcome' => self::ERR_TAG_MISMATCH, 'debug' => $this->_errorMap[self::ERR_TAG_MISMATCH] ) );
            break;
            case self::ERR_TAG_ID:
            	$this->exceptionList[] = errObject::get( array( 'outcome' => self::ERR_TAG_ID, 'debug' => $this->_errorMap[self::ERR_TAG_ID] ) );
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
    		return array( errObject::get( array( 'outcome' => self::ERR_NONE, 'debug' => $this->_errorMap[self::ERR_NONE] . " [ 1 ]" ) ) );
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
     * Arguments: raw source string and raw target string
     * 
     * @param string $source_seg
     * @param string $target_seg
     * 
     */
    public function __construct($source_seg, $target_seg) {

        mb_regex_encoding('UTF-8');
        mb_internal_encoding("UTF-8");
        
//         Log::doLog($_GET);
//         Log::doLog($source_seg);
//         Log::doLog($target_seg);
        
        $src_enc = mb_detect_encoding($source_seg);
        $trg_enc = mb_detect_encoding($target_seg);
        
        $source_seg = mb_convert_encoding( $source_seg, 'UTF-8', $src_enc );
        $target_seg = mb_convert_encoding( $target_seg, 'UTF-8', $trg_enc );

        $this->source_seg = $source_seg;
        $this->target_seg = $target_seg;

        $this->srcDom = $this->_loadDom($source_seg, self::ERR_SOURCE);
        $this->trgDom = $this->_loadDom($target_seg, self::ERR_TARGET);

    }

    /**
     * Load an XML String into DomObject and add a global Error if not valid
     *
     * @param $xmlString
     * @param int $targetErrorType
     *
     * @return DOMDocument
     */
    protected function _loadDom( $xmlString, $targetErrorType ){
        //libxml_use_internal_errors
        $dom = new DOMDocument('1.0', 'utf-8');
        $trg_xml_valid = @$dom->loadXML("<root>$xmlString</root>", LIBXML_NOBLANKS | LIBXML_NOENT );
        if ($trg_xml_valid === FALSE) {
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
     * @param type $s
     * @return string
     */
    protected function _spaceToNonBreakingSpace($s) {
        return preg_replace("/\x{20}/u", chr(0xa0), $s);
    }
    
    /**
     * Replace Ampersand with a placeHolder
     * 
     * @param string $s
     * @return string
     */
    protected function _placeHoldAmp($s) {
        if( !preg_match("/\&([#a-zA-Z0-9]+);/", $s) ){
            return preg_replace("/\&/", self::AMPPLACEHOLDER, $s);
        }
        return $s;
    }

    /**
     * Restore Ampersand From placeHolder
     * 
     * @param string $s
     * @return string
     */
    protected function _restoreamp($s) {
        $pattern = "#" . self::AMPPLACEHOLDER . "#";
        return preg_replace($pattern, Utils::unicode2chr("&"), $s);
    }

    /**
     * Perform all integrity check and comparisons on source and target string
     * 
     * @return errObject[]
     * 
     */
    public function performConsistencyCheck() {

        $srcNodeList = @$this->srcDom->getElementsByTagName( 'root' )->item( 0 )->childNodes;
        $trgNodeList = @$this->trgDom->getElementsByTagName( 'root' )->item( 0 )->childNodes;

        if ( !$srcNodeList instanceof DOMNodeList || !$trgNodeList instanceof DOMNodeList ) {
            return $this->getErrors();
        }

        //Save normalized dom Element
        $this->normalizedTrgDOM = clone $this->trgDom;

        //Save normalized Dom Node list
        $this->normalizedTrgDOMNodeList = @$this->normalizedTrgDOM->getElementsByTagName( 'root' )->item( 0 )->childNodes;

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

        $srcNodeList = @$this->srcDom->getElementsByTagName( 'root' )->item( 0 )->childNodes;
        $trgNodeList = @$this->trgDom->getElementsByTagName( 'root' )->item( 0 )->childNodes;

        if ( !$srcNodeList instanceof DOMNodeList || !$trgNodeList instanceof DOMNodeList ) {
            return $this->getErrors();
        }

        $this->_checkTagMismatch( $srcNodeList, $trgNodeList );

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
        preg_match_all('#^[\s\t\r\n]+[^<]+#u', $this->source_seg, $source_tags);
        preg_match_all('#^[\s\t\r\n]+[^<]+#u', $this->target_seg, $target_tags);
        $source_tags = $source_tags[0];
        $target_tags = $target_tags[0];
        if( count($source_tags) != count($target_tags) ){
            $this->_addError(self::ERR_WS_HEAD);
        }

        //get all special chars before a tag
        preg_match_all('#[\s\t\r\n]+<[^/>]+>#u', $this->source_seg, $source_tags);
        preg_match_all('#[\s\t\r\n]+<[^/>]+>#u', $this->target_seg, $target_tags);
        $source_tags = $source_tags[0];
        $target_tags = $target_tags[0];
        if( count($source_tags) != count($target_tags) ){
            $this->_addError(self::ERR_BOUNDARY_HEAD);
        }

        //get All special chars between TAGS before first char occurrence
        preg_match_all('#</[^>]+>[\s\t\r\n]+.*<[^/>]+>#u', $this->source_seg, $source_tags);
        preg_match_all('#</[^>]+>[\s\t\r\n]+.*<[^/>]+>#u', $this->target_seg, $target_tags);
        $source_tags = $source_tags[0];
        $target_tags = $target_tags[0];
        if( ( count($source_tags) != count($target_tags) ) ){
            $this->_addError(self::ERR_BOUNDARY_HEAD);
        }

        //get All special chars after LAST tag at the end of line if there are
        preg_match_all('/<[^>]+>[\s\t\r\n]+$/u', $this->source_seg, $source_tags);
        preg_match_all('/<[^>]+>[\s\t\r\n]+$/u', $this->target_seg, $target_tags);
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

    protected function _checkTagMismatch( DOMNodeList $srcNodeList, DOMNodeList $trgNodeList ){

        if( !function_exists( 'domIDCompare' ) ){
            function domIDCompare( $a, $b ){
                if( $a['id'] == $b['id'] ) return 0;
                return ( $a['id'] < $b['id'] ? -1 : 1 );
            }
        }

        $srcDomElements = array();
        $trgDomElements = array();

        $this->_mapElements( $srcNodeList, $srcDomElements );
        $this->_mapElements( $trgNodeList, $trgDomElements );

//        Log::doLog( $srcDomElements );
//        Log::doLog( $trgDomElements );

        $this->_checkTagCountMismatch( count($srcDomElements), count($trgDomElements) );

        //check for Tag ID MISMATCH
        $diffArray = array_udiff(  $srcDomElements, $trgDomElements, 'domIDCompare' );
        if( !empty($diffArray) && !empty($trgDomElements) ){
            $this->_addError(self::ERR_TAG_ID);
            //Log::doLog($diffArray);
        }

        return array( $srcDomElements, $trgDomElements );

    }

    /**
     * Perform all consistency contents check Internally
     *  
     * @param DOMNodeList $srcNodeList
     * @param DOMNodeList $trgNodeList
     */
    protected function _checkContentConsistency(DOMNodeList $srcNodeList, DOMNodeList $trgNodeList) {

        $result = $this->_checkTagMismatch( $srcNodeList, $trgNodeList );

        $srcDomElements = $result[0];
        $trgDomElements = $result[1];

        foreach( $srcDomElements as $srcTagID ){

            if( $srcTagID['name'] == 'x' ){
                continue;
            }

            if( !is_null( $srcTagID['parent_id'] ) ){

                $srcNodeContent = $this->_queryDOMElement( $this->srcDom, $srcTagID )->textContent;

                foreach( $trgDomElements as $k => $elements ){
                    if( $elements['id'] == $srcTagID['id'] ){
                        $trgTagReference = $trgDomElements[$k];
                    }
                }

                $trgNodeContent = $this->_queryDOMElement( $this->trgDom, $trgTagReference )->textContent;

            } else {

                $srcNodeContent = $srcNodeList->item($srcTagID['node_idx'])->textContent;

                foreach( $trgDomElements as $k => $elements ){
                    if( $elements['id'] == $srcTagID['id'] ){
                        $trgTagReference = $trgDomElements[$k];
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

        $Node = new DOMElement( 'g' );

        $availableParentList = $domDoc->getElementsByTagName( $TagReference[ 'name' ] );
        $availableParentsLen = $availableParentList->length;

        for ( $i = 0; $i < $availableParentsLen; $i++ ) {

            $element = $availableParentList->item( $i );
            if ( $element->getAttribute( 'id' ) == $TagReference[ 'id' ] ) {

                /**
                 * @var DOMElement $Node
                 */
                $Node = $element;

                //Log::doLog( 'Found: ' . $availableParentList->item($i)->textContent );
            }
        }

        return $Node;

    }

    /**
     * Create a map of NodeTree
     * <pre>
     * array (
     *   0 => array (
     *     'type' => 'DOMElement',
     *     'name' => 'g',
     *     'parent_id' => NULL,
     *     'id' => '557',
     *     'node_idx' => 0,
     *   ),
     *   1 => array (
     *     'type' => 'DOMElement',
     *     'name' => 'g',
     *     'parent_id' => NULL,
     *     'id' => '558',
     *     'node_idx' => 1,
     *   ),
     *   2 => array (
     *     'type' => 'DOMElement',
     *     'name' => 'g',
     *     'parent_id' => '558',
     *     'id' => '559',
     *     'node_idx' => 1,
     *   ),
     *   3 => array (
     *     'type' => 'DOMElement',
     *     'name' => 'g',
     *     'parent_id' => '558',
     *     'id' => '560',
     *     'node_idx' => 3,
     *   ),
     *   4 => array (
     *     'type' => 'DOMElement',
     *     'name' => 'x',
     *     'parent_id' => '560',
     *     'id' => '125',
     *     'node_idx' => 0,
     *  )
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
                    'parent_id' => $parentID,
                    'id'        => $elementID,
                    'node_idx'  => $i
                );

                $srcDomElements[ $depth++ ] = $plainRef;

                if ( $element->hasChildNodes() ) {
                    $this->_mapElements( $element->childNodes, $srcDomElements, $depth, $elementID );
                }

            } else {
                //$srcDomElements[$depth++] = array( 'type' => 'DOMText', 'content' => $elementList->item($i)->textContent );
                //Log::doLog( "Found DOMText in Source " . $elementList->item($i)->textContent );
            }

        }
        //Log::doLog($srcDomElements);
    }

    /**
     * Check for number of tags in NodeList of Segment
     * 
     * @param int $srcNodeCount
     * @param int $trgNodeCount
     *
     */
    protected function _checkTagCountMismatch( $srcNodeCount, $trgNodeCount) {
        if ($srcNodeCount != $trgNodeCount) {
            $this->_addError(self::ERR_COUNT);
        }
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