<?
include_once INIT::$UTILS_ROOT."/CatUtils.php";
include_once INIT::$UTILS_ROOT . '/QA.php';
class XliffSAXTranslationReplacer{

	private $filename; //source filename
    private $originalFP;

	private $inTU=false;//flag to check wether we are in a <trans-unit>
	private $inTarget=false;//flag to check wether we are in a <target>, to ignore everything
	private $isEmpty=false; //flag to check wether we are in an empty tag (<tag/>)

	private $CDATABuffer = ""; //buffer for special tag
	private $bufferIsActive = false; //buffer for special tag

	private $offset=0;//offset for SAX pointer
	private $outputFP;//output stream pointer
	private $currentBuffer;//the current piece of text it's been parsed
	private $len;//length of the currentBuffer
	private $segments; //array of translations
	private $currentId;//id of current <trans-unit>

    private $target_lang;

    public function __construct( $filename, $segments, $trg_lang = null, $filePointer = null ) {

        $this->filename    = $filename;

        if ( is_resource( $filePointer ) ) {
            $this->originalFP = $filePointer;
            rewind( $this->originalFP );
        } else {
            if ( !( $this->originalFP = fopen( $this->filename, "r" ) ) ) {
                die( "could not open XML input" );
            }
        }

        $this->outputFP    = fopen( $this->filename . '.out.sdlxliff', 'w+' );
        $this->segments    = $segments;
        $this->target_lang = $trg_lang;

    }

    public function __destruct() {
        fclose( $this->originalFP );
        fclose( $this->outputFP );
    }

    public function replaceTranslation() {

        //write xml header
        fwrite( $this->outputFP, '<?xml version="1.0" encoding="utf-8"?>' );

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
            $temporary_check_buffer = preg_replace( "/&(.*?);/", '#%$1#%', $this->currentBuffer );

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
                $temporary_check_buffer = preg_replace( "/&(.*?);/", '#%$1#%', $this->currentBuffer );

            }

            //free stuff outside the loop
            unset( $temporary_check_buffer );

