<?php
$root = realpath(dirname(__FILE__) . '/../../');
include_once ($root."/inc/config.inc.php"); // only fortesting purpose
include_once (INIT::$UTILS_ROOT."/utils.class.php"); //only for testing purpose
/*
   This code is copyrighted and property of Translated s.r.l.
   Should not be distrubuted.
   This is made available for Matecat partners for executing the field test.
   Thank you for keeping is confidential.
 */

class MyMemoryAnalyzer {

	private $url = "http://mymemory.translated.net";
	private $root_path = "api";

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

		$countwordReport = Utils::curl_post( "$this->url/$this->root_path/analyze", $d );

		$reportDecoded=json_decode($countwordReport,true);

		return $reportDecoded;
	}

}
?>
