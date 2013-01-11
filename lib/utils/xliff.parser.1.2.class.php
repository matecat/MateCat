<?

/*

Basic and Pretty Tollerant Xliff 1.0, 1.1 and 1.2 into Array converter

Developed by Translated s.r.l. distributed in LGPL.

XLIFF 1.2
http://docs.oasis-open.org/xliff/xliff-core/xliff-core.html

This parser was written in order to extract just the basic information needed for translation within Matecat.
This tool has been designed to try to parse non well formed XML files, since they are pretty common in the translation industry :)

Performance 3s for 1M words (on a i7 quad-core)

Example Output

['files'] = array
 			[1] = array
 				   [attr] = array
				     ['source-lang']
				     ['target-lang']
				     ['data-type']
				   ['trans-units'] = array
				   					[1] = array
										   ['attr'] = array
										     		  ['id'] = Unique ID in file (hopefully)
										   ['source'] = array
										                  ['raw-content'] = "Hello &lt;b&gt;World&lt;/b&gt;"
										   ['target'] = array
														  ['raw-content'] = "Ciao &lt;b&gt;Mondo&lt;/b&gt;"
							

Tollerance
1) As by specs, all <file> have required attributes, we issue a warning but continue importing.
2) '" to enclose attributes id="1" id="1", but not without quotes eg. id=1
3) Non-closed or wrongly nested tags are managed and often results in a skipped elements without affecting the rest.
4) We often see XLIFF files with ISO content, if detected it get converted at file level (unfortunately not at segment level, risky)

*/

// echo Xliff_Parser::fix_non_well_formed_xml('<g id="1">Ciao</g> lo <g id="2"></g>sai che 5 > 2 ? &apos;&gt;');
// echo Xliff_Parser::fix_non_well_formed_xml('Continue >>'); exit;