            $this->currentBuffer = preg_replace( "/&(.*?);/", '#%$1#%', $this->currentBuffer );
            if ( $escape_AMP ) {
                $this->currentBuffer = str_replace( "&", '#%amp#%', $this->currentBuffer );
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
	private function tagOpen($parser, $name, $attr){

		//check if we are entering into a <trans-unit>
		if('trans-unit'==$name){
			$this->inTU=true;
			//get id
			$this->currentId=$attr['id'];
		}

        //check if we are entering into a <target>
        if('target'==$name){
            $this->inTarget = true;
        }

        //check if we are inside a <target>, obviously this happen only if there are targets inside the trans-unit
		//<target> must be stripped to be replaced, so this check avoids <target> reconstruction
        if ( !$this->inTarget ) {

			//costruct tag
            $tag = "<$name ";

			foreach( $attr as $k => $v ){

                //if tag name is file, we must replace the target-language attribute
                if( $name == 'file' && $k == 'target-language' && !empty($this->target_lang) ){
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

            //check wether the bounds of current tag are entirely in current buffer or the end of the current tag
            //is outside current buffer (in the latter case, it's in next buffer to be read by the while loop);
            //this check is necessary because we may have truncated a tag in half with current read,
            //and the other half may be encountered in the next buffer it will be passed
            if ( isset( $this->currentBuffer[ $idx - $this->offset ] ) ) {
                //if this tag entire lenght fitted in the buffer, the last char must be the last
                //symbol before the '>'; if it's an empty tag, it is assumed that it's a '/'
                $tmp_offset = $idx - $this->offset;
                $lastChar = $this->currentBuffer[ $idx - $this->offset ];
            } else {
                //if it's out, simple use the last character of the chunk
                $tmp_offset = $this->len - 1;
                $lastChar = $this->currentBuffer[ $this->len - 1 ];
            }

            //trim last space
            $tag = rtrim( $tag );

            //detect empty tag
            $this->isEmpty = ( $lastChar == '/' || $name == 'x' );
            if( $this->isEmpty ){
                $tag .= '/';
            }

			//add tag ending
            $tag .= ">";

            //seta a Buffer for the segSource Source tag
            if( 'source' == $name || 'seg-source' == $name || $this->bufferIsActive ){
                $this->bufferIsActive = true;
                $this->CDATABuffer .= $tag;
            } else {
                $this->postProcAndflush( $this->outputFP, $tag );
            }

		}

	}

	/*
	   callback for tag close event
	 */
	private function tagClose($parser, $name){

        $tag = '';

        /**
         * if it is an empty tag, do not add closing tag because we have already closed it in
         *
         * self::tagOpen method
         *
         */
		if( !$this->isEmpty ){

            if( !$this->inTarget ){
                $tag = "</$name>";
            }

            //if it's a source and there is a translation available, append the target to it
            if ( 'target' == $name ) {

                if ( isset( $this->segments[ 'matecat|' . $this->currentId ] ) ) {
                    //get translation of current segment, by indirect indexing: id -> positional index -> segment
                    //actually there may be more that one segment to that ID if there are two mrk of the same source segment
                    $id_list = $this->segments[ 'matecat|' . $this->currentId ];

                    //init translation
                    $translation = '';
                    foreach ( $id_list as $id ) {
                        $seg = $this->segments[ $id ];
                        //add xliff markup, appending multiple MRKs
                        $translation = $this->prepareSegment( $seg, $translation );
                    }
                    //append translation
                    $tag = "<target>$translation</target>";
                }

                //signal we are leaving a target
                $this->inTarget = false;
                $this->postProcAndflush( $this->outputFP, $tag, $treatAsCDATA = true );

            } elseif ( 'source' == $name || 'seg-source' == $name ) { // we are closing a critical CDATA section

                $this->bufferIsActive = false;
                $tag                  = $this->CDATABuffer . "</$name>";
                $this->CDATABuffer    = "";
                //flush to pointer
                $this->postProcAndflush( $this->outputFP, $tag );

            } elseif ( $this->bufferIsActive ) { // this is a tag ( <g | <mrk ) inside a seg or seg-source tag
                $this->CDATABuffer .= "</$name>";
                //Do NOT Flush

            } else { //generic tag closure do Nothing
                //flush to pointer
                $this->postProcAndflush( $this->outputFP, $tag );
            }


        } else{
			//ok, nothing to be done; reset flag for next coming tag
            $this->isEmpty = false;
		}

        //check if we are leaving a <trans-unit>
		if('trans-unit'==$name){
			$this->inTU=false;
		}

	}

	/*
	   callback for CDATA event
	 */
	private function characterData($parser,$data){
		//don't write <target> data
		if(!$this->inTarget && !$this->bufferIsActive ){

			//flush to pointer
			$this->postProcAndflush($this->outputFP,$data);

		} elseif( $this->bufferIsActive ) {
            $this->CDATABuffer .= $data;
        }

	}

	/*
	   postprocess escaped data and write to disk
	 */
    private function postProcAndFlush( $fp, $data, $treatAsCDATA = false ) {
        //postprocess string
        $data = preg_replace( "/#%(.*?)#%/", '&$1;', $data );
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
	private function prepareSegment($seg,$transunit_translation = ""){
		$end_tags = "";

//		$seg ['segment'] = CatUtils::restorenbsp ( $seg ['segment'] );
//		$seg ['translation'] = CatUtils::restorenbsp ( $seg ['translation'] );

        $seg ['segment'] = CatUtils::view2rawxliff( $seg ['segment'] );
        $seg ['translation'] = CatUtils::view2rawxliff ( $seg ['translation'] );

        //QA non sense for source/source check until source can be changed. For now SKIP
		if (is_null ( $seg ['translation'] ) || $seg ['translation'] == '') {
			$translation = $seg ['segment'];
		} else {

			$translation = $seg ['translation'];
            if( empty($seg['locked']) ){
                //consistency check
                $check = new QA ( $seg ['segment'], $translation );
                $check->performTagCheckOnly ();
                if( $check->thereAreErrors() ){
                    $translation = '|||UNTRANSLATED_CONTENT_START|||' . $seg ['segment'] . '|||UNTRANSLATED_CONTENT_END|||';
                    Log::doLog("tag mismatch on\n".print_r($seg,true)."\n(because of: ".print_r( $check->getErrors(), true ).")");
                }
            }

		}

		if (!empty($seg['mrk_id'])) {
			$translation = "<mrk mtype=\"seg\" mid=\"" . $seg['mrk_id'] . "\">".$seg['mrk_prev_tags'].$translation.$seg['mrk_succ_tags']."</mrk>";
		}

		$transunit_translation.=$seg['prev_tags'] . $translation . $end_tags . $seg['succ_tags'];
		return $transunit_translation;

	}

}
?>
