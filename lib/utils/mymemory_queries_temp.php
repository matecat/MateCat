<?php

function getFromMM($query, $s, $t) {
	$q = urlencode($query);
	$url = "http://mymemory.translated.net/api/get?q=$q&langpair=$s|$t&de=matecatdeveloper@matecat.com";
	$res = file_get_contents($url);
	$res = json_decode($res, true);

	$ret = $res['matches'];

	foreach ($ret as &$match) {
		if ($match['last-update-date'] == "0000-00-00 00:00:00") {
			$match['last-update-date'] = "0000-00-00";
		}
		if (!empty($match['last-update-date']) and $match['last-update-date'] != '0000-00-00') {
			$match['last-update-date'] = date("Y-m-d", strtotime($match['last-update-date']));
		}

		if (empty($match['created-by'])) {
			$match['created-by'] = "Anonymous";
		}

		$match['match'] = $match['match'] * 100;
		$match['match'] = $match['match'] . "%";

		if ($match['created-by'] == 'MT!') {
			$match['match'] = "MT";
			$match['created-by'] = "MT";
		}
	}

	return $ret;
}

function addToMM($seg, $tra, $source_lang, $target_lang, $id_translator, $key) {
	$seg = urlencode($seg);
	$tra = urlencode($tra);
	$private_query = "";
	if (!empty($id_translator) and !empty($key)) {
		$id_translator = rawurldecode($id_translator);
		$key = rawurlencode($key);
		$private_query = "key=$key";
	}
	$url = "http://mymemory.translated.net/api/set?seg=$seg&tra=$tra&langpair=$source_lang|$target_lang&de=matecatdeveloper@matecat.com&$private_query";

	//log::doLog($url);
	$res = file_get_contents($url);
	$res = json_decode($res, true);

	return $res;
}

function deleteToMM($source_lang, $target_lang, $source, $target) {

	$url = "http://mymemory.translated.net/api/delete?langpair=$source_lang|$target_lang&seg=$source&tra=$target&de=matecatdeveloper@matecat.com";
	$res = file_get_contents($url);
	$res = json_decode($res, true);

	return $res;
}

?>
