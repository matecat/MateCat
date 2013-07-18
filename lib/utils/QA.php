<?php
/**
 * Translation string quality assurance.
 * 
 * Used for integrity comparison of strings. <br />
 * Add errors to a list every time it found a mismatch between strings.
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
        4 =>  'Tag id mismatch',
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
     * @var array(stdClass(number:string)) 
     */
    protected $exceptionList = array();

    /**
     * Add an error to error List.
     * Internal CodeMap
     * 
     * @param int $errCode
     */
    protected function _addError($errCode) {
        
        switch( $errCode ) {
            case self::ERR_COUNT:
            case self::ERR_SOURCE:
            case self::ERR_TARGET:
                $this->exceptionList[] = (object)array( 'outcome' => self::ERR_TAG_MISMATCH, 'debug' => $this->_errorMap[self::ERR_TAG_MISMATCH] );
            break;
            default:
                $this->exceptionList[] = (object)array( 'outcome' => $errCode, 'debug' => $this->_errorMap[$errCode] );
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
     * Export Error List
     * @param bool $show_ERR_NONE Display or not an ERR_NONE
     * @return stdClass(array(number:string))
     * <pre>
     * Array
     * (
     *     [0] => stdClass Object
     *         (
     *             [outcome] => 0
     *             [debug] => 
     *         ),
     * )
     * </pre>
     */
    public function getErrors( $show_ERR_NONE = true ) {
        if( empty( $this->exceptionList ) && $show_ERR_NONE ){
            return array( (object)array( 'outcome' => self::ERR_NONE, 'debug' => $this->_errorMap[self::ERR_NONE] ) );
        }
        return $this->exceptionList;        
    }
    
    /**
     * Get error list in json format
     * 
     * @param bool $show_ERR_NONE Display or not an ERR_NONE
     * @return string Json
     */
    public function getErrorsJSON( $show_ERR_NONE = true ) {
        return json_encode( $this->getErrors( $show_ERR_NONE ) );
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
                
        $src_enc = mb_detect_encoding($source_seg);
        $trg_enc = mb_detect_encoding($target_seg);
        
        $source_seg = mb_convert_encoding( $source_seg, 'UTF-8', $src_enc );
        $target_seg = mb_convert_encoding( $target_seg, 'UTF-8', $trg_enc );

        // ensure there are no entities
        $this->source_seg = html_entity_decode($source_seg, ENT_HTML401, 'UTF-8');
        $this->target_seg = html_entity_decode($target_seg, ENT_HTML401, 'UTF-8');

        // ensure that are no ampersand ( & ) and preserve them
        $seg = $this->_placeHoldAmp($this->source_seg);
        $tra = $this->_placeHoldAmp($this->target_seg);
        
        $this->srcDom = new DOMDocument('1.0', 'utf-8');
        $src_xml_valid = $this->srcDom->loadXML("<root>$seg</root>", LIBXML_NOBLANKS | LIBXML_NOENT );
        if ($src_xml_valid === FALSE) {
            $this->_addError(self::ERR_SOURCE);
        }

        $this->trgDom = new DOMDocument('1.0', 'utf-8');
        @$trg_xml_valid = $this->trgDom->loadXML("<root>$tra</root>", LIBXML_NOBLANKS | LIBXML_NOENT );
        if ($trg_xml_valid === FALSE) {
            $this->_addError(self::ERR_TARGET);
        }
        
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
        return preg_replace("/\&/", self::AMPPLACEHOLDER, $s);
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
     * @return array(array(number:string))
     * 
     */
    public function performConsistencyCheck() {

        $srcNodeList = @$this->srcDom->getElementsByTagName('root')->item(0)->childNodes;
        $trgNodeList = @$this->trgDom->getElementsByTagName('root')->item(0)->childNodes;
        
        if( ! $srcNodeList instanceof DOMNodeList || ! $trgNodeList instanceof DOMNodeList ){
            return $this->getErrors();
        }
        
        //Save normalized dom Element
        $this->normalizedTrgDOM = clone $this->trgDom;
        
        //Save normalized Dom Node list
        $this->normalizedTrgDOMNodeList = @$this->normalizedTrgDOM->getElementsByTagName('root')->item(0)->childNodes;
        
        $this->_checkTagsBoundary();
        $this->_checkContentConsistency($srcNodeList, $trgNodeList);

        // all checks completed
        return $this->getErrors();
    }

    /**
     * Performs a check for differences on tags boundaries
     * All withespaces, tabs, carriage return, new lines between tags are checked
     * 
     */
    protected function _checkTagsBoundary() {
        
        //get all special chars before a tag
        preg_match_all('/[\s\t\r\n]+<[^\/>]+>/', $this->source_seg, $source_tags);
        preg_match_all('/[\s\t\r\n]+<[^\/>]+>/', $this->target_seg, $target_tags);
        $source_tags = $source_tags[0];
        $target_tags = $target_tags[0];
        if( count($source_tags) != count($target_tags) ){
            $this->_addError(self::ERR_BOUNDARY_HEAD);
        }
        
        preg_match_all('/<[^>]+>[\s\t\r\n]+$/', $this->source_seg, $source_tags);
        preg_match_all('/<[^>]+>[\s\t\r\n]+$/', $this->target_seg, $target_tags);
        $source_tags = $source_tags[0];
        $target_tags = $target_tags[0];
        if( count($source_tags) != count($target_tags) ){
            $this->_addError(self::ERR_BOUNDARY_TAIL);
        }

    }
    
    /**
     * Perform all consistency contents check Internally
     *  
     * @param DOMNodeList $srcNodeList
     * @param DOMNodeList $trgNodeList
     */
    protected function _checkContentConsistency(DOMNodeList $srcNodeList, DOMNodeList $trgNodeList) {
        
        $this->_checkTagCount($srcNodeList->length, $trgNodeList->length);
                
        for ($i = 0; $i < $srcNodeList->length; $i++) {

            if( $srcNodeList->item($i) instanceof DOMText ){
                continue;
            }
            
            if ($srcNodeList->item($i)->getAttribute('id') != $trgNodeList->item($i)->getAttribute('id')) {
                $this->_addError(self::ERR_TAG_ID);
            }

            $srcNodeContent = $srcNodeList->item($i)->textContent;
            $trgNodeContent = $trgNodeList->item($i)->textContent;

            $this->_checkHeadWhiteSpaces($srcNodeContent, $trgNodeContent, $i);
            $this->_checkTailWhiteSpaces($srcNodeContent, $trgNodeContent, $i);
            $this->_checkHeadTabs($srcNodeContent, $trgNodeContent);
            $this->_checkTailTabs($srcNodeContent, $trgNodeContent);
            $this->_checkHeadCRNL($srcNodeContent, $trgNodeContent);
            $this->_checkTailCRNL($srcNodeContent, $trgNodeContent);
            
        }
                
    }

    /**
     * Check for number of tags in NodeList of Segment
     * 
     * @param int $srcNodeCount
     * @param int $trgNodeCount
     *
     */
    protected function _checkTagCount( $srcNodeCount, $trgNodeCount) {
        if ($srcNodeCount != $trgNodeCount) {
            $this->_addError(self::ERR_COUNT);
        }
    }
    
    /**
     * Search for head whitespaces ( comparison of strings )
     * 
     * @param string $srcNodeContent
     * @param string $trgNodeContent
     * @param int $nodeDepth
     */
    protected function _checkHeadWhiteSpaces($srcNodeContent, $trgNodeContent, $nodeDepth) {
        
        //backup and check start string
        $_srcNodeContent = $srcNodeContent;
        #$_trgNodeContent = $trgNodeContent; //not Used
        
        $srcHasHeadNBSP = $this->_hasHeadNBSP($srcNodeContent);
        $trgHasHeadNBSP = $this->_hasHeadNBSP($trgNodeContent);
           
        //normalize spaces
        $srcNodeContent = $this->_nbspToSpace($srcNodeContent);
        $trgNodeContent = $this->_nbspToSpace($trgNodeContent);
        
        $headSrcWhiteSpaces = mb_stripos($srcNodeContent, " ", 0, 'utf-8');
        $headTrgWhiteSpaces = mb_stripos($trgNodeContent, " ", 0, 'utf-8');      
        
        if ( ( $headSrcWhiteSpaces === 0 || $headTrgWhiteSpaces === 0 ) && $headSrcWhiteSpaces !== $headTrgWhiteSpaces) {
            $this->_addError(self::ERR_WS_HEAD);
        }

        //normalize the target first space according to the source type
        if( $srcHasHeadNBSP != $trgHasHeadNBSP && !$this->thereAreErrors() ){

            //get the string from normalized string
            $_trgNodeContent = $this->normalizedTrgDOMNodeList->item( $nodeDepth )->nodeValue;
            
            if( $srcHasHeadNBSP ) {
                $_trgNodeContent = preg_replace( "/^\x{20}{1}/u", Utils::unicode2chr(0Xa0), $_trgNodeContent );
            } else {
                $_trgNodeContent = preg_replace( "/^\x{a0}{1}/u", Utils::unicode2chr(0X20), $_trgNodeContent );
            }
            
            $this->normalizedTrgDOMNodeList->item( $nodeDepth )->nodeValue = $_trgNodeContent;

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
            preg_match('/<root>(.*)<\/root>/u', $this->normalizedTrgDOM->saveHTML(), $matches );
            return html_entity_decode( $matches[1], ENT_HTML401, 'UTF-8' );
        }
        
        throw new LogicException( __METHOD__ . " call when errors found in Source/Target integrity check & comparison.");
    }
        
    /**
     * Search for trailing whitespaces ( comparison of strings )
     * 
     * @param string $srcNodeContent
     * @param string $trgNodeContent
     * @param int $nodeDepth
     */
    protected function _checkTailWhiteSpaces($srcNodeContent, $trgNodeContent, $nodeDepth) {
        
        //backup and check start string
        $_srcNodeContent = $srcNodeContent;
        #$_trgNodeContent = $trgNodeContent; //not used
        
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
            $_trgNodeContent = $this->normalizedTrgDOMNodeList->item( $nodeDepth )->nodeValue;

            if( $srcHasTailNBSP ) {
                $_trgNodeContent = preg_replace( "/\x{20}{1}$/u", Utils::unicode2chr(0Xa0), $_trgNodeContent );
            } else {
                $_trgNodeContent = preg_replace( "/\x{a0}{1}$/u", Utils::unicode2chr(0X20), $_trgNodeContent );
            }
            
            $this->normalizedTrgDOMNodeList->item( $nodeDepth )->nodeValue = $_trgNodeContent;

        }
        
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