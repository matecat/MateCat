<?

/*
This code is copyrighted and property of Translated s.r.l. 
Should not be distrubuted. 
This is made available for Matecat partners for executing the field test.
Thank you for keeping is confidential.
*/

class MyMemory {

public static function  TMS_MATCH($seg1,$seg2,$penalty_id=0, $for_semantix=false) {
	// BUG: "my account" con "account lockout" da un match del ~60%
	// "my account" con "Account Name" ha un match di 77
	//BUG: NON SERVE
	$seg1 = trim($seg1);
	$seg2 = trim($seg2);

	// Aggiunto 2012-10-05 per milgiorare split parole in diff_html
	// rimosso perchè non usato
	// mb_internal_encoding('UTF-8');
    // mb_regex_encoding('UTF-8');

	$ts1=$seg1;
	$ts2=$seg2;

	$penalty = 0;
	switch($penalty_id) {
		case "1": //mt!
			$penalty = 0.15;
		break;

		case "2": //webalign!
			$penalty = 0.05;
		break;

		case "3": //align!
			$penalty = 0.5;
		break;

		case "4": //mtfixed!
			$penalty = 0.05;
		break;

		case "11": // 1 star (low quality)
			$penalty = 0.50;
		break;

		case "12": // 1 star (low quality)
			$penalty = 0.30;
		break;

		case "13": // 1 star (average low quality)
			$penalty = 0.10;
		break;

		case "14": // 1 star (average high quality)
			$penalty = 0.00;
		break;

		case "15": // high quality
			$penalty = -0.01;
		break;

		default:
		$penalty = 0;
		break;
	}

	//BUG: dovrò inserire livelli di astrazione sui segmenti e calcolare la media
	//per ora porto tutto a lowercase:

	$seg1 = mb_strtolower($seg1,"UTF-8");
	$seg2 = mb_strtolower($seg2,"UTF-8");

	// xml apos
	$seg1 = str_replace('&apos;',"'",$seg1);
	$seg2 = str_replace('&apos;',"'",$seg2);

	// Tag Penalties
	preg_match_all('/<.*?'.'>/s',$seg1,$temp1);
	preg_match_all('/<.*?'.'>/s',$seg2,$temp2);
	$c = count(self::my_array_xor($temp1[0],$temp2[0]));

	$seg1=preg_replace('/<.*?'.'>/s',' ',$seg1);
	$seg2=preg_replace('/<.*?'.'>/s',' ',$seg2);

	$penalty += 0.01*$c;


	$c=0;
	// Penalty for different numbers
	$temp1 = '';
	$temp2 = '';
	preg_match_all('/[0-9,\.]+/',$seg1,$temp1);
	preg_match_all('/[0-9,\.]+/',$seg2,$temp2);
	$c = count(self::my_array_xor($temp1[0],$temp2[0]));

	$seg1=preg_replace('/[[0-9,\.]+/',' ',$seg1);
	$seg2=preg_replace('/[[0-9,\.]+/',' ',$seg2);

	$penalty_placeable=0.01*$c;

	// Penalties Punctuation
	// Differs from numbers because if A has punt and B does not, it's not that bad as if a number is missing.
	$c = 0;
	$temp1 = '';
	$temp2 = '';
	preg_match_all('/[[:punct:]]+/',$seg1,$temp1);
	preg_match_all('/[[:punct:]]+/',$seg2,$temp2);
	$c = count(self::my_array_xor($temp1[0],$temp2[0]));
	$seg1=preg_replace('/[[:punct:]]+/',' ',$seg1);
	$seg2=preg_replace('/[[:punct:]]+/',' ',$seg2);
	$penalty_placeable+=0.02*$c;


	// penalty per case sensitive / formatting
	$penalty_formatting = 0.00;
	if(mb_strtolower($ts1,"UTF-8")==mb_strtolower($ts2,"UTF-8") and $ts1!=$ts2) {
		$penalty_formatting = 0.02;
	}



	// End of penalties ------------------------------------------------

	// I remove all double spaces I introduced
	$seg1=preg_replace('/[ ]+/',' ',$seg1);
	$seg2=preg_replace('/[ ]+/',' ',$seg2);

	$a = explode(' ',($seg1));
	$b = explode(' ',($seg2));

	$a = array_filter($a,"trim");
	$b = array_filter($b,"trim");

	$result = self::TMS_ARRAY_MATCH($a,$b,$for_semantix)-($penalty+$penalty_formatting+$penalty_placeable);
	return $result;
}




function TMS_ARRAY_MATCH($array1,$array2,$for_semantix = false)    {
	
	// No Longer symmetric
	
	// Important:
	// Array1 is the segment to translate
	// Array2 is the suggestion
	
	// es. control panel -> panel = lev match 75%
	$min_words_norm = 4;
	$max_words_norm = 12;
	$bonus = 1;

	$aliases= array_flip(array_values(array_unique(array_merge($array1,$array2))));

	$stringA=''; $stringB='';
	
	foreach($array1 as $entry) { $stringA.=self::unichr($aliases[$entry]);
		if(strlen($entry)>4) { $stringA.=self::unichr($aliases[$entry]); }
		
	}

	foreach($array2 as $entry) { $stringB.=self::unichr($aliases[$entry]);
		if(strlen($entry)>4) { $stringB.=self::unichr($aliases[$entry]); }
		
	}

	// Is the string is longer than 254 words (does not make sense) I cannot use levenstein of oliver.
	if ( (strlen($stringA)>254) OR (strlen($stringB)>254) ) return -1;


	similar_text($stringA,$stringB,$p);

	$la = strlen($stringA);
	$lb = strlen($stringB);
	$lmax = max($la,$lb);


	if($for_semantix) {
		$lmax = $la;
		$min_words_norm = 1;
	}

	$result = 1 - $lmax/max($lmax,$min_words_norm)*(1-$p/100);

	return $result;
}


// I expect this to be in PHP in the future...
function my_array_xor($array_a, $array_b) {
		$union_array = array_merge($array_a, $array_b);
		$intersect_array = array_intersect($array_a, $array_b);
		return array_diff($union_array, $intersect_array);
	}

function unichr($u) {
        return mb_convert_encoding('&#' . intval($u) . ';', 'UTF-8', 'HTML-ENTITIES');
}


function diff($old, $new){
	$maxlen = 0;
	foreach($old as $oindex => $ovalue){
		$nkeys = array_keys($new, $ovalue);
		foreach($nkeys as $nindex){
			$matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1]) ?
				$matrix[$oindex - 1][$nindex - 1] + 1 : 1;
			if($matrix[$oindex][$nindex] > $maxlen){
				$maxlen = $matrix[$oindex][$nindex];
				$omax = $oindex + 1 - $maxlen;
				$nmax = $nindex + 1 - $maxlen;
			}
		}	
	}
	if($maxlen == 0) return array(array('d'=>$old, 'i'=>$new));
	return array_merge(
		self::diff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
		array_slice($new, $nmax, $maxlen),
		self::diff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen)));
}

public static function diff_html($old, $new,$by_word=true){
	
	// No diff no work
	if ($old==$new) {
		return $new;
	}
	
	if (strlen($old)<=254 AND strlen($old)<=254) {
		if (levenshtein($new, $old)<=2) {
		$by_word = false;
		}
	}

	if ($by_word==true) {
		$sep = ' ';
        // $old_array = mb_split('[^\w]+',$old);
		$old_array = explode(' ',$old);
		$new_array = explode(' ',$new);
		// $new_array = mb_split('[^\w]+',$new);
	
	} else {
		$sep = '';
		for ($i=0;$i<strlen($old);$i++) {
		$old_array[]=$old[$i];
		}
		for ($i=0;$i<strlen($new);$i++) {
		$new_array[]=$new[$i];
		}
	}
	
	$diff = self::diff($old_array,$new_array);
	
	$ret = '';
	foreach($diff as $k){
		if(is_array($k))
			$ret .= (!empty($k['d'])?"<strike><span style=\"color:red;\">".implode($sep,$k['d'])."</span></strike>$sep":$sep).
				(!empty($k['i'])?"<span style=\"color:blue\">".implode($sep,$k['i'])."</span>$sep":$sep);
		else $ret .= $k . $sep;
	}
	return $ret;
}


}