<?php

class XliffSAXTranslationReplacer {

    protected $originalFP;

    protected $inTU = false;//flag to check whether we are in a <trans-unit>
    protected $inTarget = false;//flag to check whether we are in a <target>, to ignore everything
    protected $isEmpty = false; //flag to check whether we are in an empty tag (<tag/>)

    protected $CDATABuffer = ""; //buffer for special tag
    protected $bufferIsActive = false; //buffer for special tag

    protected $offset = 0;//offset for SAX pointer
    protected $outputFP;//output stream pointer
    protected $currentBuffer;//the current piece of text it's been parsed
    protected $len;//length of the currentBuffer
    protected $segments; //array of translations
    protected $lastTransUnit = [];
    protected $currentId;//id of current <trans-unit>

    protected $target_lang;

    protected $sourceInTarget;

    protected $transUnits ;

    protected static $INTERNAL_TAG_PLACEHOLDER;

    public function __construct( $originalXliffFilename, $segments, $transUnits, $trg_lang = null, $outputFile = null ) {

        self::$INTERNAL_TAG_PLACEHOLDER = "§" .
                substr(
                        str_replace(
                                array( '+', '/' ),
                                '',
                                base64_encode( openssl_random_pseudo_bytes( 10, $_crypto_strong ) )
                        ), 0, 4
                );

        if ( is_resource( $outputFile ) ) {
            $this->outputFP = $outputFile;
            rewind( $this->outputFP );
        } else {
            $this->outputFP = fopen( $outputFile, 'w+' );
        }

        if ( !( $this->originalFP = fopen( $originalXliffFilename, "r" ) ) ) {
            die( "could not open XML input" );
        }

        $this->segments    = $segments;
        $this->target_lang = $trg_lang;
        $this->sourceInTarget = false;
        $this->transUnits = $transUnits ;

    }

    public function __destruct() {
        //this stream can be closed outside the class
        //to permit multiple concurrent downloads, so suppress warnings
        @fclose( $this->originalFP );
        fclose( $this->outputFP );
    }

    /**
     * @param boolean $emptyTarget
     */
    public function setSourceInTarget( $emptyTarget ) {
        $this->sourceInTarget = $emptyTarget;
    }

    public function replaceTranslation() {

        //write xml header
        fwrite( $this->outputFP, '<?xml version="1.0" encoding="UTF-8"?>' );

        //create parser
        $xml_parser = xml_parser_create( 'UTF-8' );

        //configure parser
        //pass this object to parser to make its variables and functions visible inside callbacks
        xml_set_object( $xml_parser, $this );
        //avoid uppercasing all tags name
        xml_parser_set_option( $xml_parser, XML_OPTION_CASE_FOLDING, false );
        //define callbacks for tags
        xml_set_element_handler( $xml_parser, "tagOpen", "tagClose" );
        //define callback for data
        xml_set_character_data_handler( $xml_parser, "characterData" );

        //read a chunk of text
        while ( $this->currentBuffer = fread( $this->originalFP, 4096 ) ) {
            /*
               preprocess file
             */
            // obfuscate entities because sax automatically does html_entity_decode
             $temporary_check_buffer = preg_replace( "/&(.*?);/", self::$INTERNAL_TAG_PLACEHOLDER . '$1' . self::$INTERNAL_TAG_PLACEHOLDER, $this->currentBuffer );

            $lastByte = $temporary_check_buffer[ strlen( $temporary_check_buffer ) - 1 ];

            //avoid cutting entities in half:
            //the last fread could have truncated an entity (say, '&lt;' in '&l'), thus invalidating the escaping
            //***** and if there is an & that it is not an entity, this is an infinite loop !!!!!

            $escape_AMP = false;

            // 9 is the max length of an entity. So, suppose that the & is at the end of buffer,
            // add 9 Bytes and substitute the entities, if the & is present and it is not at the end
            //it can't be a entity, exit the loop

            while ( true ) {

                $_ampPos = strpos( $temporary_check_buffer, '&' );

                //check for real entity or escape it to safely exit from the loop!!!
                if ( $_ampPos === false || strlen( substr( $temporary_check_buffer, $_ampPos ) ) > 9 ) {
                    $escape_AMP = true;
                    break;
                }

                //if an entity is still present, fetch some more and repeat the escaping
                $this->currentBuffer .= fread( $this->originalFP, 9 );
                $temporary_check_buffer = preg_replace( "/&(.*?);/", self::$INTERNAL_TAG_PLACEHOLDER . '$1' . self::$INTERNAL_TAG_PLACEHOLDER, $this->currentBuffer );

            }

            //free stuff outside the loop
            unset( $temporary_check_buffer );

            $this->currentBuffer = preg_replace( "/&(.*?);/", self::$INTERNAL_TAG_PLACEHOLDER . '$1' . self::$INTERNAL_TAG_PLACEHOLDER, $this->currentBuffer );
            if ( $escape_AMP ) {
                $this->currentBuffer = str_replace( "&", self::$INTERNAL_TAG_PLACEHOLDER . 'amp' . self::$INTERNAL_TAG_PLACEHOLDER, $this->currentBuffer );
            }

            //get lenght of chunk
            $this->len = strlen( $this->currentBuffer );

            //parse chunk of text
            if ( !xml_parse( $xml_parser, $this->currentBuffer, feof( $this->originalFP ) ) ) {
                //if unable, die
                die( sprintf( "XML error: %s at line %d",
                        xml_error_string( xml_get_error_code( $xml_parser ) ),
                        xml_get_current_line_number( $xml_parser ) ) );
            }
            //get accumulated this->offset in document: as long as SAX pointer advances, we keep track of total bytes it has seen so far; this way, we can translate its global pointer in an address local to the current buffer of text to retrieve last char of tag
            $this->offset += $this->len;
        }
        //close parser
        xml_parser_free( $xml_parser );

    }


