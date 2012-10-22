<?php
$text = (isset($_POST['csv']) ? $_POST['csv'] : false);

header("Content-Disposition: attachment; filename=\"export.csv\"");
header("Content-Type: application/force-download");
header("Content-Length: " . filesize($File));
header("Connection: close");

echo($text);
exit();

?>


