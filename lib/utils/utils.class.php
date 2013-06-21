<?php

//include_once ("../inc/config.class.php"); // only for testing purpose
class Utils {


	public static function is_assoc($array) {
		return is_array($array) AND (bool) count(array_filter(array_keys($array), 'is_string'));
	}

	public static function curl_post($url, &$d, $opt = array()) {
		//echo "1 - " . memory_get_usage(true)/1024/1024;
		//echo "\n";
		if (!self::is_assoc($d)) {
			throw new Exception("The input data to " . __FUNCTION__ . "must be an associative array", -1);
		}
		//  print_r ($d); exit;
		$ch = curl_init();

		// $data = http_build_query($d);
		// $d = null;
		//echo "2 - " . memory_get_usage(true)/1024/1024;
		//echo "\n";

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_USERAGENT, "Matecat-Cattool/v" . INIT::$BUILD_NUMBER);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $d);
		if (self::is_assoc($opt) and !empty($opt)) {
			foreach ($opt as $k => $v) {

				if (stripos($k, "curlopt_") === false or stripos($k, "curlopt_") !== 0) {
					$k = "curlopt_$k";
				}
				$const_name = strtoupper($k);
				//echo $const_name;exit;
				if (defined($const_name)) {
					curl_setopt($ch, constant($const_name), $v);
				}
			}
		}


		$output = curl_exec($ch);

		//echo "2 - " . memory_get_usage(true)/1024/1024;
		$info = curl_getinfo($ch);

		curl_close($ch);
		/* if ($output === false || $info != 200) {
		   $output = null;
		   } */

		//print_r ($info);

		return $output;
	}

	public static function getRealIpAddr() {
		$ip="";
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {   //check ip from share internet              
			$ip = $_SERVER['HTTP_CLIENT_IP'];
			//log::doLog("ip 1 $ip");
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {   //to check ip is pass from proxy
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			//log::doLog("ip 2 $ip");
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
			//log::doLog("ip 3 $ip");
		}
		return $ip;
	}

}

?>
