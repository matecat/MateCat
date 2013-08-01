<?php


$mysql_hostname  = "10.30.1.241";            // Database Server machine

$mysql_database  = "matecat_sandbox";     // Database Name
$mysql_username  = "matecat";                 // Database User
$mysql_password  = "matecat01";            // Database Password

$mysql_link = mysql_connect($mysql_hostname, $mysql_username, $mysql_password);
mysql_select_db($mysql_database, $mysql_link);

$query="select j.id, source, target, p.name, j.password, p.create_date, p.tm_analysis_wc, p.remote_ip_address  
from jobs j 
inner join projects p on p.id=j.id_project 
order by p.id desc limit 0,100";

$result = mysql_query($query);
echo '<html><head>
<style>
	body { font-family: Calibri, Arial; }
	td { padding: 5px 10px; border-bottom: 1px solid #ccc;} 
	tr { }
	tr:hover { background: #EEEEEE; }
</style>
</head>
<body>

<table>
			<tr>
				<th>Create date</th>
				<th>Nome</th>
				<th>Source Language</th>
				<th>Target Language</th>
				<th>Matecat Eq. Words</th>
				<th> Customer IP </th>
			</tr>
			';

while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
	if ($row[7]=='UNKNOWN') $row[7]="--";
	echo "<tr>
		<td>$row[5]</td>
		<td><a target=\"_blank\" href=\"http://matecat.translated.home/translate/$row[3]/$row[1]-$row[2]/$row[0]-$row[4]\">$row[3]</a></td>
		<td>$row[1]</td>
		<td>$row[2]</td>
		<td align=right>".number_format($row[6],0,',','.')."</td>
		<td>$row[7]</td>
	      </tr>";
//    printf("project id: %s  source: %s  target: %s  name: %s", $row[0], $row[1], $row[2], $row[3]);  
}

echo '</table></body></html>';
/*
$markup = 	'<html><head></head><body><table>
			<tr>
				<th>Nome</th>
				<th>Source Language</th>
				<th>Target Language</th>
				<th>Link</th>
			</tr>
			<tr>
				<td>...</td>
				<td>...</td>
				<td>...</td>
				<td>...</td>
			</tr>
			</table></body></html>';
*/			
//echo $markup;


?>
