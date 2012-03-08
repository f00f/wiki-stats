<?php
/** Statistik-Erweiterung fuer Mediawiki.
*
* Entwickelt von le Nique dangereux
*/

# to activate the extension, include it from your LocalSettings.php
# with: include("extensions/YourExtensionName.php");

$wgExtensionFunctions[] = "wfStats";
function wfStats() {
	global $wgParser;
	$wgParser->setHook( "stats", "stats" );
}

// the callback function for converting the input text to HTML output
function stats($input) {
	// set default arguments
	$fehler = 0;
	$output = "";
	$allParams['start'] = "";
	$allParams['ende'] = "";
	$allParams['name'] = "Gesamt";
	$allParams['target'] = "";


	// get input args
	$aParams = explode("\n", $input); // ie 'website=http://www.whatever.com'


	foreach($aParams as $sParam) {
		$aParam = explode("=", $sParam, 2); // ie $aParam[0] = 'website' and $aParam[1] = 'http://www.whatever.com'
		if( count( $aParam ) < 2 ) // no arguments passed
		continue;

		$sType = trim(strtolower($aParam[0])); // ie 'website'
		$sArg = trim($aParam[1]); // ie 'http://www.whatever.com'

		switch ($sType) {
		case 'start':
		case 'ende':
			// clean up
			if (FALSE !== date_create($sArg)) {
				$allParams[$sType] = $sArg; // 2012
			}
			break;
		case 'name':
			if ($sArg != "Gesamt")
			{
				// see if column for player exists
				$res = mysql_query('DESCRIBE `stats_games`');
				$fehler = 1;
				mysql_data_seek($res, 9);
				while($row = mysql_fetch_array($res)) 
				{
					if ($sArg == $row['Field']) { // Nik
						$allParams[$sType] = $sArg;
						$fehler = 0;
					}
				}    
			}
			break;
		case 'target':
			$allowedTarget = array('Tore', 'Gegentore',
								'Gewonnen', 'Verloren', 'Unentschieden',
								'SerieG', 'SerieV');
			if (in_array($sArg, $allowedTarget)) {
				$allParams[$sType] = $sArg; // Tore
			} else {
				$fehler = 1;
			}
			break;
		case 'art':
			$allowedArt = array('BUL', 'CC', 'DM');
			$Art = explode("+", $sArg);
			foreach($Art as $ArtPruef) {
				if (! in_array($ArtPruef, $allowedArt)) {
					$fehler = 1;
					break;
				}
			}
			break;

		}
		if (1 == $fehler) {
			print 'Fehler in den Parametern.';
		}
	}

	// Startwert nicht gesetzt (auf unendlich setzen)
	if ($allParams['start']=="") {
		$allParams['start']="1900-01-01";
		$allParams['ende']="2200-01-01";
	}

	// target nicht gesetzt
	if ($allParams['target']=="" && $allParams['name']=="Gesamt") {
		$fehler = 1;
	}

	// build output
	if ($fehler==0)
	{

		// SaisonE nicht eintegragen (auf Startwert +1 Jahr setzen)
		if ($allParams['ende']=="") {
			$allParams['ende'] = $DateA->format('d.m') . "." . ($DateA->format('Y')+1);
		}
		$DateA=date_create($allParams['start']);
		$DateE=date_create($allParams['ende']);

		// SuchString zusammenstellen
		$SuchString="(`Datum` BETWEEN '".$DateA->format('Y-m-d')."' AND '".$DateE->format('Y-m-d')."')";

		$Merker=0;
		if (isset($Art))
		{
			foreach($Art as $ArtPruef) 
			{
				if ($Merker==0)
				{$SuchString=$SuchString." AND (`Art`='".$ArtPruef."'";$Merker=1;}
				else
				{$SuchString=$SuchString." OR `Art`='".$ArtPruef."'";}
			}
			if ($Merker==1)
			{$SuchString=$SuchString.") ";}
		}


		// Name = Gesamt
		if ($allParams['name']=="Gesamt")
		{
			switch ($allParams['target']) 
			{
			case 'Tore':
				$sqlres = mysql_query("SELECT * FROM `stats_games` WHERE ".$SuchString);
				While ($sqlobj =  mysql_fetch_object($sqlres))
				{$output=$output+$sqlobj->Tore;}
				break;
			case 'Gegentore':
				$sqlres = mysql_query("SELECT * FROM `stats_games` WHERE ".$SuchString);
				While ($sqlobj =  mysql_fetch_object($sqlres))
				{$output=$output+$sqlobj->Gegentore;}
				break;
			case 'Gewonnen':
				$sqlres = mysql_query("SELECT * FROM `stats_games` WHERE `Tore` > `Gegentore` AND ".$SuchString);
				$output=mysql_num_rows($sqlres);
				break;
			case 'Verloren':
				$sqlres = mysql_query("SELECT * FROM `stats_games` WHERE `Tore` < `Gegentore` AND ".$SuchString);
				$output=mysql_num_rows($sqlres);
				break;
			case 'Unentschieden':
				$sqlres = mysql_query("SELECT * FROM `stats_games` WHERE `Tore` = `Gegentore` AND ".$SuchString);
				$output=mysql_num_rows($sqlres);
				break;
			case 'SerieG':
				$sqlres = mysql_query("SELECT * FROM `stats_games` WHERE ".$SuchString." ORDER BY `Datum` DESC, `SpielNr` DESC");
				$AktuelleSiegSerie=0;
				$LaengsteSiegSerie=0;
				$LaengsteSiegSerieMerker=0;
				While ($sqlobj =  mysql_fetch_object($sqlres))
				{
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
				$output = $LaengsteSiegSerie;
				break;
			case 'SerieV':
				$sqlres = mysql_query("SELECT * FROM `stats_games` WHERE ".$SuchString." ORDER BY `Datum` DESC, `SpielNr` DESC");
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
				$output = $LaengsteNiederlageSerie;
				break;
			}
		}
		// Name != Gesamt
		else
		{
			$sqlres = mysql_query("SELECT * FROM `stats_games` WHERE ".$SuchString);
			$Merker = 0;
			while ($sqlobj =  mysql_fetch_object($sqlres)) {
				if ($sqlobj->$allParams['name']!=255)
				{$output=$output+$sqlobj->$allParams['name'];$Merker=1;}
			}
			if ($Merker==0) {
				$output = "-";
			}
		}



		//$output = mysql_num_rows($sqlres)." : ".$allParams['name']." : ".$allParams['target'];
	}
	else {
		$output = "Fehlerhafte Parameter";
	}

	// return the output
	return $output;
}
?>