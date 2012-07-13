<?php

function getFromMM($query) {
    $q = urlencode($query);
    $url = "http://mymemory.translated.net/api/get?q=$q&langpair=en|it&de=matecatdeveloper@matecat.com";
    $res = file_get_contents($url);
    //echo $res;
    $res = json_decode($res, true);

    $ret = $res['matches'];

//print_r ($ret);exit;

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

function addToMM($seg, $tra, $source_lang, $target_lang) {
    $seg = urlencode($seg);
    $tra = urlencode($tra);
    $url = "http://mymemory.translated.net/api/set?seg=$seg!&tra=$tra!&langpair=$source_lang|$target_lang&de=matecatdeveloper@matecat.com";
    $res = file_get_contents($url);
    //echo $res;
    $res = json_decode($res, true);
    // print_r($res);
    
    return $res;
}

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
?>
