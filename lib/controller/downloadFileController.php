<?php

set_time_limit(180);
include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";

class downloadFileController extends downloadController {

	private $id_job;
	private $password;
	private $fname;
	private $download_type;

	public function __construct() {
		parent::__construct();

		$this->fname = $this->get_from_get_post('filename');
		$this->id_file = $this->get_from_get_post('id_file');
		$this->id_job = $this->get_from_get_post('id_job');
		$this->download_type = $this->get_from_get_post('download_type');
		$this->filename = $this->fname;
		$this->password = $this->get_from_get_post("password");

		$this->download_type = $this->get_from_get_post("download_type");

		if (empty($this->id_job)) {
			$this->id_job = "Unknown";
		}
	}

	public function doAction() {
		$debug=array();
		$debug['total'][]=time();

		// specs for filename at the task https://app.asana.com/0/1096066951381/2263196383117
		$converter = new fileFormatConverter();
		$debug['get_file'][]=time();
		$files_job = getFilesForJob($this->id_job, $this->id_file);
		$debug['get_file'][]=time();
		$nonew = 0;

		$output_content = array();
		foreach ($files_job as $file) {
			$mime_type = $file['mime_type'];
			$id_file = $file['id_file'];
			$current_filename = $file['filename'];
			$original = $file['xliff_file'];

			$debug['get_segments'][]=time();
			$data = getSegmentsDownload($this->id_job, $this->password, $id_file, $nonew);
			$debug['get_segments'][]=time();


			$transunit_translation = "";

			$debug['replace'][]=time();
			foreach ($data as $i => $seg) {
				$end_tags = "";
				$translation = empty($seg['translation']) ? $seg['segment'] : $seg['translation'];

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
				if (isset($data[$i + 1]) and $seg['internal_id'] == $data[$i + 1]['internal_id']) {
					// current segment and subsequent has the same internal id --> 
					// they are two mrk of the same source segment  -->
					// the translation of the subsequentsegment will be queued to the current
					continue;
				}

				$res_match_2 = false;
				$res_match_1 = false;

				$pattern = '|(<trans-unit id=["\']' . $seg['internal_id'] . '["\'].*?>.*?)(<source.*?>.*?</source>.*?)(<seg-source.*?>.*?</seg-source>.*?)?(<target.*?>).*?(</target>)(.*?)(</trans-unit>)|si';

				$res_match_1 = preg_match($pattern, $original, $match_target);
				if (!$res_match_1) {
					$pattern = '|(<trans-unit id=[\'"]' . $seg['internal_id'] . '[\'"].*?>.*?)(<source.*?>.*?</source>.*?)(<seg-source.*?>.*?</seg-source>.*?)?(.*?</trans-unit>)|si';
					$res_match_2 = preg_match($pattern, $original, $match_target);
					if (!$res_match_2) {
						; // exception !!! see the segment format
					}
				}


				if ($res_match_1) { //target esiste
					$replacement = "$1$2$3$4" . $transunit_translation . "$5$6$7";
				}
				if ($res_match_2) { //target non esiste
					$replacement = "$1$2$3<target>$transunit_translation</target>$4";
				}

				if (!$res_match_1 and !$res_match_2) {
					continue; // none of pattern verify the file structure for current segmen t: go to next loop. In the worst case the procedure will return the original file
				}

				$original = preg_replace($pattern, $replacement, $original);
				$transunit_translation = ""; // empty the translation before the end of the loop
			}
			$debug['replace'][]=time();
			$output_content[$id_file]['content'] = $original;
			$output_content[$id_file]['filename'] = $current_filename;

			if (!in_array($mime_type, array("xliff", "sdlxliff", "xlf"))) {
				$debug['do_conversion'][]=time();
				file_put_contents("/home/matecat/test.sdlxliff", $output_content[$id_file]['content']);
				$convertResult = $converter->convertToOriginal($output_content[$id_file]['content']);
				$output_content[$id_file]['content'] = $convertResult['documentContent'];
				$debug['do_conversion'][]=time();
			}
		}

		$ext = "";
		if ($this->download_type == 'all') {
			if (count($output_content) > 1) {
				$this->filename = $this->fname;
				$pathinfo = pathinfo($this->fname);
				if ($pathinfo['extension'] != 'zip') {
					$this->filename = $pathinfo['basename'] . ".zip";
				}
				$this->content = $this->composeZip($output_content); //add zip archive content here;
			} elseif (count($output_content) == 1) {
				$this->setContent($output_content);
			}
		} else {
			$this->setContent($output_content);
		}
		$debug['total'][]=time();
	}

	private function setContent($output_content) {
		foreach ($output_content as $oc) {
			$pathinfo = pathinfo($oc['filename']);
			$ext = $pathinfo['extension'];
			$this->filename = $oc['filename'];

			if ($ext == 'pdf' or $ext == "PDF") {
				$this->filename = $pathinfo['basename'] . ".docx";
			}
			$this->content = $oc['content'];
		}
	}

	private function composeZip($output_content) {
		$file = tempnam("/tmp", "zipmatecat");
		$zip = new ZipArchive();
		$zip->open($file, ZipArchive::OVERWRITE);

		// Staff with content
		foreach ($output_content as $f) {
			$pathinfo = pathinfo($f['filename']);
			$ext = $pathinfo['extension'];
			if ($ext == 'pdf' or $ext == "PDF") {
				$f['filename'] = $pathinfo['basename'] . ".docx";
			}
			$zip->addFromString($f['filename'], $f['content']);
		}

		// Close and send to users
		$zip->close();
		$zip_content = file_get_contents("$file");
		unlink($file);
		return $zip_content;
	}

	private function convertToOriginalFormat() {

	}

}

?>
