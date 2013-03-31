<?php
// @uses $sql

function games_FindByID($id)
{
	global $sql;
	if (!is_numeric($id) || 0 >= $id)
	{
		//$error_msg = 'err_invalid_id';
		return false;
	}

	$res = $sql->query("SELECT * FROM `stats_games` WHERE `ID` = {$id}");

	if (mysql_num_rows($res) != 1)
	{
		//$error_msg = 'err_id_not_found';
		return false;
	}

	return mysql_fetch_object($res);
}

function games_FindAll()
{
	global $sql;

	$res = $sql->query("SELECT * FROM `stats_games` ORDER BY `Datum` DESC, `SpielNr` DESC");

	if (mysql_num_rows($res) < 1)
	{
		//$error_msg = 'err_no_games_found';
		return false;
	}

	$games = array();
	while($match = mysql_fetch_object($res)) {
		$games[] = $match;
	}
	mysql_free_result($res);

	return $games;
}

function games_FindLatest($num)
{
	global $sql;

	$res = $sql->query("SELECT * FROM `stats_games` ORDER BY `Datum` DESC, `SpielNr` DESC LIMIT {$num}");

	if (mysql_num_rows($res) < 1)
	{
		//$error_msg = 'err_no_games_found';
		return false;
	}

	$games = array();
	while($match = mysql_fetch_object($res)) {
		$games[] = $match;
	}
	mysql_free_result($res);

	return $games;
}
