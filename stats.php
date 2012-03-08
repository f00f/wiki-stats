<?php
/** Statistik-Erweiterung fuer Mediawiki.
 *
 * Entwickelt von le Nique dangereux
 *
 * Verwendung:
 * <stats>
 * param1=val1
 * param2=val2
 * ...
 * </stats>
 *
 * Moegliche Parameter (andere werden ignoriert):
 * name - wen soll die Statistik enthalten?
 *       Moegliche Werte:
 *       "Gesamt" [default] - fuer die ganze Mannschaft (``target'' erforderlich)
 *       Spielername - nur dieser Spieler
 *       Liste von Namen - (noch nicht unterstuetzt) diese Spieler (z.B. fuer Torschuetzenlisten)
 * target - was soll angezeigt werden?
 *       Moegliche Werte: "Gewonnen", "Verloren", "Unentschieden",
 *                        "Tore", "Gegentore", "SerieG", "SerieV"
 * art - welche Wettkaempfe sollen beruecksichtigt werden?
 *       nicht gesetzt -> alle
 *       "BUL", "CC", "DM"
 *       Kombination mit "+", z.B. BUL+DM
 * start, ende - welcher Zeitraum soll beruecksichtigt werden?
 *       ``start'' nicht gesetzt -> gesamt
 *       ``ende'' nicht gesetzt -> ``start'' + 1 Jahr
 *       Format: dd.mm.yyyy
 */

# to activate the extension, include it from your LocalSettings.php
# with: include("extensions/YourExtensionName.php");

$wgExtensionFunctions[] = "wfStats";
function wfStats() {
	global $wgParser;
	$wgParser->setHook( "stats", "uwr_stats" );
}

// Parses parameters given to extension
function parseParams(&$input) {
	global $uwr_stats_aArt;
	global $uwr_stats_allParams;
	global $uwr_stats_fehler;
	
	$uwr_stats_fehler = FALSE;

	// get input args
	$aParams = explode("\n", $input); // ie 'website=http://www.whatever.com'

	foreach($aParams as $sParam) {
		$aParam = explode("=", $sParam, 2); // ie $aParam[0] = 'website' and $aParam[1] = 'http://www.whatever.com'
		if( count( $aParam ) < 2 ) // no arguments passed
		continue;

		$sType = strtolower(trim($aParam[0])); // ie 'website'
		$sArg = trim($aParam[1]); // ie 'http://www.whatever.com'

		switch ($sType) {
		case 'start':
		case 'ende':
			// clean up
			if (FALSE !== date_create($sArg)) {
				$uwr_stats_allParams[$sType] = $sArg; // 2012
			}
			break;
		case 'name':
			if ($sArg != "Gesamt") {
				// see if column for player exists
				$res = mysql_query('DESCRIBE `stats_games`');
				$uwr_stats_fehler = TRUE;
				mysql_data_seek($res, 9);
				while($row = mysql_fetch_array($res)) {
					if ($sArg == $row['Field']) { // Nik
						$uwr_stats_allParams[$sType] = $sArg;
						$uwr_stats_fehler = FALSE;
					}
				}    
			}
			break;
		case 'target':
			$allowedTarget = array('Tore', 'Gegentore',
								'Gewonnen', 'Verloren', 'Unentschieden',
								'SerieG', 'SerieV');
			if (in_array($sArg, $allowedTarget)) {
				$uwr_stats_allParams[$sType] = $sArg; // Tore
			} else {
				$uwr_stats_fehler = TRUE;
				return false;
			}
			break;
		case 'art':
			$allowedArt = array('BUL', 'CC', 'DM');
			$uwr_stats_aArt = explode("+", $sArg);
			foreach($uwr_stats_aArt as $ArtPruef) {
				if (! in_array($ArtPruef, $allowedArt)) {
					$uwr_stats_fehler = TRUE;
					return false;
				}
			}
			break;

		}
		if ($uwr_stats_fehler) {
			print 'Fehler in den Parametern.';
			return false;
		}
	}
	// do not return anything - this function modifies a global variable
}

