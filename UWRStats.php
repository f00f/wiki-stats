<?php
/** Statistik-Erweiterung fuer Mediawiki.
 *
 * Entwickelt von le Nique dangereux und f00f
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
 *       Ein Spielername - nur dieser Spieler
 *       Liste von Namen - diese Spieler (fuer Torschuetzenlisten)
 *       "torschuetzen", "torschuetzenS", "torschuetzenG" - alle Spieler, die ein Tor erzielt haben (fuer Torschuetzenlisten)
 *       "mitspieler" - alle die dabei waren (für Anwesenheitsliste)
 *       "gesamtbilanz" - Ewige Gesamtbilanz wird als Tabelle ausgegeben   
 * target - was soll angezeigt werden?
 *       Moegliche Werte: "Gewonnen", "Verloren", "Unentschieden",
 *                        "Tore", "Gegentore", "SerieG", "SerieV", "All",
 *                        "Turniere"
 * art - welche Wettkaempfe sollen beruecksichtigt werden?
 *       nicht gesetzt -> alle
 *       "LL", "BUL", "CC", "DM", ..., oder die Spiel-ID
 *       Kombination mit "+", z.B. BUL+DM
 * start, ende - welcher Zeitraum soll beruecksichtigt werden?
 *       ``start'' nicht gesetzt -> gesamt
 *       ``ende'' nicht gesetzt -> ``start'' + 1 Jahr
 *       Format: dd.mm.yyyy
 * saison - Jahreszahl, ueberschreibt ``start'' und ``ende''
 *       setzt ``start'' auf 1. Sep. <saison> und ``ende'' auf 31. Aug. <saison+1>
 *       Bsp.: Saison 2011/2012 -> saison=2011
 */

/*
TODO:
- `art' negierbar machen, z.B.: art=! BM
- `order' zum sortieren (chrono und anti-chrono)
- saison=alle
*/

# to activate the extension, include it from your LocalSettings.php
# with: include("extensions/UWRStats/UWRStats.php");

// load config values
require_once 'config.inc.php';

$wgExtensionFunctions[] = "wfStats";
$wgExtensionCredits['parserhook'][] = array(
       'path' => __FILE__,
       'name' => 'UWRStats',
       'author' =>'[[User:Nik|le Nique dangereux]] und [[User:Hannes|f00f]]',
       'url' => 'http://ba.uwr1.de/wiki/?title=UWR_Stats',
       'description' => 'Verschiedene Statistikfunktionen, siehe z.B. Torschützenliste der [[1. Bundesliga Süd 2011/2012]]',
       'version'  => UWR_STATS_VERSION,
       );

function wfStats() {
	global $wgParser;
	$wgParser->setHook( "stats", "uwr_stats_r" );
}

// fetch player names from database schema
function getPlayerNamesFromDB() {
	$allNames = array();
	$dbr = wfGetDB(DB_SLAVE);
	$sql = 'DESCRIBE `stats_games`';
	$res = $dbr->query($sql);
	$res->seek(NUM_NONPLAYER_COLS); // skip data columns
	while($row = $res->fetchRow()) {
		$allNames[] = $row['Field'];
	}
	return $allNames;
}

