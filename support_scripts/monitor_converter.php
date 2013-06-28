<?php

define("USER", "root");
define("PASS", "marcofancydandy");
define("CHECK_FILE", "up.html");

//array schema "VM ip" => "host ip"
$server_map = array("10.30.1.247" => "10.30.1.250");

foreach ($server_map as $vm => $host) {
    //echo "pinging $host\n";
    $ping_host_res = shell_exec("ping -c1 $host");
    $ping_host_res = explode("\n", $ping_host_res);
    //echo "done\n\n";
    //print_r ($ping_host_res);
    if (strpos($ping_host_res[1], "Unreachable") !== false) {
        $m = "Host $host (and hence thh the hosted vm $vm) do not respond to ping.
              Check manually if the host is still running;
                ";
        // echo "sending email\n";
        send_mail("antonio-htr", "antonio-htr@translated.net", "antonio", "antonio@translated.net", "CONVERTER VM $host locked", "$m");
        //echo "done\n\n";
        continue;
    }


    // $vm_check=@file_get_contents("http://$vm/".CHECK_FILE);
    $vm_check = mycurl($vm, CHECK_FILE);
    if (!$vm_check) {
        //echo "server $vm down try to  restart\n";
        $ret = launchCommand($host);
        $message = implode("\n", $ret[1]);
        if ($ret[0] == 0) {
            $status = "OK";
        } else {
            $status = "KO";
        }
        send_mail("antonio-htr", "antonio-htr@translated.net", "antonio", "antonio@translated.net", "CONVERTER VM $host locked", "Trying to restart $status:  $message");
    } else {
        echo "server $vm up\n";
    }
    echo "\n";
}

function launchCommand($host, $command = "restart") {
    $cmd = array();
    $ret = array();
    switch ($command) {
        case 'start':
            $cmd[] = "screen -d -m VBoxHeadless -n --startvm 'TradosAPI'";
            break;
        case 'stop':
            $cmd[] = " VBoxManage controlvm 'TradosAPI' poweroff";
            break;
        case 'restart':
        default:
            $cmd[] = " VBoxManage controlvm 'TradosAPI' poweroff";
            $cmd[] = "screen -d -m VBoxHeadless -n --startvm 'TradosAPI'";
    }
    $conn = ssh2_connect($host, 22);
    $authResult = ssh2_auth_password($conn, USER, PASS);
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
    //print_r($ret);
    return array(0, $ret);
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

?>