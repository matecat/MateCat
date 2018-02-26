<?php

/*

   Basic and Pretty Tollerant Xliff 1.0, 1.1, 1.2 and 1.3 into Array converter

   Developed by Translated s.r.l. distributed in LGPL.

   XLIFF 1.2
   http://docs.oasis-open.org/xliff/xliff-core/xliff-core.html

   This parser was written in order to extract just the basic information needed for translation within Matecat.
   This tool has been designed to try to parse non well formed XML files, since they are pretty common in the translation industry :)

   Performance 3s for 1M words (on a i7 quad-core)

   Example Output

   ['files'] = array
   [1] = array
   [attr] = array
   ['source-lang']
   ['target-lang']
   ['data-type']
   ['trans-units'] = array
   [1] = array
   ['attr'] = array
   ['id'] = Unique ID in file (hopefully)
   ['source'] = array
   ['raw-content'] = "Hello &lt;b&gt;World&lt;/b&gt;"
   ['target'] = array
   ['raw-content'] = "Ciao &lt;b&gt;Mondo&lt;/b&gt;"


   Tollerance
   1) As by specs, all <file> have required attributes, we issue a warning but continue importing.
   2) '" to enclose attributes id="1" id="1", but not without quotes eg. id=1
   3) Non-closed or wrongly nested tags are managed and often results in a skipped elements without affecting the rest.
   4) We often see XLIFF files with ISO content, if detected it get converted at file level (unfortunately not at segment level, risky)

 */

class Xliff_Parser {

    /**
     * @var FeatureSet
     */
    protected $features;

    public function __construct( FeatureSet $features = null ) {
        $this->features = $features;
    }

    private static $find_xliff_tags_reg = null;

