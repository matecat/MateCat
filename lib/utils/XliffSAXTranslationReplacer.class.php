<?
include_once INIT::$UTILS_ROOT."/cat.class.php";
include_once INIT::$UTILS_ROOT . '/QA.php';
class XliffSAXTranslationReplacer{

	private $filename; //source filename
	private $inTU=false;//flag to check wether we are in a <trans-unit>
	private $inTarget=false;//flag to check wether we are in a <target>, to ignore everything
	private $isEmpty=false; //flag to check wether we are in an empty tag (<tag/>)
	private $offset=0;//offset for SAX pointer
	private $ofp;//output stream pointer
	private $currentBuffer;//the current piece of text it's been parsed
	private $len;//length of the currentBuffer
	private $segments; //array of translations
	private $currentId;//id of current <trans-unit>
	private $empty_tags=array('detected-source-lang','fmt','sdl:cxt','cxt','sdl:node','sdl:ref-file','ref-file','sdl:seg','seg','x');
	private $regular_tags=array('body','bpt','bpt-props','cxt-def','cxt-defs','ept','sdl:filetype-id','filetype-id','file','file-info','fmt-def','fmt-defs','g','group','header','internal-file','mrk','ph','props','reference','sdl:cxts','cxts','sdl:filetype','filetype','sdl:filetype-info','filetype-info','sdl:ref-files','ref-files','sdl:seg-defs','seg-defs','seg-source','sniff-info','source','st','tag','tag-defs','target','trans-unit','value','xliff');

    private $target_lang;

	public function __construct( $filename,$segments, $trg_lang = null ){
		$this->filename=$filename;
		$this->ofp=fopen($this->filename.'.out.sdlxliff','w');
		$this->segments=$segments;
        $this->target_lang = $trg_lang;
	}

	/*
	   public function __destruct(){
	   fclose($this->ofp);
	   }
	 */


	public function replaceTranslation(){
		//open file
		if (!($fp = fopen($this->filename, "r"))) {
			die("could not open XML input");
		}
		//write xml header
		fwrite($this->ofp,'<?xml version="1.0" encoding="utf-8"?>');

		//create parser
		$xml_parser = xml_parser_create();
		//configure parser
		//pass this object to parser to make its variables and functions visible inside callbacks 
		xml_set_object($xml_parser,$this);
		//avoid uppercasing all tags name
		xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, false);
		//define callbacks for tags
		xml_set_element_handler($xml_parser, "tagOpen", "tagClose");
		//define callback for data
		xml_set_character_data_handler($xml_parser, "characterData");

