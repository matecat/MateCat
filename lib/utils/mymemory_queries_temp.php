<?php

function getFromMM($query) {
    $q = urlencode($query);
    $url = "http://mymemory.translated.net/api/get?q=$q&langpair=en|it";
    $res = file_get_contents($url);
    $res = json_decode($res, true);

    $ret = array();
    // print_r ($res['matches']);
    foreach ($res['matches'] as $match) {
        if ($match['last-update-date']=="0000-00-00 00:00:00"){
            $match['last-update-date']="";
        }
        
        $ret[] = array($match['translation'], $match['quality'], $match['created-by'], $match['last-update-date'], $match['match']*100);
    }


    //print_r ($ret);
    return $ret;
}

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
?>