	public function Xliff2Array($file_content) {

	    $xliff = [];

		// Pre-Processing.
		// Fixing non UTF-8 encoding (often I get Unicode UTF-16)
		$enc = mb_detect_encoding($file_content);
		if ($enc <> 'UTF-8') {
			$file_content = iconv($enc, 'UTF-8', $file_content);
			$xliff['parser-warnings'][] = "Input identified as $enc ans converted UTF-8. May not be a problem if the content is English only";
		}

		// Checking Requirements (By specs, I know that xliff version is in the first 1KB)
		preg_match('|<xliff.*?\sversion\s?=\s?["\'](.*?)["\']|si', substr($file_content, 0, 1000), $tmp);
		if (!isset($tmp[1])) {
			$xliff['parser-errors'][] = "Cannot import. This does not seems a valid XLIFF, we support version 1.0, 1.1, 1.2.";
			return $xliff;
		}
		if (!in_array($tmp[1], array('1.0', '1.1', '1.2'))) {
			$xliff['parser-errors'][] = "Cannot import XLIFF version $tmp[1]. We only support XLIFF (version 1.0, 1.1, 1.2).";
			return $xliff;
		}

		// Getting the Files

		$files = preg_split('|<file[\s>]|si', $file_content, -1, PREG_SPLIT_NO_EMPTY);

		$i = 0;
		foreach ($files as $file) {

			// First element in the XLIFF split is the content before <file> (header), skipping
			if ($i > 0) {

				// Getting Files Attributes
				// Restrict preg action for speed, just for attributes
				$file_short = substr($file, 0, strpos($file, '>') + 1);
				// Original
				unset($temp);
				preg_match('|original\s?=\s?["\'](.*?)["\']|si', $file_short, $temp);
				if (isset($temp[1])) {
					$xliff['files'][$i]['attr']['original'] = $temp[1];
				} else {
					$xliff['files'][$i]['attr']['original'] = "no-name";
				}
				// Source-language
				unset($temp);
				preg_match('|source-language\s?=\s?["\'](.*?)["\']|si', $file_short, $temp);
				if (isset($temp[1])) {
					$xliff['files'][$i]['attr']['source-language'] = $temp[1];
				} else {
					$xliff['files'][$i]['attr']['source-language'] = "en-US";
					// Todo, we could do auto-detect!
				}
				// Data-type
				unset($temp);
				preg_match('|datatype\s?=\s?["\'](.*?)["\']|si', $file_short, $temp);
				if (isset($temp[1])) {
					$xliff['files'][$i]['attr']['datatype'] = $temp[1];
				} else {
					$xliff['files'][$i]['attr']['datatype'] = "txt";
				}
				// Target-language
                unset( $temp );
                preg_match( '|target-language\s?=\s?["\'](.*?)["\']|si', $file_short, $temp );
                if ( isset( $temp[ 1 ] ) ) {
                    $xliff[ 'files' ][ $i ][ 'attr' ][ 'target-language' ] = $temp[ 1 ];
                }

                //Custom MateCat x-Attribute
                unset( $temp );
                preg_match( '|x-(.*?)=\s?["\'](.*?)["\']|si', $file_short, $temp );
                if ( isset( $temp[ 1 ] ) ) {
                    $xliff[ 'files' ][ $i ][ 'attr' ][ 'custom' ][ $temp[ 1 ] ] = $temp[ 2 ];
                }

                /*
                 * PHP 5.2 BUG/INCONSISTENCY vs PHP > 5.2 IN preg_match_all
                 * This code works only in php > 5.2 when there are more than one internal-file tag
                 *
                 *   preg_match_all( '|<internal-file[^>]form\s?=\s?["\'](.*?)["\'][^>]*>(.*?)</internal-file>|si', $file, $temp, PREG_SET_ORDER );
                 *   foreach( $temp as $_order => $internal ){
                 *       $xliff['files'][$i]['reference'][$_order]['form-type'] = $internal[1];
                 *       $xliff['files'][$i]['reference'][$_order]['base64']    = $internal[2];
                 *   }
                 *  unset($internal);
                 *  unset($temp);
                 *
                 */

                //get External reference for sub-files
                //should be an error for potential exhausting of memory when a base64 file is very large
                $temp = explode( "<internal-file", $file );
                $_order = 0;
                foreach( $temp as &$internal_file ){ //pass by reference, do not make another copy of the array
                    preg_match( '|form\s?=\s?["\'](.*?)["\'][^>]*>(.*)</internal-file>|si', $internal_file, $hash );
                    if( isset( $hash[1] ) ){
                        $xliff['files'][$i]['reference'][$_order]['form-type'] = $hash[1];
                        $xliff['files'][$i]['reference'][$_order]['base64']    = $hash[2];
                        $_order++;
                    }
                }
                unset($_order);
                unset($hash);
                unset($temp);

				// Getting Trans-units
				$trans_units = preg_split('|<trans-unit[\s>]|si', $file, -1, PREG_SPLIT_NO_EMPTY);
				$j = 0;
				$trans_unit_id_array_for_uniqueness_check = [];
				foreach ($trans_units as $trans_unit) {

					// First element in the XLIFF split is the header, not the first file
					if ($j > 0) {
						// Getting Trans-unit attributes
						// ID
                        unset( $temp );
                        preg_match( '|id\s?=\s?["\'](.*?)["\']|si', $trans_unit, $temp );

                        if ( trim( $temp[ 1 ] ) == "" ) {
                            throw new DomainException( "Invalid trans-unit id found. EMPTY value", 400 );
                        } else {
                            $trans_unit_id_array_for_uniqueness_check[] = trim( $temp[ 1 ] );
                        }

                        $xliff[ 'files' ][ $i ][ 'trans-units' ][ $j ][ 'attr' ][ 'id' ] = $temp[ 1 ];

						// Translate
						unset($temp);
						preg_match('|translate\s?=\s?["\'](.*?)["\']|si', $trans_unit, $temp);
						if (isset($temp[1]))
							$xliff['files'][$i]['trans-units'][$j]['attr']['translate'] = $temp[1];

                        /**
                         * Approved
                         * @see http://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html#approved
                         */
                        unset($temp);
                        preg_match('|approved\s?=\s?["\'](.*?)["\']|si', $trans_unit, $temp);
                        if (isset($temp[1]))
                            $xliff['files'][$i]['trans-units'][$j]['attr']['approved'] = filter_var( $temp[1], FILTER_VALIDATE_BOOLEAN );

                        unset($temp);

                        // Getting Source and Target raw content
                        $this->getSource( $xliff, $i, $j, $trans_unit );
						$this->getTarget( $xliff, $i, $j, $trans_unit );
						$this->getSDLStatus( $xliff, $i, $j, $trans_unit );

                        $this->evalNotes($xliff, $i, $j, $trans_unit);

						// Add here other trans-unit sub-elements you need, copying and pasting the 3 lines below

                        unset( $temp );

                        //TODO improve with DOM
                        preg_match( '|<seg-source.*?>(.*?)</seg-source>|si', $trans_unit, $temp );
                        if ( isset( $temp[ 1 ] ) ) {

                            $markers = $temp[ 1 ];

                            unset( $temp );

                            $markers = preg_split( '#<mrk\s#si', $markers, -1 );

                            //same markers are in the target tag if it is present with pre-translations, because seg-target does not exists
                            //in XLIFF standard definition
                            //so, we split for the same markers and use same positional indexes, by the way,
                            // there can be empty markers in translation ( <mrk ... /> ) and not in seg-source
                            // the regular expressions must be different
                            if ( isset( $xliff[ 'files' ][ $i ][ 'trans-units' ][ $j ][ 'target' ][ 'raw-content' ] ) ) {
                                $target_markers = preg_split( '#<mrk\s#si', $xliff[ 'files' ][ $i ][ 'trans-units' ][ $j ][ 'target' ][ 'raw-content' ], -1 );
                            }

                            $mi = 0;
                            $k  = 0;
                            while ( isset( $markers[ $mi + 1 ] ) ) {
                                unset( $mid );

                                preg_match( '|mid\s?=\s?["\'](.*?)["\']|si', $markers[ $mi + 1 ], $mid );

                                //re-build the mrk tag after the split
                                $originalMark = trim( '<mrk ' . $markers[ $mi + 1 ] );

                                $xliff[ 'files' ][ $i ][ 'trans-units' ][ $j ][ 'seg-source' ][ $k ][ 'mid' ]           = $mid[ 1 ];
                                $xliff[ 'files' ][ $i ][ 'trans-units' ][ $j ][ 'seg-source' ][ $k ][ 'ext-prec-tags' ] = ( $mi == 0 ? $markers[ 0 ] : "" ); //put the first tags of seg-source structure

                                $mark_string = preg_replace( '#^<mrk\s[^>]+>(.*)#', '$1', $originalMark ); // at this point we have: ---> 'Test </mrk> </g>>'
                                $mark_content = preg_split( '#</mrk>#si', $mark_string );

                                $xliff[ 'files' ][ $i ][ 'trans-units' ][ $j ][ 'seg-source' ][ $k ][ 'raw-content' ]   = $mark_content[0];
                                $xliff[ 'files' ][ $i ][ 'trans-units' ][ $j ][ 'seg-source' ][ $k ][ 'ext-succ-tags' ] = $mark_content[1];

								// Different from source and target content, I expect that if you used seg-source it is a well done tool, so I don't try to fix.
                                if( isset( $xliff['files'][$i]['trans-units'][$j]['target']['raw-content'] ) && !empty( $xliff['files'][$i]['trans-units'][$j]['target']['raw-content'] ) ){

                                    unset($mt_id);

                                    //if mark tags are present in target ( target segmentation )
                                    if ( isset( $target_markers[ $mi + 1 ] ) ) {

                                        $originalTransMark = trim( '<mrk ' . $target_markers[ $mi + 1 ] );

                                        //target and seg-source can have different mark id, so i store the target mid
                                        //with same rules
                                        preg_match( '|mid\s?=\s?["\'](.*?)["\']|si', $target_markers[ $mi + 1 ], $mt_id );

                                        //use seg-target to store segmented translations and use the same positional indexes in source
                                        $xliff[ 'files' ][ $i ][ 'trans-units' ][ $j ][ 'seg-target' ][ $k ][ 'mid' ]           = $mt_id[ 1 ];
                                        $xliff[ 'files' ][ $i ][ 'trans-units' ][ $j ][ 'seg-target' ][ $k ][ 'ext-prec-tags' ] = ( $mi == 0 ? $target_markers[ 0 ] : "" ); //put the first tags of seg-source structure

                                        $mark_string = preg_replace( '#^<mrk\s[^>]+>(.*)#', '$1', $originalTransMark );
                                        $mark_content = preg_split( '#</mrk>#si', $mark_string );

                                        if( isset( $mark_content[ 1 ] ) ){
                                            $xliff[ 'files' ][ $i ][ 'trans-units' ][ $j ][ 'seg-target' ][ $k ][ 'raw-content' ]   = $mark_content[0];
                                            $xliff[ 'files' ][ $i ][ 'trans-units' ][ $j ][ 'seg-target' ][ $k ][ 'ext-succ-tags' ] = $mark_content[1];
                                        } else {
                                            $xliff[ 'files' ][ $i ][ 'trans-units' ][ $j ][ 'seg-target' ][ $k ][ 'raw-content' ]   = "";
                                            $xliff[ 'files' ][ $i ][ 'trans-units' ][ $j ][ 'seg-target' ][ $k ][ 'ext-succ-tags' ] = $mark_content[0];
                                        }

                                    }

                                }

                                $mi++;
								$k++;

							}
						}
					}

						$j++;

				} // End of trans-units
                $total_trans_units_id = count( $trans_unit_id_array_for_uniqueness_check );
				$trans_units_unique_id = count( array_unique( $trans_unit_id_array_for_uniqueness_check ) );
				if( $total_trans_units_id != $trans_units_unique_id ){
                    throw new DomainException( "Invalid trans-unit id, duplicate found.", 400 );
                }
			} // End of files

			$i++;
		}

		return $xliff;
	}


