<?php
// @uses $sql

function tournaments_FindAll($groupYears = true)
{
	if ($groupYears) {
		return tournaments_FindAllGroupYears();
	} else {
		return tournaments_FindAllSeparateYears();
	}
}

function tournaments_FindAllSeparateYears()
{
	global $sql;

	$q = "SELECT CONCAT(`Turnier`, ' ', YEAR(`Datum`)) AS `FullName`, `Turnier`, YEAR(`Datum`) AS `Jahr`"
		. " FROM `stats_games`"
		. " GROUP BY `FullName`"
		. " ORDER BY `Datum` DESC";
	$res = $sql->query($q);

	if (mysql_num_rows($res) < 1)
	{
		//$error_msg = 'err_no_tournaments_found';
		return false;
	}

	$tournaments = array();
	while($tournament = mysql_fetch_object($res)) {
		$tournaments[] = $tournament;
	}
	mysql_free_result($res);

	return $tournaments;
}

function tournaments_FindAllGroupYears()
{
	global $sql;

	$q = "SELECT `Turnier`, YEAR(`Datum`) AS `Jahr`"
		. " FROM `stats_games`"
		. " GROUP BY `Turnier`"
		. " ORDER BY `Datum` DESC";
	$res = $sql->query($q);

	if (mysql_num_rows($res) < 1)
	{
		//$error_msg = 'err_no_tournaments_found';
		return false;
	}

	$tournaments = array();
	while($tournament = mysql_fetch_object($res)) {
		$tournaments[] = $tournament;
	}
	mysql_free_result($res);

	return $tournaments;
}
