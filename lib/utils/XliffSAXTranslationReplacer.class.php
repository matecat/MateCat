<?

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

	public function __construct($filename,$segments){
		$this->filename=$filename;
		$this->ofp=fopen($this->filename.'.out.sdlxliff','w');
		$this->segments=$segments;
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
				//log::doLog("split entity detected: $uffa");
				$this->currentBuffer.=fread($fp,64);
				$temporary_check_buffer = preg_replace("/&(.*?);/", '#escaped_ent#$1##', $this->currentBuffer);
			}
			//free stuff outside the loop
			unset($temporary_check_buffer);
			//get lenght of chunk
			$this->len=strlen($this->currentBuffer);

			//file_put_contents($this->filename.'escaped.xliff',$uffa,FILE_APPEND);
			/*
			if('&'==substr($uffa,0,1)){
				log::doLog("string $uffa");
				if($uffa==$this->currentBuffer)log::doLog("failure in escaping, from\n".$this->currentBuffer."\n\nto\n\n$uffa");
			}
			*/
			$this->currentBuffer = preg_replace("/&(.*?);/", '#escaped_ent#$1##', $this->currentBuffer);

			/*		
					$this->currentBuffer = str_replace("&gt;/", '#escaped_ent#gt##', $this->currentBuffer);
					$this->currentBuffer = str_replace("&lt;/", '#escaped_ent#lt##', $this->currentBuffer);
			 */

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
				//put attributes in it
				$tag.="$k=\"$v\" ";
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

			//avoid getting wrong about closing tags
			/*
			while(true){
				//pick last char before tag closing
				$currChar=$this->currentBuffer[$tmp_offset];
				//if it's not a char, slide back of 1 char the pointer
				if(''==trim($currChar)) $tmp_offset--;
				//else, proceed
				else break;
			}
			*/
			//detect empty tag
			//if last char of tag is a backslash (or it's a well known empty tag), it's an empty tag
			if(/*'/'==$currChar and*/ (in_array($name,$this->empty_tags) and !in_array($name,$this->regular_tags))){
				$this->isEmpty=true;
				//add the slash to the tag
				$tag.='/';
			}
			//log::doLog("$name is ".(($this->isEmpty)?'':'not ')."empty");

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

			/*
			   commented for fix

			//don't know why, but outside translation units stuff is html-encoded
			if(!$this->inTU){
			//encode entities
			$data=htmlentities($data,ENT_NOQUOTES);
			}
			 */
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
		//log::doLog($this->currentId. " INPUT t1 : $transunit_translation\n\n");
		$end_tags = "";
		//echo "t1 : ".$seg['translation']."\n";
		//consistency check
		$tag_mismatch=false;
		$outcome=Utils::checkTagConsistency($seg['segment'],$seg['translation']);
		if($outcome['outcome']>0){
			$tag_mismatch=true;
			log::doLog("tag mismatch on\n".print_r($seg,true)."\n(because of: ".$outcome['debug'].")");
		}
		if(empty($seg['translation']) or $tag_mismatch){
			$translation=$seg['segment'];
		}else{
			$translation=$seg['translation'];
		}
		//fix to escape non-html entities
		//	log::doLog($this->currentId. " ESCAPE t1 : $translation\n\n");
		$translation = str_replace("&lt;", '#LT#', $translation);
		$translation = str_replace("&gt;", '#GT#', $translation);
		$translation = str_replace("&amp;", '#AMP#', $translation);
		//$translation=html_entity_decode($translation,ENT_NOQUOTES|ENT_HTML401,"utf-8");
		$translation=html_entity_decode($translation,ENT_NOQUOTES,"utf-8");
		$translation = str_replace('#AMP#','&amp;', $translation);
		$translation = str_replace('#LT#','&lt;', $translation);
		$translation = str_replace('#GT#','&gt;', $translation);
		//	log::doLog($this->currentId. " VALIDATE t1 : $translation\n\n");

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
			//log::doLog($this->currentId. " INVALID ($end_tags) t2 : $translation\n");
			$translation = str_replace($end_tags, "", $translation);
			//log::doLog($this->currentId. " FIX t2 : $translation\n");
		}

		if (!empty($seg['mrk_id'])) {
			$translation = "<mrk mtype=\"seg\" mid=\"" . $seg['mrk_id'] . "\">".$seg['mrk_prev_tags'].$translation.$seg['mrk_succ_tags']."</mrk>";
		}
		//	log::doLog($this->currentId. " t3 : $translation\n");
		//	log::doLog( "\n\n");
		$transunit_translation.=$seg['prev_tags'] . $translation . $end_tags . $seg['succ_tags'];
		//	log::doLog($this->currentId. " OUTPUT t4 : $transunit_translation\n");
		/*
		   if (isset($data[$i + 1]) and $seg['internal_id'] == $data[$i + 1]['internal_id']) {
		// current segment and subsequent has the same internal id --> 
		// they are two mrk of the same source segment  -->
		// the translation of the subsequentsegment will be queued to the current
		continue;
		}
		 */
		return $transunit_translation;
	}

}
?>