class Xliff_Parser {

public static function Xliff2Array($file_content) {

// Pre-Processing. 
// Fixing non UTF-8 encoding (often I get Unicode UTF-16)
$enc = mb_detect_encoding($file_content);
if ($enc<>'UTF-8') {
	$file_content = iconv($enc,'UTF-8',$file_content); 	
	$xliff['parser-warnings'][] = "Input identified as $enc ans converted UTF-8. May not be a problem if the content is English only";
}

// Checking Requirements (By specs, I know that xliff version is in the first 1KB)
preg_match('|<xliff\s.*?version\s?=\s?["\'](.*?)["\']|si',substr($file_content,0,1000),$tmp);
if (!isset($tmp[1])) {
	$xliff['parser-errors'][] = "Cannot import. This does not seems a valid XLIFF, we support version 1.0, 1.1, 1.2.";
	return $xliff;
}
if (!in_array($tmp[1],array('1.0','1.1','1.2'))) {
	$xliff['parser-errors'][] = "Cannot import XLIFF version $tmp[1]. We only support XLIFF (version 1.0, 1.1, 1.2).";
	return $xliff;
}


// Getting the Files 

$files = preg_split('|<file[\s>]|si',$file_content,-1, PREG_SPLIT_NO_EMPTY);

$i = 0;
foreach ($files as $file) {

	// First element in the XLIFF split is the content before <file> (header), skipping
	if ($i>0)  
	{
	
	// Getting Files Attributes
	
	// Restrict preg action for speed, just for attributes
	$file_short = substr($file,0,strpos($file,'>')+1);
	// Original
	unset($temp);
	preg_match('|original\s?=\s?["\'](.*?)["\']|si',$file_short,$temp);
	$xliff['files'][$i]['attr']['original'] = $temp[1];
	// Source-language
	unset($temp);
	preg_match('|source-language\s?=\s?["\'](.*?)["\']|si',$file_short,$temp);
	$xliff['files'][$i]['attr']['source-language'] = $temp[1];
	// Data-type
	unset($temp);
	preg_match('|datatype\s?=\s?["\'](.*?)["\']|si',$file_short,$temp);
	$xliff['files'][$i]['attr']['datatype'] = $temp[1];
	// Target-language
	unset($temp);
	preg_match('|target-language\s?=\s?["\'](.*?)["\']|si',$file_short,$temp);
	if (isset($temp[1])) $xliff['files'][$i]['attr']['target-language'] = $temp[1];
	
	
	// Getting Trans-units
	$trans_units = preg_split('|<trans-unit[\s>]|si',$file,-1, PREG_SPLIT_NO_EMPTY);
	$j = 0;
	foreach ($trans_units as $trans_unit) {
		
		// First element in the XLIFF split is the header, not the first file
		if ($j>0)  {
			// Getting Trans-unit attributes
			// ID
			unset($temp);
			preg_match('|id\s?=\s?["\'](.*?)["\']|si',$trans_unit,$temp);
			$xliff['files'][$i]['trans-units'][$j]['attr']['id'] = $temp[1];
			
			// Translate
			unset($temp);
			preg_match('|translate\s?=\s?["\'](.*?)["\']|si',$trans_unit,$temp);
			if (isset($temp[1])) $xliff['files'][$i]['trans-units'][$j]['attr']['translate'] = $temp[1];
				
			
			// Getting Source and Target raw content
			unset($temp);
			
			preg_match('|<source.*?>(.*?)</source>|si',$trans_unit,$temp);
			// just in case of a <source /> 
			if (!isset($temp[1])) {  
				$temp[1] = '';
			}
				$temp[1] = self::fix_non_well_formed_xml($temp[1]);
				$xliff['files'][$i]['trans-units'][$j]['source']['raw-content'] = $temp[1];
			
			
			unset($temp);
			preg_match('|<target.*?>(.*?)</target>|si',$trans_unit,$temp);
			if (isset($temp[1])) {
				$temp[1] = self::fix_non_well_formed_xml($temp[1]);
				$xliff['files'][$i]['trans-units'][$j]['target']['raw-content'] = $temp[1];
			}
			
			// Add here other trans-unit sub-elements you need, copying and pasting the 3 lines below
			
			unset($temp);
			preg_match('|<seg-source.*?>(.*?)</seg-source>|si',$trans_unit,$temp);
			// if (isset($temp[1])) $xliff['files'][$i]['trans-units'][$j]['seg-sources']['raw-content'] = $temp[1]; 
			
			if (isset($temp[1])) {
			$markers = $temp[1];
			unset($temp);
			log::doLog("BEFORE");
			log::doLog($markers);
			$markers = preg_split('#(<mrk\s.*?type="seg".*?>(.*?)</mrk>)#si',$markers,-1, PREG_SPLIT_DELIM_CAPTURE);
			log::doLog("AFTER");
			log::doLog($markers);
			
			$mi = 0;
			$k  = 0;
			while (isset($markers[$mi+1])) {
				log::doLog ("mi is $mi - exits \$marckers[\$mi +1]");
				unset($mid);
				preg_match('|mid\s?=\s?["\'](.*?)["\']|si', $markers[$mi+1],$mid);
				
				// For not loosing info I attach the last external tag to the last seg marker. 
				if (!isset($markers[$mi+5])) { 
					$last_ext_tags = $markers[$mi+3]; 
				} else { 
					$last_ext_tags = ''; 
				}
				
				$xliff['files'][$i]['trans-units'][$j]['seg-source'][$k]['mid'] = $mid[1];
				$xliff['files'][$i]['trans-units'][$j]['seg-source'][$k]['ext-prec-tags'] = $markers[$mi];
				$xliff['files'][$i]['trans-units'][$j]['seg-source'][$k]['raw-content'] = $markers[$mi+2] ;
				$xliff['files'][$i]['trans-units'][$j]['seg-source'][$k]['ext-succ-tags'] = $last_ext_tags;
				// Different from source and target content, I expect that if you used seg-source it is a a well done tool so I don't try to fix.
				$mi = $mi + 3;		
				$k++;
			}
log::doLog("mi exit $mi \n\n");
			}

		}
	$j++;	
	} // End of trans-units
	
	
	} // End of files
	
	$i++;
}

//log::doLog ($xliff);
//echo "<pre>"; print_r ($xliff); exit;
return $xliff;
}

public static function fix_non_well_formed_xml($content) {
	
	/*
	This function exists because many developers started adding html tags directly into the XLIFF source since:
	 1) XLIFF tag remapping is too complex for them
	 2) Trados does not lock Tags within the <source> that are expressed as &gt;b&lt; but is tollerant to html tags in <source>	
	 
	 in shor people typed:
	 <source>The <b>red</d> house</source> or worst <source>5 > 3</source>
	 instead of
	 <source>The <g id="1">red</g> house.</source> and <source>5 &gt; 3</source>
	 But this also became 
	
	
	 This function will do the following
	 <g id="1">Hello</g>, 4 > 3 -> <g id="1">Hello</g>, 4 &gt; 3
	 <g id="1">Hello</g>, 4 > 3 &gt; -> <g id="1">Hello</g>, 4 &gt; 3 &gt; 2
	
	 
		BUG / KNOWN ISSUE, is not very tollerante in tag writing style. These will not work:
		</ g> instead of </g>
		<g /> since g is not an empty tag 
		<x> instead of <x /> since x if an empty tag 
	
	
	 Replace <g> <ph> etc etc in ##XLIFF-TAG1-erwsldf##	
	  <g>, <x/>, <bx/>, <ex/>, <bpt> , <ept>, <ph>, <it> , <mrk>
	  */
	  
	
	// Performance: I do a quick check before doing many preg	
	if (preg_match('|<.*?>|si', $content,$tmp)) {
		$tags = array(); $tmp = array();
		
		preg_match_all('|<g\s.*?>|si', $content,$tmp);    $tags = array_merge($tags,(array)$tmp[0]);
		preg_match_all('|</g>|si', $content,$tmp);        $tags = array_merge($tags,(array)$tmp[0]);
		preg_match_all('|<x.*?/?>|si', $content,$tmp);    $tags = array_merge($tags,(array)$tmp[0]);
		preg_match_all('|<bx.*?/?]>|si', $content,$tmp);  $tags = array_merge($tags,(array)$tmp[0]);
		preg_match_all('|<ex.*?/?>|si', $content,$tmp);   $tags = array_merge($tags,(array)$tmp[0]);
		preg_match_all('|<bpt\s.*?>|si', $content,$tmp);  $tags = array_merge($tags,(array)$tmp[0]);
		preg_match_all('|</bpt>|si', $content,$tmp);      $tags = array_merge($tags,(array)$tmp[0]);
		preg_match_all('|<ept\s.*?>|si', $content,$tmp);  $tags = array_merge($tags,(array)$tmp[0]);
		preg_match_all('|</ept>|si', $content,$tmp);      $tags = array_merge($tags,(array)$tmp[0]);
		preg_match_all('|<ph\s.*?>|si', $content,$tmp);   $tags = array_merge($tags,(array)$tmp[0]);
		preg_match_all('|</ph>|si', $content,$tmp);       $tags = array_merge($tags,(array)$tmp[0]);
		preg_match_all('|<it\s.*?>|si', $content,$tmp);   $tags = array_merge($tags,(array)$tmp[0]);
		preg_match_all('|</ph>|si', $content,$tmp);       $tags = array_merge($tags,(array)$tmp[0]);
		preg_match_all('|<it\s.*?>|si', $content,$tmp);   $tags = array_merge($tags,(array)$tmp[0]);
		preg_match_all('|</it>|si', $content,$tmp);       $tags = array_merge($tags,(array)$tmp[0]);
		preg_match_all('|<mrk\s.*?>|si', $content,$tmp);  $tags = array_merge($tags,(array)$tmp[0]);
		preg_match_all('|</mrk>|si', $content,$tmp);      $tags = array_merge($tags,(array)$tmp[0]);
	}
	
	if (isset($tags[0])) {	
	$i = 0;
	$tag_map = array();
	
	foreach ($tags as $tag) {
		$key = '##XLIFF-PLACEHOLDER-TAG'.$i.'-erwsldf##';
		$tag_map[$key] = $tag;
		$content = str_replace($tag,$key,$content);
		$i++;
		}	
	}
	// print_r($tag_map);
	
	// In PHP 5.2.3 this became magically a single line of code with 'double encode'=false! 
	// Waiting php 5.4 for ENT_SUBSTITUTE, ENT_XML1 or ENT_DISALLOWED
	// This means that £ will be converted wrongly in &pound;
	// $content = htmlentities($content,ENT_QUOTES,'UTF-8',false);
	$content = htmlspecialchars($content,ENT_QUOTES,'UTF-8',false);
	
	if (isset($tags[0])) {
	foreach ($tag_map as $key=>$tag) {
		$content = str_replace($key,$tag,$content);
	}
	}
	
	
	return $content;
	
	
}


}
