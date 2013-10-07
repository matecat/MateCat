<?php

define("SSH_USER", "root");
define("SSH_PASS", "marcofancydandy");
define("CHECK_FILE", "up.html");

//array schema "VM ip" => "host ip"
$server_map = array(
		array('vm'=>'10.11.0.2','host'=>'10.30.1.250','instance_name'=>'TradosAPI'),
		array('vm'=>'10.11.0.10','host'=>'10.30.1.250','instance_name'=>'TradosAPI-2'),
		array('vm'=>'10.11.0.18','host'=>'10.30.1.251','instance_name'=>'TradosAPI-3'),
		array('vm'=>'10.11.0.26','host'=>'10.30.1.251','instance_name'=>'TradosAPI-4'),
		array('vm'=>'10.11.0.34','host'=>'10.30.1.232','instance_name'=>'TradosAPI-5'),
		array('vm'=>'10.11.0.42','host'=>'10.30.1.232','instance_name'=>'TradosAPI-6')
		);


function launchCommand($converter, $command = "restart") {
	$cmd = array();
	$ret = array();
	switch ($command) {
		case 'start':
			$cmd[] = "screen -d -m VBoxHeadless --startvm '".$converter['instance_name']."'";
			break;
		case 'stop':
			$cmd[] = " VBoxManage controlvm '".$converter['instance_name']."' poweroff";
			break;
		case 'restart':
		default:
			$cmd[] = " VBoxManage controlvm '".$converter['instance_name']."' poweroff";
			$cmd[] = "screen -d -m VBoxHeadless --startvm '".$converter['instance_name']."'";
	}
	echo "connecting to ".$converter['host']."\n";

	$conn = ssh2_connect($converter['host'], 22);
	$authResult = ssh2_auth_password($conn, SSH_USER, SSH_PASS);
	if (!$authResult) {
		$ret[] = "Incorrect password !!!\n";
		return array(-1, $ret);
	} else {
		$ret[] = "Password OK\n";
		foreach ($cmd as $c) {
			$stream = ssh2_exec($conn, $c);
			stream_set_blocking($stream, true);
			$stream_out = ssh2_fetch_stream($stream, SSH2_STREAM_STDIO);
			$ret_content= stream_get_contents($stream_out);

			$ret[] = "command is $c";
			$ret[] = $ret_content;
		}
	}
	return array(0, $ret);
}


function mycurl($host, $path) {

	$ch = curl_init("http://$host");
	curl_setopt($ch, CURLOPT_NOBODY, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_exec($ch);
	$retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if (200 == $retcode) {
		return 1;
	} else {
		return 0;
	}
}

function send_mail($from_name, $from_email, $to_name, $to_email, $subject, $message, $charset = "utf-8") {  //my mails are not spam!!
	$all_emails = split("[ \,]", trim($to_email));
	$all_emails = array_filter($all_emails, 'trim');
	// print_r($all_emails); exit;

	$from_name = str_replace(',', ' ', $from_name);

	$from_email_temp = split("[ \,]", trim($from_email));
	$from_email = $from_email_temp[0];

	// per garantire questi 10 traduttori che hanno due email
	foreach ($all_emails as $to_email) {
		$headers = "MIME-Version: 1.0\r\n";
		$headers .= "Content-type: text/plain; charset=" . $charset . "\r\n";
		$headers .= "X-Mailer: Translated Mailer\r\n";
		$headers .= "X-Sender: <" . $from_email . ">\r\n";
		$headers .= "Return-Path: <" . $from_email . ">\r\n";
		$headers .= "From: " . $from_name . " <" . $from_email . ">\r\n";
		$headers .= "To: " . $to_name . " <" . $to_email . ">\r\n";
		//              $headers .= "Bcc: $from_email\r\n";
		$result = mailfrom($from_email, $to_email, $subject, $message, $headers, "ONLY_HEADERS");
		if (!$result) {
			return false;
		}   // SE ANCHE UN SOLO INDIRIZZO DA ERRORE DO ERRORE!
	}
	// BUG: BISOGNA LEGGERE LE SPECS SENDMAIL PER SAPERE SE E' ANDATO....
	return $result;
}

function mailfrom($fromaddress, $toaddress, $subject, $body, $headers, $add_headers = "ADD_HEADERS") {
	$fp = popen('/usr/sbin/sendmail -f' . $fromaddress . ' ' . $toaddress, "w");
	if (!$fp)
		return false;

	if ($add_headers <> "ONLY_HEADERS") { // se headers contiene il to:
		fputs($fp, "To: $toaddress\n");
	}
	fputs($fp, "Subject: $subject\n");
	fputs($fp, $headers . "\n\n");
	fputs($fp, $body);
	fputs($fp, "\n");
	pclose($fp);
	return true;
}

?>