    /**
     * This function exists because many developers started adding html tags directly into the XLIFF source since:
     * 1) XLIFF tag remapping is too complex for them
     * 2) Trados does not lock Tags within the <source> that are expressed as &gt;b&lt; but is tolerant to html tags in <source>
     *
     * in short people typed:
     * <source>The <b>red</d> house</source> or worst <source>5 > 3</source>
     * instead of
     * <source>The <g id="1">red</g> house.</source> and <source>5 &gt; 3</source>
     *
     * This function will do the following
     * <g id="1">Hello</g>, 4 > 3 -> <g id="1">Hello</g>, 4 &gt; 3
     * <g id="1">Hello</g>, 4 > 3 &gt; -> <g id="1">Hello</g>, 4 &gt; 3 &gt; 2
     *
     * @param $content string
     *
     * @return mixed|string
     */
	public static function fix_non_well_formed_xml( $content ) {

		if (self::$find_xliff_tags_reg === null) {
			// List of the tags that we don't want to escape
			$xliff_tags = array('g', 'x', 'bx', 'ex', 'bpt', 'ept', 'ph', 'it', 'mrk');
			// Convert the list of tags in a regexp list, for example "g|x|bx|ex"
			$xliff_tags_reg_list = implode('|', $xliff_tags);
			// Regexp to find all the XLIFF tags:
			//   </?               -> matches the tag start, for both opening and
			//                        closure tags (see the optional slash)
			//   ($xliff_tags_reg) -> matches one of the XLIFF tags in the list above
			//   (\s[^>]*)?        -> matches attributes and so on; ensures there's a
			//                        space after the tag, to not confuse for example a
			//                        "g" tag with a "gblabla"; [^>]* matches anything,
			//                        including additional spaces; the entire block is
			//                        optional, to allow tags with no spaces or attrs
			//   /? >              -> matches tag end, with optional slash for
			//                        self-closing ones
			// If you are wondering about spaces inside tags, look at this:
			//   http://www.w3.org/TR/REC-xml/#sec-starttags
			// It says that there cannot be any space between the '<' and the tag name,
			// between '</' and the tag name, or inside '/>'. But you can add white
			// space after the tag name, though.
			self::$find_xliff_tags_reg = "#</?($xliff_tags_reg_list)(\\s[^>]*)?/?>#si";
		}

		// Find all the XLIFF tags
		preg_match_all(self::$find_xliff_tags_reg, $content, $matches);
		$tags = (array) $matches[0];

		// Prepare placeholders
		$tags_placeholders = array();
		for ($i = 0; $i < count($tags); $i++) {
			$tag = $tags[$i];
			$tags_placeholders[$tag] = "#@!XLIFF-TAG-$i!@#";
		}

		// Replace all XLIFF tags with placeholders that will not be escaped
		foreach ($tags_placeholders as $tag => $placeholder) {
			$content = str_replace($tag, $placeholder, $content);
		}

		// Escape the string with the remaining non-XLIFF tags
		$content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8', false);

		// Put again in place the original XLIFF tags replacing placeholders
		foreach ($tags_placeholders as $tag => $placeholder) {
			$content = str_replace($placeholder, $tag, $content);
		}

		return $content;
		
	}