    /*
       callback for tag open event
     */
    protected function tagOpen( $parser, $name, $attr ) {

        //check if we are entering into a <trans-unit>
        if ( 'trans-unit' == $name ) {
            $this->inTU = true;
            //get id
            $this->currentId = $attr[ 'id' ];
        }

        //check if we are entering into a <target>
        if ( 'target' == $name ) {
            $this->inTarget = true;
        }

        //check if we are inside a <target>, obviously this happen only if there are targets inside the trans-unit
        //<target> must be stripped to be replaced, so this check avoids <target> reconstruction
        if ( !$this->inTarget ) {

            //costruct tag
            $tag = "<$name ";

            foreach ( $attr as $k => $v ) {

                //if tag name is file, we must replace the target-language attribute
                if ( $name == 'file' && $k == 'target-language' && !empty( $this->target_lang ) ) {
                    //replace Target language with job language provided from constructor
                    $tag .= "$k=\"$this->target_lang\" ";
                    //Log::doLog($k . " => " . $this->target_lang);
                } else {
                    //put attributes in it
                    $tag .= "$k=\"$v\" ";
                }

            }

            //this logic helps detecting empty tags
            //get current position of SAX pointer in all the stream of data is has read so far:
            //it points at the end of current tag
            $idx = xml_get_current_byte_index( $parser );

            //check whether the bounds of current tag are entirely in current buffer or the end of the current tag
            //is outside current buffer (in the latter case, it's in next buffer to be read by the while loop);
            //this check is necessary because we may have truncated a tag in half with current read,
            //and the other half may be encountered in the next buffer it will be passed
            if ( isset( $this->currentBuffer[ $idx - $this->offset ] ) ) {
                //if this tag entire lenght fitted in the buffer, the last char must be the last
                //symbol before the '>'; if it's an empty tag, it is assumed that it's a '/'
                $tmp_offset = $idx - $this->offset;
                $lastChar   = $this->currentBuffer[ $idx - $this->offset ];
            } else {
                //if it's out, simple use the last character of the chunk
                $tmp_offset = $this->len - 1;
                $lastChar   = $this->currentBuffer[ $this->len - 1 ];
            }

            //trim last space
            $tag = rtrim( $tag );

            //detect empty tag
            $this->isEmpty = ( $lastChar == '/' || $name == 'x' );
            if ( $this->isEmpty ) {
                $tag .= '/';
            }

            //add tag ending
            $tag .= ">";

            //seta a Buffer for the segSource Source tag
            if ( 'source' == $name
                    || 'seg-source' == $name
                    || $this->bufferIsActive
                    || 'value' == $name
                    || 'bpt' == $name
                    || 'ept' == $name
                    || 'ph' == $name
                    || 'st' == $name
                    || 'note' == $name ) {

                //WARNING BECAUSE SOURCE AND SEG-SOURCE TAGS CAN BE EMPTY IN SOME CASES!!!!!
                //so check for isEmpty also in conjunction with name
                if( $this->isEmpty && ( 'source' == $name || 'seg-source' == $name ) ) {
                    $this->postProcAndFlush( $this->outputFP, $tag );

                } else {
                    //these are NOT source/seg-source/value empty tags, THERE IS A CONTENT, write it in buffer
                    $this->bufferIsActive = true;
                    $this->CDATABuffer .= $tag;
                }

            } else {
                $this->postProcAndFlush( $this->outputFP, $tag );
            }

        }

    }

