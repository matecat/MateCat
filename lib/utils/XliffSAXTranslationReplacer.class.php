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

	public function __construct($filename,$segments){
		$this->filename=$filename;
		$this->ofp=fopen($this->filename.'.out.xliff','w');
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
		//pass this object to parser to make callbacks visible
		xml_set_object($xml_parser,$this);
		//avoid uppercasing all tags name
		xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, false);
		//define callbacks for tags
		xml_set_element_handler($xml_parser, "tagOpen", "tagClose");
		//define callback for data
		xml_set_character_data_handler($xml_parser, "characterData");

		//read a chunk of text
		while ($this->currentBuffer = fread($fp, 4096)) {
			//get lenght of chunk
			$this->len=strlen($this->currentBuffer);
			//get last char
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
				$currChar=$this->currentBuffer[$idx-$this->offset];
			} else {
				//if it's out, simple use the last character of the chunk
				$currChar=$this->currentBuffer[$this->len-1];
			}

			//if last char of tag is a backslash, it's an empty tag
			if('/'==$currChar){
				$this->isEmpty=true;
				//add the slash to the tag
				$tag.='/';
			}

			//trim last space
			$tag=rtrim($tag);
			//add tag ending
			$tag.=">";
			//flush to pointer
			fwrite($this->ofp,$tag);
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
			fwrite($this->ofp,$tag);
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
			//don't know why, but outside translation units stuff is html-encoded
			if(!$this->inTU){
				//encode entities
				$data=htmlentities($data,ENT_NOQUOTES);
			}

			//flush to pointer
			fwrite($this->ofp,$data);
		}
	}
	/*
	   prepare segment tagging for xliff insertion
	 */
	private function prepareSegment($seg,$transunit_translation = ""){
		$end_tags = "";
		//echo "t1 : ".$seg['translation']."\n";
		$translation = empty($seg['translation']) ? $seg['segment'] : $seg['translation'];
		//echo "t11 : $translation\n\n";

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
			//echo "t2 : $translation\n";
		}

		if (!empty($seg['mrk_id'])) {
			$translation = "<mrk mtype=\"seg\" mid=\"" . $seg['mrk_id'] . "\">$translation</mrk>";
		}
		//echo "t3 : $translation\n";
		//echo "\n\n";
		$transunit_translation.=$seg['prev_tags'] . $translation . $end_tags . $seg['succ_tags'];
		//echo "t4 :" .$seg['prev_tags'] . $translation . $end_tags.$seg['succ_tags']."\n";
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