    private function evalNotes( &$xliff, $i, $j, $trans_unit ) {
        $temp = null;
        preg_match_all( '|<note.*?>(.+?)</note>|si', $trans_unit, $temp );
        $matches = array_values( $temp[ 1 ] );
        if ( count( $matches ) > 0 ) {
            foreach ( $matches as $match ) {

                $note = [];
                if ( $this->isJSON( $match ) ) {
                    $note[ 'json' ] = $this->cleanCDATA( $match );
                } else {
                    $note[ 'raw-content' ] = $this->fix_non_well_formed_xml( $match );
                }

                $xliff[ 'files' ][ $i ][ 'trans-units' ][ $j ][ 'notes' ][] = $note;

            }
        }
    }

    private function isJSON( $noteField ){
        try {
            $noteField = $this->cleanCDATA( $noteField );
            if( empty( $noteField ) ) throw new Exception();
            json_decode( $noteField );
            Utils::raiseJsonExceptionError();
        } catch ( Exception $exception ){
            return false;
        }
        return true;
    }

    private function cleanCDATA( $testString ){
        $cleanXMLContent = new SimpleXMLElement( '<rootNoteNode>' . $testString . '</rootNoteNode>', LIBXML_NOCDATA );
        return $cleanXMLContent->__toString();
    }

