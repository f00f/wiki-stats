<?php
// @uses $sql

// In $_POST and form fields, player names are escaped
// (as array indices or field names, resp.)
function players_EscapeName($spieler) {
	return str_replace(' ', '_', $spieler);
}

function players_FindAll()
{
	global $sql;

	// Spielernamen aus der Datenbank laden.
	// Für jeden Spieler gibt es eine Spalte, die ersten N Spalten beschreiben das Spiel.
	$res = $sql->query('DESCRIBE `stats_games`');
	mysql_data_seek($res, NUM_NONPLAYER_COLS);

	$all_player_names = array();
	while($row = mysql_fetch_array($res))
	{
		array_push($all_player_names, $row['Field']);
	}

	mysql_free_result($res);

	return $all_player_names;
}