    /*
       callback for tag close event
     */
    protected function tagClose( $parser, $name ) {

        $tag = '';

        /**
         * if it is an empty tag, do not add closing tag because we have already closed it in
         *
         * self::tagOpen method
         *
         */
        if ( !$this->isEmpty ) {

            if ( !$this->inTarget ) {
                $tag = "</$name>";
            }

            //if it's a source and there is a translation available, append the target to it
            if ( 'target' == $name ) {

                if ( isset( $this->transUnits[ $this->currentId ] ) ) {
                    // get translation of current segment, by indirect indexing: id -> positional index -> segment
                    // actually there may be more that one segment to that ID if there are two mrk of the same source segment
                    $list_of_ids = $this->transUnits[ $this->currentId ] ;

                    /*
                     * At the end of every cycle the segment grouping information is lost: unset( 'matecat|' . $this->currentId )
                     *
                     * We need to take the info about the last segment parsed
                     *          ( normally more than 1 db row because of mrk tags )
                     *
                     * So, copy the current segment data group to an another structure to take the last one segment
                     * for the next tagOpen ( possible sdl:seg-defs )
                     *
                     */

                    $this->lastTransUnit = array();

                    $warning = false;
                    $last_value = null;
                    for( $i = 0; $i < count( $list_of_ids ) ; $i++ ) {
                        if( isset( $list_of_ids[ $i ] ) ){
                            $id = $list_of_ids[ $i ] ;
                            if( isset( $this->segments[ $id ] ) && ( $i == 0 || $last_value + 1 == $list_of_ids[ $i ] ) ){
                                $last_value = $list_of_ids[ $i ];
                                $this->lastTransUnit[] = $this->segments[ $id ] ;
                            }
                        } else {
                            $warning = true;
                        }
                    }

                    if( $warning ){
                        $old_fname = Log::$fileName;
                        Log::$fileName = "XliffSax_Polling.log";
                        Log::doLog( "WARNING: PHP Notice polling. CurrentId: '" . $this->currentId . "' - Filename: '" . $this->segments[ 0 ][ 'filename' ] . "' - First Segment: '" . $this->segments[ 0 ][ 'sid' ] . "'" );
                        Log::$fileName = $old_fname;
                    }

                    // init translation
                    $translation = '';

                    // we must reset the lastMrkId found because this is a new segment.
                    $lastMrkId = -1;

                    foreach ( $list_of_ids as $pos => $id ) {

                        /*
                         * This routine works to respect the positional orders of markers.
                         * In every cycle we check if the mrk of the segment is below or equal the last one.
                         * When this is true, means that the mrk id belongs to the next segment with the same internal_id
                         * so we MUST stop to apply markers and translations
                         *
                         * Begin:
                         * pre-assign zero to the new mrk if this is the first one ( in this segment )
                         * If it is null leave it NULL
                         */
                        if ( (int) $this->segments[ $id ][ "mrk_id" ] < 0 && $this->segments[ $id ][ "mrk_id" ] !== null ) {
                            $this->segments[ $id ][ "mrk_id" ] = 0;
                        }

                        /*
                         * WARNING:
                         * For those seg-source that does'nt have a mrk ( having a mrk id === null )
                         * ( null <= -1 ) === true
                         * so, cast to int
                         */
                        if( (int) $this->segments[ $id ][ "mrk_id" ] <= $lastMrkId ) {
                            break;
                        }

                        $seg = $this->segments[ $id ];

                        //delete translations so the prepareSegment
                        // will put source content in target tag
                        if( $this->sourceInTarget ){
                            $seg['translation'] = '';
                        }

                        $translation = $this->prepareSegment( $seg, $translation );

                        /*
                         * WARNING: this unset is needed to manage the duplicated Trans-unit IDs
                         *
                         */
                        unset(  $this->transUnits[ $this->currentId ] [ $pos ] ) ;

                        $lastMrkId = $this->segments[ $id ][ "mrk_id" ];

                    }

                    //append translation
                    $tag = "<target xml:lang=\"" . strtolower( $this->target_lang ) . "\">$translation</target>";
                }

                //signal we are leaving a target
                $this->inTarget = false;
                $this->postProcAndFlush( $this->outputFP, $tag, $treatAsCDATA = true );

            } elseif ( 'source' == $name
                    || 'seg-source' == $name
                    || 'value' == $name
                    || 'bpt' == $name
                    || 'ept' == $name
                    || 'st' == $name
                    || 'note' == $name ) { // we are closing a critical CDATA section

                $this->bufferIsActive = false;
                $tag                  = $this->CDATABuffer . "</$name>";
                $this->CDATABuffer    = "";
                //flush to pointer
                $this->postProcAndFlush( $this->outputFP, $tag );

            } elseif ( $this->bufferIsActive ) { // this is a tag ( <g | <mrk ) inside a seg or seg-source tag
                $this->CDATABuffer .= "</$name>";
                //Do NOT Flush

            } else { //generic tag closure do Nothing
                //flush to pointer
                $this->postProcAndFlush( $this->outputFP, $tag );
            }


        } else {
            //ok, nothing to be done; reset flag for next coming tag
            $this->isEmpty = false;
        }

        //check if we are leaving a <trans-unit>
        if ( 'trans-unit' == $name ) {
            $this->inTU = false;
        }

    }