    /**
     * @param $xliff
     * @param $i
     * @param $j
     * @param $trans_unit
     */
    private function getSource( &$xliff, $i, $j, $trans_unit ) {
        try {
            $tagContent = $this->_getTagContent( 'source', $trans_unit );
            $xliff[ 'files' ][ $i ][ 'trans-units' ][ $j ][ 'source' ][ 'raw-content' ] = self::fix_non_well_formed_xml( $tagContent );
        } catch( UnexpectedValueException $e ){
            //Found Empty Source Tag
            Log::doLog( $e->getMessage() );
        }
    }

    /**
     * @param $xliff
     * @param $i
     * @param $j
     * @param $trans_unit
     */
    private function getTarget( &$xliff, $i, $j, $trans_unit ) {
        try {
            $tagContent = $this->_getTagContent( 'target', $trans_unit );
            $xliff[ 'files' ][ $i ][ 'trans-units' ][ $j ][ 'target' ][ 'raw-content' ] = self::fix_non_well_formed_xml( $tagContent );
        } catch( UnexpectedValueException $e ){
            //Found Empty Target Tag
            Log::doLog( $e->getMessage() );
        }
    }

    /**
     * Extract tag content using DOM ( fallback to old implementation on failure )
     *
     * @param $tagName
     * @param $trans_unit
     *
     * @return string
     * @throws UnexpectedValueException
     */
    private function _getTagContent( $tagName, $trans_unit ) {

        /**
         * Rebuild the trans-unit tag integrity after preg_split
         */
        $trans_unit = '<trans-unit ' . explode( '</trans-unit>', $trans_unit )[ 0 ] . '</trans-unit>';

        libxml_use_internal_errors( true );
        $dDoc          = new DOMDocument();
        $trg_xml_valid = @$dDoc->loadXML( "<root>$trans_unit</root>", LIBXML_NOENT | LIBXML_COMPACT | LIBXML_NOEMPTYTAG );

        /**
         * Get all tags by their name
         */
        $tagList = $dDoc->getElementsByTagName( $tagName );

        if ( $trg_xml_valid === false ) {

            $errorList = libxml_get_errors();
            Log::doLog( "Invalid target found, fallback to old implementation to get the content by regular expression" );
            Log::doLog( "<trans-unit $trans_unit" );
            Log::doLog( $errorList );

            //fallback to old regexp wrong implementation
            $regexp = "|<{$tagName}[^>]*?>(.*?)</{$tagName}>|si";

            preg_match( $regexp, $trans_unit, $temp );
            $tmpTag = $temp[ 1 ];

        } elseif ( $tagList->length ) {

            $tmpTag = '';
            foreach ( $tagList as $_tag ) {
                /** @var $_tag DOMElement|DOMNode */
                $childNodes = $_tag->hasChildNodes();

                //<alt-trans> has also <source> and <target> tags inside, we want only those of the <trans-unit>
                if ( $_tag->parentNode->nodeName == 'trans-unit' && !empty( $childNodes ) ) {

                    //Loop on the child nodes, saveXML concatenation
                    foreach ( $_tag->childNodes as $node ) {
                        $tmpTag .= $dDoc->saveXML( $node );
                    }

                }
            }

        }

        if ( empty( $tmpTag ) ) {
            throw new UnexpectedValueException( "The content of the tag $tagName is empty." );
        }

        return $tmpTag;

    }

    private function getSDLStatus( &$xliff, $i, $j, $trans_unit ){
        //['attr']['translate']
        //<sdl:seg id="4" locked="true"
        preg_match( '|<sdl:seg.*?(locked).*?>.+?</sdl:seg>|si', $trans_unit, $temp );
        if ( isset( $temp[ 1 ] ) ) {
            $xliff[ 'files' ][ $i ][ 'trans-units' ][ $j ][ 'attr' ][ 'locked' ] = true;
        }
    }

}
