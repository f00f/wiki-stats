<?php
require_once "./inc/sql.inc.php";

//SpielerHinzufügen
if (@$_REQUEST['Add'] == "1" AND @$_REQUEST['AddName'])
{
	$sql->db_connect();
	$sql->query("ALTER TABLE `stats_games`
		ADD `{$_REQUEST['AddName']}` TINYINT
		UNSIGNED NOT NULL DEFAULT '255'");
	$sql->close();
}
//SpielerHinzufügen

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");  
header('Location: ./index.php');