// Build an SQL condition based on the parameters
function build_filter($from, $to) {
	global $uwr_stats_aArt;

	// date range
	$filter = "(`Datum` BETWEEN '".$from."' AND '".$to."')";

	// filter by tournament-type
	if (isset($uwr_stats_aArt) && count($uwr_stats_aArt) > 0) {
		$filter .= " AND (`Art`='"
					. implode("' OR `Art`='", $uwr_stats_aArt)
					. "') ";
		/*
		$Merker = FALSE;
		foreach($uwr_stats_aArt as $ArtPruef) {
			if (! $Merker) {
				$filter .= " AND (`Art`='".$ArtPruef."'";
				$Merker = TRUE;
			} else {
				$filter .= " OR `Art`='".$ArtPruef."'";
			}
		}
		if ($Merker) {
			$filter .= ") ";
		}
		*/
	}

	return $filter;
}

// Get number of goals (pos. or neg.)
// Params:
// type - "Tore" or "Gegentore"
function getNumGoals($type, $filter) {
	$sum = 0;
	// TODO: geht "SELECT SUM(`{$type}`) AS 'SUM' FROM `stats_games` WHERE {$filter}"
	$sqlres = mysql_query("SELECT `{$type}` FROM `stats_games` WHERE {$filter}");
	while ($row = mysql_fetch_assoc($sqlres)) {
		$sum += $row[ $type ];
	}
	return $sum;
}

// find longest series of conseq. wins
function getSeriesWon($filter) {
	$sqlres = mysql_query("SELECT * FROM `stats_games` WHERE {$filter}"
						. " ORDER BY `Datum` DESC, `SpielNr` DESC");
	$AktuelleSiegSerie=0;
	$LaengsteSiegSerie=0;
	$LaengsteSiegSerieMerker=0;
	while ($sqlobj =  mysql_fetch_object($sqlres)) {
		if ($sqlobj->Tore > $sqlobj->Gegentore AND $AktBeendet==0) {
			$AktuelleSiegSerie++;
		}

		if ($sqlobj->Tore > $sqlobj->Gegentore) {
			$LaengsteSiegSerieMerker++;
		}

		if ($sqlobj->Tore <= $sqlobj->Gegentore) {
			$LaengsteSiegSerieMerker=0;
			$AktBeendet=1;
		}

		if ($LaengsteSiegSerie < $LaengsteSiegSerieMerker) {
			$LaengsteSiegSerie=$LaengsteSiegSerieMerker;
		}
	}
	return $LaengsteSiegSerie;
}

// find longest series of conseq. defeats
function getSeriesLost($filter) {
	$sqlres = mysql_query("SELECT * FROM `stats_games` WHERE {$filter}"
						. " ORDER BY `Datum` DESC, `SpielNr` DESC");
	$LaengsteNiederlageSerie=0;
	$LaengsteNiederlageSerieMerker=0;

	while ($sqlobj =  mysql_fetch_object($sqlres)) {
		if ($sqlobj->Tore < $sqlobj->Gegentore) {
			$LaengsteNiederlageSerieMerker++;
		}

		if ($sqlobj->Tore >= $sqlobj->Gegentore) {
			$LaengsteNiederlageSerieMerker = 0;
		}

		if ($LaengsteNiederlageSerie < $LaengsteNiederlageSerieMerker) {
			$LaengsteNiederlageSerie = $LaengsteNiederlageSerieMerker;
		}
	}
	return $LaengsteNiederlageSerie;
}