// for a list of player names, check if all corresponding columns exist
function checkPlayerNames($playersString) {
	$allowedNames = getPlayerNamesFromDB();
	$players = explode(',', $playersString);
	foreach ($players as $p) {
		if (! in_array($p, $allowedNames)) {
			return false;
		}
	}
	return true;
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
			if ('ende' == $sType && 'heute' == $sArg) {
				$uwr_stats_allParams[$sType] = date('d.m.Y'); // 2012
			}
			else if (FALSE !== date_create($sArg)) {
				$uwr_stats_allParams[$sType] = $sArg; // 2012
			}
			break;
		case 'saison':
			$test = (int) $sArg;
			if ($test > 1900 && $test < 2200) {
				$uwr_stats_allParams[$sType] = $sArg;
			}
			break;
		case 'name':
			if ('Gesamt' == $sArg || 'torschuetzen' == $sArg || 'torschuetzenS' == $sArg || 'mitspieler' == $sArg || 'gesamtbilanz' == $sArg  || 'torschuetzenG' == $sArg) {
				$uwr_stats_allParams[$sType] = $sArg;
			} else {
			//if ($sArg != "Gesamt") {
				$rv = checkPlayerNames($sArg);
				if (! $rv) {
					$uwr_stats_fehler = TRUE;
					return 'E3: Wert "'.$sArg.'" ist fuer "name" nicht erlaubt/unterstuetzt.';
				}
				$uwr_stats_allParams[$sType] = $sArg;
			}
			break;
		case 'target':
			$allowedTarget = array('Tore', 'Gegentore',
								'Gewonnen', 'Verloren', 'Unentschieden',
								'SerieG', 'SerieV', 'All', 'Turniere');
			if (in_array($sArg, $allowedTarget)) {
				$uwr_stats_allParams[$sType] = $sArg; // Tore
			} else {
				$uwr_stats_fehler = TRUE;
				return 'E1: Wert "'.$sArg.'" ist fuer "target" nicht erlaubt/unterstuetzt.';
			}
			break;
		case 'art':
			$allowedArt = array('LL', '2BL', 'BUL', 'REL',
								'JUN', 'JUG', 'BM', 'DM', 'CC',
								'BOT', 'FT');
			$uwr_stats_aArt = explode("+", $sArg);
			foreach($uwr_stats_aArt as $ArtPruef) {
				if (! (in_array($ArtPruef, $allowedArt) || is_numeric($ArtPruef)) ) {
				    $uwr_stats_fehler = TRUE;
					return 'E2: Wert "'.$ArtPruef.'" ist fuer "art" nicht erlaubt/unterstuetzt.';
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
// - Date: `start' and `ende'
// - Art
function build_filter($from, $to) {
	global $uwr_stats_aArt;

	// date range
	$filter = "(`Datum` BETWEEN '".$from."' AND '".$to."')";

	// filter by tournament-type
	if (isset($uwr_stats_aArt) && count($uwr_stats_aArt) > 0) {
		if (is_numeric($uwr_stats_aArt[0])) {
			// individual game(s)
			$filter .= " AND (`ID`='"
					. implode("' OR `ID`='", $uwr_stats_aArt)
					. "') ";
		} else {
			$filter .= " AND (`Art`='"
						. implode("' OR `Art`='", $uwr_stats_aArt)
						. "') ";
		}
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
	$dbr = wfGetDB(DB_SLAVE);
	$sql = "SELECT `{$type}` FROM `stats_games` WHERE {$filter}";
	$res = $dbr->query($sql);
	while ($row = $res->fetchRow()) {
		$sum += $row[ $type ];
	}
	return $sum;
}

// find longest series of conseq. wins
function getSeriesWon($filter) {
	$dbr = wfGetDB(DB_SLAVE);
	$sql = "SELECT * FROM `stats_games` WHERE {$filter}"
			. " ORDER BY `Datum` DESC, `SpielNr` DESC";
	$res = $dbr->query($sql);
	$AktuelleSiegSerie=0;
	$LaengsteSiegSerie=0;
	$LaengsteSiegSerieMerker=0;
	while ($sqlobj =  $res->fetchObject()) {
		if ($sqlobj->Tore > $sqlobj->Gegentore && $AktBeendet==0) {
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
	$dbr = wfGetDB(DB_SLAVE);
	$sql = "SELECT * FROM `stats_games` WHERE {$filter}"
			. " ORDER BY `Datum` DESC, `SpielNr` DESC";
	$res = $dbr->query($sql);
	$LaengsteNiederlageSerie=0;
	$LaengsteNiederlageSerieMerker=0;

	while ($sqlobj = $res->fetchObject()) {
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
//   player - player name
//   filter - SQL condition to filter stats table
function getNumGoalsForPlayer($player, $filter) {
	$dbr = wfGetDB(DB_SLAVE);
	$sql = "SELECT COUNT(`ID`) AS 'COUNT', SUM(`{$player}`) AS 'SUM' FROM `stats_games` "
			. "WHERE `{$player}` <> 255 AND {$filter}";
	$res = $dbr->query($sql);
	if ($res->numRows() < 1) {
		return "-";
	}
	$row = $res->fetchRow();
	if ($row['COUNT'] < 1) {
		return "-";
	}
	return $row['SUM'];
}

// Get number of goals for a given player, weighted by number of goals in each game.
// Params:
//   player - player name
//   filter - SQL condition to filter stats table
function getNumGoalsForPlayerG($player, $filter) {
	$Tore = 0;

	$dbr = wfGetDB(DB_SLAVE);
	$sql = "SELECT `ID` FROM `stats_games` WHERE {$filter}";
	$relevant_matches = $dbr->query($sql);
	while ($match = $relevant_matches->fetchObject()) {
		$sql = "SELECT `Tore`, `{$player}` FROM `stats_games` WHERE `ID` = {$match->ID}";
		$match_data_res = $dbr->query($sql);
		$match_data = $match_data_res->fetchObject();
		if (0 != $match_data->Tore && 255 != $match_data->$player)
		{
			$Tore += ($match_data->$player/($match_data->Tore));
		}
	}

	return round ($Tore, 2);
}

// Create list (prettytable) with number of goals for multiple players
// Params:
//   players - array with player names
//   filter - filter game type and date range
//   excludeZero - don't show players which didn't score
//   excludeNotPlayed - don't show players who didn't play
//   format - N=Normal, S=Short, G (same a N, with added column for weighted goals), M=Player names only
function getListOfGoalsForPlayers(&$players, $filter, $excludeZero = false, $excludeNotPlayed = false, $format = 'N') {
	$out="";
	$Allplayer="";
	$listOfGoals = array();
	$listOfGoalsG = array();
	
	if ($format=='M')
	{
		$excludeZero = false;
	}
	
	foreach ($players as $player) {
		$goals = 0;
		if ($format!='M') {
			$goals = getNumGoalsForPlayer($player, $filter);
		}
		if ($excludeZero && ('0' === $goals || 0 === $goals)) {
			continue;
		}
		if ($excludeNotPlayed && '-' == $goals) {
			continue;
		}
		$listOfGoals[$player] = $goals;
		if ($format=='G') {
			$goalsG = getNumGoalsForPlayerG($player,$filter);
			$listOfGoalsG[$player] = $goalsG;
		}
	}
	arsort($listOfGoals);
	
	if ($format=='N')
	{
		$out = '<table class="prettytable sortable">';
		$out .= '<tr><th>Name</th><th>Tore</th></tr>';
		foreach ($listOfGoals as $p => $g) {
			$out .= "<tr>"
				. "<td>{$p}</td>"
				. "<td style='text-align:center;'>{$g}</td>"
				. "</tr>";
		}
		$out .= '</table>';
	}

	if ($format=='G')
	{
		$out = '<table class="prettytable sortable mw-collapsible mw-collapsed">';
		$out .= '<tr><th>Name</th><th>Tore</th><th>Tore (gewichtet)</th></tr>';
		foreach ($listOfGoals as $p => $g) {
			$out .= "<tr>"
				. "<td>{$p}</td>"
				. "<td style='text-align:center;'>{$g}</td>"
				. "<td style='text-align:center;'>".number_format($listOfGoalsG[$p], 2, ',', '')."</td>"
				. "</tr>";
		}
		$out .= '</table>';
	}
	
	
	if ($format=='S')
	{
		foreach ($listOfGoals as $p => $g) {
			if ($out!="")
				{$out.=", ";}
			$out .= "{$p}";
			if ($g!="1")
				{$out .=" {$g}";}
		}
	}
	
	if ($format=='M')
  	{
		foreach ($listOfGoals as $p => $g) {
			if ($out!="")
				{$out.=", ";}
			$out .= "{$p}";
		}
  	}
	
	return $out;
}


// Erstelle Liste (prettytable) Gesamtbilanz
function getListOfGesamtbilanz($SuchString) {
	$dbr = wfGetDB(DB_SLAVE);
	$sql = "SELECT * FROM stats_games WHERE ".$SuchString." GROUP BY Gegner ORDER BY Gegner";
	$res = $dbr->query($sql);

	if ($res->numRows() < 1) {
		return "-";
	}
	$out="";
	$AnzahlSpiele=0;
	$AnzahlGewonnen=0;
	$GesTore=0; $GesGegenTore=0;
	$hSieg=0; $hSiegT=0; $hSiegG=0;
	$AnzahlVerloren=0;
	$hVer=0; $hVerT=0; $hVerG=0;
	$AnzahlUnentschieden=0;

	$out = '<table class="prettytable sortable mw-collapsible mw-collapsed">';
	$out .= '<tr><th>Gegner</th><th>Spiele</th><th>G</th><th>U</th><th>V</th><th class="unsortable">Tore</th><th class="unsortable">Höchster Sieg</th><th class="unsortable">Höchste Niederlage</th></tr>';

	while ($row = $res->fetchObject()) {
		$sql = "SELECT * FROM stats_games WHERE ".$SuchString." AND Gegner = '".$row->Gegner."'";
		$tempres = $dbr->query($sql);
		while ($rowtemp = $tempres->fetchObject()) {
			$GesTore = $GesTore + $rowtemp->Tore;
			$GesGegenTore = $GesGegenTore + $rowtemp->Gegentore;
			$AnzahlSpiele++;
			if ($rowtemp->Tore > $rowtemp->Gegentore) {
				$AnzahlGewonnen++;
				if($hSieg < $rowtemp->Tore - $rowtemp->Gegentore) {
					$hSieg = $rowtemp->Tore - $rowtemp->Gegentore;
					$hSiegT = $rowtemp->Tore;
					$hSiegG = $rowtemp->Gegentore;
				}
			}
			if ($rowtemp->Tore < $rowtemp->Gegentore) {
				$AnzahlVerloren++;
				if($hVer < $rowtemp->Gegentore - $rowtemp->Tore) {
					$hVer = $rowtemp->Gegentore - $rowtemp->Tore;
					$hVerT = $rowtemp->Tore;
					$hVerG = $rowtemp->Gegentore;
				}
			}
			if ($rowtemp->Tore == $rowtemp->Gegentore) {
				$AnzahlUnentschieden++;
			}
		}
		
		$out .= "<tr><td>".$row->Gegner."</td><td>".$AnzahlSpiele."</td><td>".$AnzahlGewonnen."</td><td>".$AnzahlUnentschieden."</td><td>".$AnzahlVerloren."</td><td>".$GesTore.":".$GesGegenTore."</td><td>";
		if ($hSieg!=0)
			{$out .= $hSiegT . ":" . $hSiegG;}
		$out .="</td><td>";
		if ($hVer!=0)
			{$out .= $hVerT . ":" . $hVerG;}
		$out .="</td></tr>";

		$AnzahlSpiele=0;
		$AnzahlGewonnen=0;
		$GesTore=0;$GesGegenTore=0;
		$hSieg=0;$hSiegT=0;$hSiegG=0;
		$AnzahlVerloren=0;
		$hVer=0;$hVerT=0;$hVerG=0;
		$AnzahlUnentschieden=0;
	}

	//$out=$out."\n|}";
	$out .= "</table>";
	return $out;
}

// Create list (prettytable) with number of goals for all players who have scored
// in the selected period and match type.
function getListOfGoalsForAllScorers($filter, $format) {
	$players = getPlayerNamesFromDB();
	$excludeZero = true;
	$excludeNotPlayed = true;
	return getListOfGoalsForPlayers($players, $filter, $excludeZero, $excludeNotPlayed, $format);
}

// Create list of all tournaments a player has played
//   player - player name ['Gesamt' or `false' means ALL]
//   filter - SQL condition to filter stats table
//   format - 'S'=grouped by season 'Y'=grouped by year
function getListOfTournamentsForPlayer($player, $filter, $format='S') {
	if ($player && $player != 'Gesamt') {
		$filter = "`{$player}` != 255 AND {$filter}";
	}

	$dbr = wfGetDB(DB_SLAVE);
	$res = $dbr->select('`stats_games`',
		array("DISTINCT CONCAT(`Turnier`, ' ', YEAR(`Datum`)) AS `Name`",
			' YEAR(`Datum`) AS `Jahr`',
			' `Datum`',
			' `Art`'),
		$filter,
		__METHOD__,
		array('GROUP BY' => '`Name`',
			'ORDER BY' => '`Datum` DESC')
		);
	if (0 == $res->numRows())
	{
		return "Fehler: Keine Turniere gefunden.";
	}

	$out = '';
	$current_section = '';
	foreach($res as $turnier)
	{
		$date = date_create($turnier->Datum);
		$section_id = $turnier->Jahr;
		if ('S' == $format) {
			if ($date->format('m') < 9) {
				// belongs to last year's season
				$section_id--;
			}
		}
		if ($section_id != $current_section) {
			$classes = 'uwr-stats tournament-list';
			if ($current_section) {
				// close previous section
				$out .= '</ul>';
				$classes .= ' collapsed';
			}
			switch($format) {
			case 'S':
				$season = $section_id.'/'.($section_id+1);
				$out .= "<strong>Saison {$season}</strong>";
				break;
			case 'Y':
				$out .= "<strong>{$section_id}</strong>";
				break;
			}
			$out .= "<ul class='{$classes}'>";
			$current_section = $section_id;
		}
		$link = $turnier->Name;
		$linkTitle = '';
		switch ($turnier->Art) {
		case 'BM':
			$link = 'Bayerische Meisterschaft '.$turnier->Jahr;
			if ($turnier->Name != $turnier->Art . ' ' . $turnier->Jahr) {
				$linkTitle = $turnier->Name;
			}
			break;
		case 'LP':
			$link = 'Länderpokal '.$turnier->Jahr;
			if ($turnier->Name != $turnier->Art . ' ' . $turnier->Jahr) {
				$linkTitle = $turnier->Name;
			}
			break;
		case 'DM':
			$link = 'Deutsche Meisterschaft '.$turnier->Jahr;
			if ($turnier->Name != $turnier->Art . ' ' . $turnier->Jahr) {
				$linkTitle = $turnier->Name;
			}
			break;
		case 'CC':
			$link = 'Champions Cup '.$turnier->Jahr;
			if ($turnier->Name != $turnier->Art . ' ' . $turnier->Jahr) {
				$linkTitle = $turnier->Name;
			}
			break;
		case 'BUL':
			$link = '1. Bundesliga Süd '.$turnier->Jahr.'/'.($turnier->Jahr + 1);
			if ($turnier->Name != '1. BL ' . $turnier->Jahr) {
				$linkTitle = $turnier->Name;
			}
			break;
		}
		$out .= '<li>[['.$link.($linkTitle ? '|'.$linkTitle : '').']]</li>';
	}
	if ($current_section) {
		// close last section
		$out .= '</ul>';
	}
	$parser = new Parser();
	$dummyTitle = new Title('');
	$pOptions = new ParserOptions();
	$pOut = $parser->parse($out, $dummyTitle, $pOptions);
	return $pOut->mText;
}

// Get total number of all (A), won (G), draw (U), or lost (V) games
// Params:
//   GUV - [GUV]
//   filter - SQL condition to filter stats table
function getNumGUVA($GUV, $filter) {
	$dbr = wfGetDB(DB_SLAVE);
	if ($GUV{0}=='A')
	{
		$sql = "SELECT COUNT(`ID`) AS 'COUNT' FROM `stats_games` WHERE {$filter}";
	}
	else
	{
		$ops = array('G' => '>', 'U' => '=', 'V' => '<');
		$op = $ops[ $GUV{0} ]; // consider only first char
		$sql = "SELECT COUNT(`ID`) AS 'COUNT' FROM `stats_games` "
				. "WHERE `Tore` {$op} `Gegentore` AND {$filter}";
	}
	$res = $dbr->query($sql);
	if ($res->numRows() < 1) {
		return '-';
	}
	$row = $res->fetchRow();
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
		'saison' => 0,
		);
	$uwr_stats_aArt = array();
	$uwr_stats_fehler = FALSE;
	$output = "";
	
	// parse and check parameters
	$rv = parseParams($input);
	if ($uwr_stats_fehler) {
		return "Fehlerhafte Parameter (1) [{$rv}]";
	}
	// target nicht gesetzt
	if ('' == $uwr_stats_allParams['target'] && "Gesamt" == $uwr_stats_allParams['name']) {
		$uwr_stats_fehler = TRUE;
	}
	if ($uwr_stats_fehler) {
		return $uwr_stats_allParams['name'] . "Fehlerhafte Parameter (2)";
	}

	// ``saison'' gesetzt (setze ``start'' und ``ende'')
	if ($uwr_stats_allParams['saison']) {
		$uwr_stats_allParams['start'] = $uwr_stats_allParams['saison'] . "-09-01";
		$uwr_stats_allParams['ende']  = ($uwr_stats_allParams['saison'] + 1)."-08-31";
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
		switch($uwr_stats_allParams['name']) {
		case 'torschuetzen':
			$output = getListOfGoalsForAllScorers($SuchString,'N');
			break;
		case 'torschuetzenS':
			$output = getListOfGoalsForAllScorers($SuchString,'S');

			// one individual game
			if (isset($uwr_stats_aArt) && count($uwr_stats_aArt) == 1 && is_numeric($uwr_stats_aArt[0]))
			{
				// load game data
				$dbr = wfGetDB(DB_SLAVE);
				$sql = "SELECT * FROM `stats_games` WHERE {$SuchString}";
				$res = $dbr->query($sql);
				$row = $res->fetchRow();
				$res->free();
				$totalGoals = $row['Tore'];//$row[5];

				if (!$totalGoals) {
					$output .= '<i>keine</i>';
				} else {
					$num_rows = count($row) / 2; // both, assoc and numbered array!

					$accountedGoals = 0;
					for ($c = NUM_NONPLAYER_COLS; $c < $num_rows; $c++)
					{
						$g = $row[$c];
						if ($g != 255)
							$accountedGoals += $g;
					}
					$mrxGoals = $totalGoals - $accountedGoals;
					if ($mrxGoals > 0) {
						$mrx = '';
						if ($output) {
							$mrx = ", ";
						}
						$mrx .= 'Mr. X';
						if ($mrxGoals > 1) {
							$mrx .= " {$mrxGoals}";
						}
						$output .= $mrx;
					}
				}
			}
			break;
		case 'torschuetzenG':
			$output = getListOfGoalsForAllScorers($SuchString,'G');
			break;
		case 'mitspieler':
			$output = getListOfGoalsForAllScorers($SuchString,'M');
			break;
		case 'gesamtbilanz':
			$output = getListOfGesamtbilanz($SuchString);  //doofes Englisch
			break;
		default:
			$players = explode(',', $uwr_stats_allParams['name']);
			if (1 == count($players)) {
				// single player stats
				$player = $players[0];
				if ('Turniere' == $uwr_stats_allParams['target']) {
					// if `Art' is not set, default should be NOT IN ('BUL', 'LL', '2BL', 'REL')
					$SuchString = build_filter($DateA->format('Y-m-d'), $DateE->format('Y-m-d'));
					$output = getListOfTournamentsForPlayer($player, $SuchString);
				}
				else
					$output = getNumGoalsForPlayer($player, $SuchString);
			} else {
				// list of players
				$output = getListOfGoalsForPlayers($players, $SuchString);
			}
			break;
		}
	} else { // Gesamt == name
		// whole team stats
		switch ($uwr_stats_allParams['target']) {
		case 'Tore':
		case 'Gegentore':
			$output = getNumGoals($uwr_stats_allParams['target'], $SuchString);
			break;
		case 'Gewonnen':
		case 'Unentschieden':
		case 'Verloren':
		case 'All':
			$output = getNumGUVA($uwr_stats_allParams['target'], $SuchString);
			break;
		case 'SerieG':
			$output = getSeriesWon($SuchString);
			break;
		case 'SerieV':
			$output = getSeriesLost($SuchString);
			break;
		case 'Turniere':
			// if `Art' is not set, default should be NOT IN ('BUL', 'LL', '2BL', 'REL')
			$SuchString = build_filter($DateA->format('Y-m-d'), $DateE->format('Y-m-d'));
			$output = getListOfTournamentsForPlayer(false, $SuchString);
			break;
		}
	}

	//$output = mysql_num_rows($sqlres)." : ".$uwr_stats_allParams['name']." : ".$uwr_stats_allParams['target'];

	// return the output
	return $output;
}

// Mit Statistikwerten Rechnen
function uwr_stats_r ($input) {
	preg_match_all("#\((.*?)\)#s",$input,$matches, PREG_PATTERN_ORDER);
	preg_match_all("#\)(.*?)\(#s",$input,$matches2, PREG_PATTERN_ORDER);

	$i=0;
	$merk=0;
	$outputtemp1=0;
	$outputtemp2=0;
	$output=0;

	foreach ($matches[1] as $key => $p) {
		if ($merk==0)
		{
			if (is_numeric($p))
				{$output = $p + 0;}
			else
			{
				$output = uwr_stats($p);
				$merk=1;
			}
		}
		else
		{
			if (is_numeric($p)) 
				{$outputtemp2 = $p + 0;}
			else
				{$outputtemp2 = uwr_stats($p);}

			switch ($matches2[1][$key-1]) {
			case '-':
				$output = $output - intval($outputtemp2);
				break;
			case '+':
				$output = $output + intval($outputtemp2);
				break;
			case '*':
				$output = $output * intval($outputtemp2);
				break;
			case '/':
				$output = $output / intval($outputtemp2);
				break;
			}
		}
		$i++;
	}

	if ($i==0)
	{
		$output = uwr_stats($input);
	}

	if (is_float($output))
	{
		$output = round($output, 2);
	}

	return $output;
}
