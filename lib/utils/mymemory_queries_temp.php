<?php

function getFromMM($query) {
    $q = urlencode($query);
    $url = "http://mymemory.translated.net/api/get?q=$q&langpair=en|it";
    $res = file_get_contents($url);
    $res = json_decode($res, true);

    $ret = $res['matches'];
//print_r ($ret);exit;

    foreach ($ret as &$match) {
        if ($match['last-update-date'] == "0000-00-00 00:00:00") {
            $match['last-update-date'] = "";
        }
        if (!empty($match['last-update-date'])) {
            $match['last-update-date'] = date("Y-m-d", strtotime($match['last-update-date']));
        }

        if (empty($match['created-by'])) {
            $match['created-by'] = "anonimous";
        }

        $match['match'] = $match['match'] * 100;
         
        if ($match['created-by'] == 'MT!') {
            $match['match'] = "MT";
            $match['created-by']="MT";
        }
        
        
    }

    return $ret;
}

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
?>