// Get number of goals for a given player.
// Params:
// player - player name
// filter - SQL condition to filter stats table
function getNumGoalsForPlayer($player, $filter) {
	$ret = 0;
	// TODO: geht "SELECT SUM(`{$player}`) FROM `stats_games` WHERE `{$player}` <> 255 AND {$filter}"
	$sqlres = mysql_query("SELECT * FROM `stats_games` WHERE {$filter}");
	$Merker = FALSE;
	while ($sqlobj =  mysql_fetch_object($sqlres)) {
		if ($sqlobj->$player != 255) {
			$ret += $sqlobj->$player;
			$Merker = TRUE;
		}
	}
	if (! $Merker) {
		return "-";
	}
	return $ret;
}

// Get total number of won (G), draw (U), or lost (V) games
// Params:
// GUV - [GUV]
// filter - SQL condition to filter stats table
function getNumGUV($GUV, $filter) {
	$ops = array('G' => '>', 'U' => '=', 'V' => '<');
	$op = $ops[ $GUV{0} ]; // consider only first char
	$sqlres = mysql_query("SELECT COUNT(`ID`) AS 'COUNT' FROM `stats_games` "
						. "WHERE `Tore` {$op} `Gegentore` AND {$filter}");
	$row = mysql_fetch_assoc($sqlres);
	return $row['COUNT'];
}

// the callback function for converting the input text to HTML output
function uwr_stats($input) {
	global $uwr_stats_aArt;
	global $uwr_stats_allParams;
	global $uwr_stats_fehler;

	// set default arguments
	$uwr_stats_allParams = array(
		'start'  => "",
		'ende'   => "",
		'name'   => "Gesamt",
		'target' => "",
		);
	$uwr_stats_aArt = array();
	$uwr_stats_fehler = FALSE;
	$output = "";
	
	// parse and check parameters
	parseParams($input);
	if ($uwr_stats_fehler) {
		return "Fehlerhafte Parameter (1)";
	}
	// target nicht gesetzt
	if ('' == $uwr_stats_allParams['target'] && "Gesamt" == $uwr_stats_allParams['name']) {
		$uwr_stats_fehler = TRUE;
	}
	if ($uwr_stats_fehler) {
		return $uwr_stats_allParams['name']. "Fehlerhafte Parameter (2)";
	}

	// ``start'' nicht gesetzt (auf unendlich setzen)
	if ('' == $uwr_stats_allParams['start']) {
		$uwr_stats_allParams['start'] = "1900-01-01";
		$uwr_stats_allParams['ende']  = "2200-01-01";
	}
	$DateA=date_create($uwr_stats_allParams['start']);
	// ``ende'' nicht eintegragen (auf ``start'' +1 Jahr setzen)
	if ('' == $uwr_stats_allParams['ende']) {
		$uwr_stats_allParams['ende'] = $DateA->format('d.m') . "." . ($DateA->format('Y')+1);
	}
	$DateE=date_create($uwr_stats_allParams['ende']);

	$SuchString = build_filter($DateA->format('Y-m-d'), $DateE->format('Y-m-d'));

	// build output
	if ("Gesamt" != $uwr_stats_allParams['name']) {
		// TODO: add support for list of players
		// single player stats
		$output = getNumGoalsForPlayer($uwr_stats_allParams['name'], $SuchString);
	} else { // Gesamt != name
		// whole team stats
		switch ($uwr_stats_allParams['target']) {
		case 'Tore':
		case 'Gegentore':
			$output = getNumGoals($uwr_stats_allParams['target'], $SuchString);
			break;
		case 'Gewonnen':
		case 'Unentschieden':
		case 'Verloren':
			$output = getNumGUV($uwr_stats_allParams['target'], $SuchString);
			break;
		case 'SerieG':
			$output = getSeriesWon($SuchString);
			break;
		case 'SerieV':
			$output = getSeriesLost($SuchString);
			break;
		}
	}

	//$output = mysql_num_rows($sqlres)." : ".$uwr_stats_allParams['name']." : ".$uwr_stats_allParams['target'];

	// return the output
	return $output;
}
?>