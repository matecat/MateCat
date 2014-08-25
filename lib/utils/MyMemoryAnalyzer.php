<?php
$root = realpath(dirname(__FILE__) . '/../../');
include_once ($root."/inc/config.inc.php"); // only fortesting purpose
include_once (INIT::$UTILS_ROOT."/Utils.php"); //only for testing purpose
/*
   This code is copyrighted and property of Translated s.r.l.
   Should not be distrubuted.
   This is made available for Matecat partners for executing the field test.
   Thank you for keeping is confidential.
 */

class MyMemoryAnalyzer {

	private $url = "http://api.mymemory.translated.net";

	public function __construct() {

	}

	public function fastAnalysis($segs_array) {
		if (!is_array($segs_array)){

			return null;
		}
		$json_segs=json_encode($segs_array);

		$d[ 'fast' ]     = "1";
		$d[ 'df' ]       = "matecat_array";
		$d[ 'segs' ]     = $json_segs;

		$countwordReport = Utils::curl_post( "$this->url/analyze", $d );

		$reportDecoded=json_decode($countwordReport,true);

		return $reportDecoded;
	}

	/**
	 * Detect language for an array of file's segments.
	 * @param $segs_array An array whose keys are file IDs and values are array of segments.
	 * @return mixed
	 */
	public function detectLanguage($segs_array, $lang_detect_files){
		//In this array we will put a significative string for each job.
		$segmentsToBeDetected = array();


		/**
		 * @var $arrayIterator ArrayIterator
		 */
		$arrayIterator = $segs_array->getIterator();

		$counter = 0;
		//iterate through files and extract a significative
		//string long at least 150 characters for language detection
		while($arrayIterator->valid()){
			$currFileName = key($lang_detect_files);

			if($lang_detect_files[$currFileName] == "skip"){
				//this will force google to answer with "und" language code
				$segmentsToBeDetected[] = "q[$counter]=1";

				next($lang_detect_files);
				$arrayIterator->next();
				$counter++;
				continue;
			}

			$currFileId = $arrayIterator->key();

			$currFile = $arrayIterator->current();

			/**
			 * @var $currFileIterator ArrayIterator
			 */
			$segmentArray = $currFile->getIterator()->current();

			//take first 50 segments
			$segmentArray = array_slice($segmentArray, 0, 50);

            foreach ($segmentArray as $i => $singleSegment){
                $singleSegment = explode(",", $singleSegment);
                $singleSegment = array_slice( $singleSegment, 3, 1 );

                //remove tags, duplicated spaces and all not Unicode Letter
                $singleSegment[0] = preg_replace(array("#<[^<>]*>#", "#\x20{2,}#", '#\PL+#u'), array("", " ", " "), $singleSegment[0]);

                //remove not useful spaces
                $singleSegment[0] = preg_replace( "#\x20{2,}#", " ", $singleSegment[0]);

				$segmentArray[$i] = $singleSegment[0];
			}

			usort($segmentArray, array($this, 'sortByStrLenAsc'));

			$textToBeDetected = "";
			/**
			 * take first 150 characters starting from the longest segment in the slice
			 */
			for($i = count($segmentArray) -1; $i >= 0; $i-- ){
				$textToBeDetected .= " " . trim ($segmentArray[$i], "'");
				if(mb_strlen($textToBeDetected) > 150) break;
			}
			$segmentsToBeDetected[] = "q[$counter]=" . urlencode($textToBeDetected);

			next($lang_detect_files);
			$arrayIterator->next();
			$counter++;
		}

		$curl_parameters = implode("&", $segmentsToBeDetected)."&of=json";

		$options = array(
			CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => 0,
			CURLOPT_USERAGENT => "Matecat-Cattool/v" . INIT::$BUILD_NUMBER,
			CURLOPT_CONNECTTIMEOUT => 2,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $curl_parameters
		);

		$mh = new MultiCurlHandler();
		$tokenHash = $mh->createResource( "http://api-test.mymemory.translated.net/langdetect.php", $options );

		$mh->multiExec();

		$res = $mh->getAllContents();

		return json_decode( $res[ $tokenHash ], true );
	}

    private function sortByStrLenAsc($a, $b){
        return strlen($a) >= strlen($b);
    }

}