    /*
       callback for CDATA event
     */
    protected function characterData( $parser, $data ) {
        //don't write <target> data
        if ( !$this->inTarget && !$this->bufferIsActive ) {

            //flush to pointer
            $this->postProcAndFlush( $this->outputFP, $data );

        } elseif ( $this->bufferIsActive ) {
            $this->CDATABuffer .= $data;
        }

    }

    /*
       postprocess escaped data and write to disk
     */
    protected function postProcAndFlush( $fp, $data, $treatAsCDATA = false ) {
        //postprocess string
        $data = preg_replace( "/" . self::$INTERNAL_TAG_PLACEHOLDER . '(.*?)' . self::$INTERNAL_TAG_PLACEHOLDER . "/", '&$1;', $data );
        $data = str_replace( '&nbsp;', ' ', $data );
        if ( !$treatAsCDATA ) {
            //unix2dos
            $data = str_replace( "\r\n", "\r", $data );
            $data = str_replace( "\n", "\r", $data );
            $data = str_replace( "\r", "\r\n", $data );
        }
        //flush to disk
        fwrite( $fp, $data );
    }

    /*
       prepare segment tagging for xliff insertion
     */
    protected function prepareSegment( $seg, $trans_unit_translation = "" ) {
        $end_tags = "";

        //We don't need transform/sanitize from wiew to xliff because the values comes from Database
        //QA non sense for source/source check, until source can be changed. For now SKIP
        if ( is_null( $seg [ 'translation' ] ) || $seg [ 'translation' ] == '' ) {
            $translation = $seg [ 'segment' ];
        } else {

            $translation = $seg [ 'translation' ];
            if ( empty( $seg[ 'locked' ] ) ) {
                //consistency check
                $check = new QA ( $seg [ 'segment' ], $translation );
                $check->performTagCheckOnly();
                if ( $check->thereAreErrors() ) {
                    $translation = '|||UNTRANSLATED_CONTENT_START|||' . $seg [ 'segment' ] . '|||UNTRANSLATED_CONTENT_END|||';
                    Log::doLog( "tag mismatch on\n" . print_r( $seg, true ) . "\n(because of: " . print_r( $check->getErrors(), true ) . ")" );
                }
            }

        }

        if ( $seg['mrk_id'] !== null && $seg['mrk_id'] != '' ) {
            $translation = "<mrk mid=\"" . $seg['mrk_id'] . "\" mtype=\"seg\">" . $seg['mrk_prev_tags'] . $translation . $seg['mrk_succ_tags'] . "</mrk>";
        }

        $trans_unit_translation .= $seg[ 'prev_tags' ] . $translation . $end_tags . $seg[ 'succ_tags' ];

        return $trans_unit_translation;

    }

}