		//read a chunk of text
		while ($this->currentBuffer = fread($fp, 4096)) {
			/*
			   preprocess file
			 */
			$temporary_check_buffer = preg_replace("/&(.*?);/", '#escaped_ent#$1##', $this->currentBuffer);

			//avoid cutting entities in half: 
			//the last fread could have truncated an entity (say, '&lt;' in '&l'), thus invalidating the escaping
			while(strpos($temporary_check_buffer,'&')!==FALSE){
				//if an entity is still present, fetch some more and repeat the escaping
				$this->currentBuffer.=fread($fp,64);
				$temporary_check_buffer = preg_replace("/&(.*?);/", '#escaped_ent#$1##', $this->currentBuffer);
			}
			//free stuff outside the loop
			unset($temporary_check_buffer);
			//get lenght of chunk
			$this->len=strlen($this->currentBuffer);

			$this->currentBuffer = preg_replace("/&(.*?);/", '#escaped_ent#$1##', $this->currentBuffer);

			//parse chunk of text
			if (!xml_parse($xml_parser, $this->currentBuffer, feof($fp))) {
				//if unable, die
				die(sprintf("XML error: %s at line %d",
							xml_error_string(xml_get_error_code($xml_parser)),
							xml_get_current_line_number($xml_parser)));
			}
			//get accumulated this->offset in document: as long as SAX pointer advances, we keep track of total bytes it has seen so far; this way, we can translate its global pointer in an address local to the current buffer of text to retrieve last char of tag
			$this->offset+=$this->len;
		}
		//close parser
		xml_parser_free($xml_parser);
		//close file pointer
		fclose($fp);
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
			$this->inTarget=true;
		}
		//<target> must be stripped to be replaced, so this check avoids <target> reconstruction
		if(!$this->inTarget){

			//costruct tag
			$tag="<$name ";
			foreach($attr as $k=>$v){

                //if tag name is file, we must replace the target-language attribute
                if( $name == 'file' && $k == 'target-language' && !empty($this->target_lang) ){
                    //replace Target language with job language provided from constructor
				    $tag.="$k=\"$this->target_lang\" ";
                    //Log::doLog($k . " => " . $this->target_lang);
                } else {
                    //put attributes in it
                    $tag.="$k=\"$v\" ";
                }

			}

			//this logic helps detecting empty tags
			//get current position of SAX pointer in all the stream of data is has read so far: it points at the end of current tag
			$idx = xml_get_current_byte_index($parser);
			//check wether the bounds of current tag are entirely in current buffer or the end of the current tag is outside current buffer (in the latter case, it's in next buffer to be read by the while loop); this check is necessary because we may have truncated a tag in half with current read, and the other half may be encountered in the next buffer it will be passed
			if (isset($this->currentBuffer[$idx-$this->offset])) {
				//if this tag entire lenght fitted in the buffer, the last char must be the last symbol before the '>'; if it's an empty tag, it is assumed that it's a '/'
				$tmp_offset=$idx-$this->offset;
			} else {
				//if it's out, simple use the last character of the chunk
				$tmp_offset=$this->len-1;
			}

			//detect empty tag
			//if last char of tag is a backslash (or it's a well known empty tag), it's an empty tag
			if(in_array($name,$this->empty_tags) and !in_array($name,$this->regular_tags)){
				$this->isEmpty=true;
				//add the slash to the tag
				$tag.='/';
			}

			//trim last space
			$tag=rtrim($tag);
			//add tag ending
			$tag.=">";
			//flush to pointer
			$this->postProcAndflush($this->ofp,$tag);
		}
	}

	/*
	   callback for tag close event
	 */
	private function tagClose($parser, $name){

		//if it is an empty tag, do not add closing tag
		if(!$this->isEmpty){
			$tag='';
			if(!$this->inTarget){
				//add ending tag
				$tag="</$name>";
			}
			//if it's a source and there is a translation available, append the target to it
			if('target'==$name){
				if(isset($this->segments[$this->currentId])){
					//get translation of current segment, by indirect indexing: id -> positional index -> segment
					//actually there may be more that one segment to that ID if there are two mrk of the same source segment
					$id_list=$this->segments[$this->currentId];

					//init translation
					$translation='';
					foreach($id_list as $id){
						$seg=$this->segments[$id];
						//add xliff markup, appending multiple MRKs
						$translation=$this->prepareSegment($seg,$translation);
					}
					//append translation
					$tag="<target>$translation</target>";
				}
				//signal we are leaving a target
				$this->inTarget=false;
			}
			//flush to pointer
			$this->postProcAndflush($this->ofp,$tag);
		}
		else{
			//ok, nothing to be done; reset flag for next coming tag
			$this->isEmpty=false;
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
		if(!$this->inTarget){

			//flush to pointer
			$this->postProcAndflush($this->ofp,$data);
		}
	}

	/*
	   postprocess escaped data and write to disk
	 */
	private function postProcAndFlush($fp,$data){
		//postprocess string
		$data = preg_replace("/#escaped_ent#(.*?)##/", '&$1;', $data);
		$data=str_replace('&nbsp;',' ',$data);
		$data=str_replace("\r\n","\r",$data);
		$data=str_replace("\n","\r",$data);
		$data=str_replace("\r","\r\n",$data);
		//flush to disk
		fwrite($fp,$data);	
	}

	/*
	   prepare segment tagging for xliff insertion
	 */
	private function prepareSegment($seg,$transunit_translation = ""){
		$end_tags = "";

		$seg ['segment'] = CatUtils::restorenbsp ( $seg ['segment'] );
		$seg ['translation'] = CatUtils::restorenbsp ( $seg ['translation'] );
                
                $seg ['segment'] = CatUtils::restore_xml_entities ( $seg ['segment'] );
		$seg ['translation'] = CatUtils::restore_xml_entities ( $seg ['translation'] );


        //QA non sense for source/source check until source can be changed. For now SKIP
		if (is_null ( $seg ['translation'] ) || $seg ['translation'] == '') {
			$translation = $seg ['segment'];
		} else {
			$translation = $seg ['translation'];

            //consistency check
            $check = new QA ( $seg ['segment'], $translation );
            $check->performTagCheckOnly ();
            if( $check->thereAreErrors() ){
                $translation = '|||UNTRANSLATED_CONTENT_START|||' . $seg ['segment'] . '|||UNTRANSLATED_CONTENT_END|||';
                //log::doLog("tag mismatch on\n".print_r($seg,true)."\n(because of: ".print_r( $check->getErrors(), true ).")");
            }

		}

		//fix to escape non-html entities
		$translation = str_replace("&lt;", '#LT#', $translation);
		$translation = str_replace("&gt;", '#GT#', $translation);
		$translation = str_replace("&amp;", '#AMP#', $translation);
		$translation = html_entity_decode($translation,ENT_NOQUOTES,"utf-8");
		$translation = str_replace('#AMP#','&amp;', $translation);
		$translation = str_replace('#LT#','&lt;', $translation);
		$translation = str_replace('#GT#','&gt;', $translation);

		@$xml_valid = simplexml_load_string("<placeholder>$translation</placeholder>");
		if (!$xml_valid) {
			$temp = preg_split("|\<|si", $translation);
			$item = end($temp);
			if (preg_match('|/.*?>\W*$|si', $item)) {
				$end_tags.="<$item";
			}
			while ($item = prev($temp)) {
				if (preg_match('|/.*?>\W*$|si', $item)) {
					$end_tags = "<$item$end_tags"; //insert at the top of the string
				}
			}
			$translation = str_replace($end_tags, "", $translation);
		}

		if (!empty($seg['mrk_id'])) {
			$translation = "<mrk mtype=\"seg\" mid=\"" . $seg['mrk_id'] . "\">".$seg['mrk_prev_tags'].$translation.$seg['mrk_succ_tags']."</mrk>";
		}
		$transunit_translation.=$seg['prev_tags'] . $translation . $end_tags . $seg['succ_tags'];
		return $transunit_translation;
	}

}
?>
