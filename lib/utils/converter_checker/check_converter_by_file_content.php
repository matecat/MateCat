<?php

set_time_limit(0);

$ROOT = realpath( dirname(__FILE__) . '/../../../' );

//imports
require_once $ROOT . '/inc/config.inc.php';
INIT::obtain();

require_once INIT::$UTILS_ROOT . "/log.class.php";
require_once INIT::$UTILS_ROOT . "/utils.class.php";
require_once INIT::$UTILS_ROOT . "/fileFormatConverter.class.php";
require_once ( 'converter_checker.inc.php' );

//init params
$source_lang = "en-US";
$target_language = "it-IT";
$original_dir ='original' ;
$converted_dir ='converted' ;
$teamplate_dir ='expected' ;
$path = $ROOT. '/lib/utils/converter_checker';

//get an isntance of available converters to scan on
$active_converters=fileFormatConverter::$converters;

//start logging
echo "-----------------------------------------------------------\n";
echo "starting at ".date("Y-m-d H:i")."\n";

//get converter
$converterFactory = new fileFormatConverter();

echo "scan converters load to detect hung WINWORD\n";
//scanning load of each converter
foreach($active_converters as $current_converter=>$weight){
	echo "getting tasklist for $current_converter\n";
	$total_load=0;
	$processes=fileFormatConverter::checkNodeProcesses($current_converter);

	//if java monitor provided zero processes is almost certain that the VM is locked and the request tiemd out
	if(empty($processes)){
		echo "no answer from Java monitor, REBOOTING\n";
		rebootConverter($current_converter,$server_map,$active_converters);
	}else{

		foreach($processes as $process){
			if(strpos($process[4],'WINWORD.EXE')!==FALSE){
				$total_load+=$process[0];
			}
		}
		if($total_load>60){
			echo "found harmful instance of WINWORD, REBOOTING\n";
			rebootConverter($current_converter,$server_map,$active_converters);
		}else{
			echo "no harmful, hung WINWORD found\n";
		}
	}
	echo "\n";
}

echo "attempt to convert files to detect service failures\n";
//clean
echo "removing tmp files\n";
shell_exec("rm -fr $path/$converted_dir/");
echo "recreating tmp dir\n";
mkdir("$path/$converted_dir/",0777,true);
echo "\n";

//get files
$dir=scandir("$path/$original_dir");

//for each file, start conversion test to ensure consistency
foreach($dir as $file_name){
	//skip hardlinks
	if(in_array($file_name,array('.','..'))) continue;

	echo "scanning $file_name\n";
	//get extension
	$ext = pathinfo($file_name, PATHINFO_EXTENSION);
	//get path
	$file_path = "$path/original/$file_name";


	//pick all converters
	foreach($active_converters as $current_converter=>$weight){

		echo "sending $file_name on converter $current_converter\n";

		//convert
		$convertResult = $converterFactory->convertToSdlxliff($file_path, $source_lang, $target_language,$current_converter);
		//"do we have to reboot?" flag
		$reboot=false;

		//if conversion happened, in first place
		if ($convertResult['isSuccess'] == 1) {
			//we have a response, now compare with testset of conversions

			echo "file converted, now comparing\n";
			//get converted content
			$xliffContent = $convertResult['xliffContent'];

			echo "adapting to template of storage 10.11.0.11\n";
			$xliffContent=preg_replace('/10.11.0.[0-9]{1,3}/sm','10.11.0.11',$xliffContent);

			//store for debugging
			if(!file_exists("$path/converted/$current_converter")){
				mkdir("$path/converted/$current_converter",0777,true);
			}
			file_put_contents("$path/converted/$current_converter/$file_name.sdlxliff",$xliffContent);

			//get template
			$template_xliffContent=file_get_contents("$path/expected/$file_name.sdlxliff");

			//normalize content on current converter
			$storage=$converterFactory->getValidStorage($current_converter);

			//compare files: file lenght is weak, but necessary (because transunit-ids change and file blob changes from machine to machine) and sufficient (the text should really be the same)

			//get lenghts
			$template_len=strlen($template_xliffContent);
			$converted_len=strlen($xliffContent);
			echo "$file_name: ".$template_len.", ".$converted_len."\n";

			$max=max($template_len,$converted_len);
			$min=min($template_len,$converted_len);

			//compare distance
			$diff=$min/$max;

			if($diff<0.99){
				//file differ too much, reboot
				$reboot=true;
			}
		}else{
			//the file was not even converted, reboot
			$reboot=true;
		}

		if($reboot){
			//reboot
			echo "bad  response from $current_converter, REBOOTING\n";
			rebootConverter($current_converter,$server_map,$active_converters);
		}else{
			//everything was good
			echo "good response from $current_converter\n";
		}
		echo "\n";

	}//end foreach converter
}//end foreach file

echo "end\n\n";

function rebootConverter($current_converter,&$server_map,&$converters){
	//get right server by scanning server map util find the right one
	foreach($server_map as $server){
		if($current_converter==$server['vm']){
			$rebootable_converter=$server;
			break;
		}
	}
	$ret=launchCommand($rebootable_converter,"restart");
	$message = implode("\n", $ret[1]);
	if ($ret[0] == 0) {
		$status = "OK";
	} else {
		$status = "KO";
	}
	//send mail to warn us
	send_mail("antonio-htr", "antonio-htr@translated.net", "antonio", "alert@matecat.com", "CONVERTER VM ".$rebootable_converter['vm']." locked", "Trying to restart $status:  $message");
	echo $rebootable_converter['vm']." down, executing the following:\n$message\n";


	//removing just rebooted converter from the dance to let him recover
	unset($converters[$current_converter]);
}
?>
