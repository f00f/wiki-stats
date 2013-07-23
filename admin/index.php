<?php
// database wrapper class
require_once "./inc/sql.inc.php";
// load config values
require_once '../config.inc.php';

require_once "./inc/players.inc.php";
require_once "./inc/games.inc.php";

require_once "./inc/tmpl_header.inc.php";

$sql->db_connect();
$fehler = 0;

$SpielerNamen = players_FindAll();

$submit=@$_REQUEST["submit"];
$edit=@$_REQUEST["edit"];

//Editwerte prüfen
if ($edit==1)
{
	if (!@$_POST['ID'] && @$_REQUEST['ID']) {
		$_POST['ID'] = $_REQUEST['ID'];
	}
	echo "<font color='red'>Du bearbeitest das Spiel mit der ID ".$_POST["ID"]."</font> (<a href='./'>abbrechen</a>)";

	if (is_numeric($_POST["ID"]))
	{
		// Spieldaten laden
		$game = games_FindByID($_POST['ID']);
		if (false === $game) {
			print '<div class="error"><b>Fehler:</b> Spiel nicht gefunden. <a href="./">Zurück zur Liste</a></div>';
			die();
		}

		// TODO: in Model speichern, statt in $_POST
		$_POST['Datum']=$game->Datum;
		$_POST['SpielNr']=$game->SpielNr;
		$_POST['Turnier']=$game->Turnier;
		$_POST['Gegner']=$game->Gegner;
		$_POST['Tore']=$game->Tore;
		$_POST['Gegentore']=$game->Gegentore;
		$_POST['Art']=$game->Art;
		$_POST['Spezial']=$game->Spezial;
  
		$active_players = array();
		$active_player_ids = array();
		foreach ($SpielerNamen as $i => $spieler)
		{
			$tore = $game->$spieler;
			$s_esc = players_EscapeName($spieler);
			$_POST[$s_esc] = ((255 == $tore) ? "-" : $tore);
			if (isset($_POST[$s_esc]) && is_numeric($_POST[$s_esc])) {
				$active_player_ids[] = $i;
				$active_players[] = $spieler;
			}
		}
		// remove active players from the list to add them later at its beginning.
		foreach($active_player_ids as $i) {
			unset($SpielerNamen[$i]);
		}
		$SpielerNamen = array_merge($active_players, $SpielerNamen);

		$fehler=1; // setting $fehler=1 will pre-fill the form with the values in $_POST
	}
}
//Editwerte prüfen

if ($submit==1||$submit==2)
{ 
	//Werte überprüfen
	if (! preg_match ("!^(19|20)\d\d[- /.](0[1-9]|1[012])[- /.](0[1-9]|[12][0-9]|3[01])$!", $_POST['Datum']))
	{
		$fehler=1;
		echo 'Ungültiger Wert für "Datum".<br />';
	}
	if (! (is_numeric ( $_POST['SpielNr'] ) AND $_POST['SpielNr'] >= 0 AND $_POST['SpielNr'] <=255))
	{
		$fehler=1;
		echo 'Ungültiger Wert für "SpielNr".<br />';
	}
	if (strlen ( $_POST['Turnier'] )>150 OR strlen ( $_POST['Turnier'] ) == 0)
	{
		$fehler=1;
		echo 'Ungültiger Wert für "Turnier".<br />';
	}
	if (strlen ( $_POST['Gegner'] )>100 OR strlen ( $_POST['Gegner'] ) == 0)
	{
		$fehler=1;
		echo 'Ungültiger Wert für "Gegner".<br />';
	}
	if (! (is_numeric ($_POST['Tore']) AND $_POST['Tore'] >= 0 AND $_POST['Tore'] <=255))
	{
		$fehler=1;
		echo 'Ungültiger Wert für "Tore".<br />';
	}
	if (!(is_numeric ($_POST['Gegentore'])AND $_POST['Gegentore'] >= 0 AND $_POST['Gegentore'] <=255))
	{
		$fehler=1;
		echo 'Ungültiger Wert für "Gegentore".<br />';
	}
	if (strlen ( $_POST['Art'] )>50 OR strlen ( $_POST['Art'] ) == 0)
	{
		$fehler=1;
		echo 'Ungültiger Wert für "Art".<br />';
	}
	if (strlen ( $_POST['Spezial'] )>50)
	{
		$fehler=1;
		echo 'Ungültiger Wert für "Spezial".<br />';
	}

	foreach ($SpielerNamen as $i => $spieler)
	{
		$tore = @$_POST[players_EscapeName($spieler)];
		if (!((is_numeric($tore) AND $tore >= 0 AND $tore <=255 ) OR $tore == '-' OR $tore == ''))
		{
			$fehler=1;
			echo "Ungültiger Wert für {$spieler}<br />";
		}
	}
	//Werte überprüft

	if ($fehler!=1)
	{
		// Ein neues Spiel eintragen 
		if($submit==1)
		{
			$SPNamen="";
			$SPValue="";
			foreach ($SpielerNamen as $i => $spieler)
			{
				$tore = @$_POST[players_EscapeName($spieler)];

				$SPNamen .= ",`{$spieler}`";
				$SPValue .= ',' . (($tore == "" OR $tore == "-") ? '255' : $tore);
			}

			$sql->query("INSERT INTO stats_games (Datum, SpielNr, Turnier, Gegner, Tore, Gegentore, Art, Spezial".$SPNamen.")
					VALUES ('".$_POST['Datum']."',".$_POST['SpielNr'].", '".mysql_real_escape_string($_POST['Turnier'])."', '".mysql_real_escape_string($_POST['Gegner'])."',".$_POST['Tore'].",".$_POST['Gegentore'].", '".mysql_real_escape_string($_POST['Art'])."', '".mysql_real_escape_string($_POST['Spezial'])."'".$SPValue.")");
			echo "Neues Spiel eingetragen.";

			// the values in $_POST will be used to pre-fill the form for the next game.
			// setting $fehler=1 will pre-fill the edit form.
			$fehler = 1;
			// delete, update, or reset values which we're pretty sure that they will be different.
			$_POST['SpielNr'] += 1;
			unset($_POST['Gegner']);
			unset($_POST['Tore']);
			$_POST['Gegentore'] = 0;
			unset($_POST['Spezial']);
			$active_players = array();
			$active_player_ids = array();
			foreach ($SpielerNamen as $i => $spieler)
			{
				$s_esc = players_EscapeName($spieler);
				if (isset($_POST[$s_esc]) && is_numeric($_POST[$s_esc])) {
					$active_player_ids[] = $i;
					$active_players[] = $spieler;
					$_POST[$s_esc] = 0;
				}
			}
			// remove active players from the list to add them later at its beginning.
			foreach($active_player_ids as $i) {
				unset($SpielerNamen[$i]);
			}
			$SpielerNamen = array_merge($active_players, $SpielerNamen);
		}

		// Ein Spiel ändern 
		if($submit==2 && is_numeric($_POST["ID"]))
		{
			$SPupdate="";
			foreach ($SpielerNamen as $i => $spieler)
			{
				$tore = @$_POST[players_EscapeName($spieler)];
				$SPupdate .= ",`{$spieler}`='"
							. (($tore == "" OR $tore == "-") ? "255" : $tore)
							. "'";
			}
			$q = "UPDATE stats_games
			SET Datum='".$_POST['Datum']."', 
			SpielNr='".$_POST['SpielNr']."', 
			Turnier='".$_POST['Turnier']."',
			Gegner='".$_POST['Gegner']."', 
			Tore='".$_POST['Tore']."', 
			Gegentore='".$_POST['Gegentore']."', 
			Art='".$_POST['Art']."', 
			Spezial='".$_POST['Spezial']."'".$SPupdate."
			WHERE ID=".$_POST["ID"];
			$sql->query($q);
   
			echo "Das Spiel mit der ID {$_POST['ID']} wurde geändert.";
		}
	}
}
?>
<h4>Spiel eintragen / bearbeiten</h4>
<div class="box">
<form action="index.php?submit=<?php print ($edit==1) ? "2" : "1"; ?>" method="post"> 
<table>
<tr>
<td>Datum</td><td><input name="Datum" type="text" size="10" maxlength="20" <?php if ($fehler==1) { print "value='{$_POST['Datum']}'"; };?> /></td>
<td>Turnier</td><td><input name="Turnier" type="text" size="30" maxlength="150" <?php if ($fehler==1) { print "value='{$_POST['Turnier']}'"; };?> /></td>
<td>Art</td>
<td><input name="Art" type="text" size="10" maxlength="50" <?php if ($fehler==1) { print "value='{$_POST['Art']}'"; };?> /></td>
<td colspan="4"></td>
</tr>
<tr>
<td>SpielNr</td>
<td><input name="SpielNr" type="text" size="5" maxlength="4" <?php if ($fehler==1) { print "value='{$_POST['SpielNr']}'"; };?> /></td>
<td>Gegner</td>
<td><input name="Gegner" type="text" size="30" maxlength="100" <?php if ($fehler==1 && isset($_POST['Gegner'])) { print "value='{$_POST['Gegner']}'"; };?> /></td>
<td>Tore</td>
<td><input name="Tore" type="text" size="6" maxlength="3" <?php if ($fehler==1 && isset($_POST['Tore'])) { print "value='{$_POST['Tore']}'"; };?> /></td>
<td>Gegentore</td>
<td><input name="Gegentore" type="text" size="6" maxlength="3" <?php if ($fehler==1 && isset($_POST['Gegentore'])) { print "value='{$_POST['Gegentore']}'"; };?> /></td>
<td>Spezial</td>
<td><input name="Spezial" type="text" size="10" maxlength="50" <?php if ($fehler==1 && isset($_POST['Spezial'])) { print "value='{$_POST['Spezial']}'"; };?> /></td>
</tr>
<tr>
<td colspan="10">
<small>
Hinweise (bei <abbr title="Abkürzungen">Abk.</abbr> Mouse-Over):
<u>Datum</u>: Format=yyyy-mm-dd;
<u>Turnier</u>: Turniername, Freitext;
<u>Art</u>: Wettkampftyp=[<abbr title="Landesliga">LL</abbr>|<abbr title="2. Bundesliga">2BL</abbr>|<abbr title="1. Bundesliga">BUL</abbr>|<abbr title="Bay. Meisterschaft">BM</abbr>|<abbr title="Dt. Meisterschaft">DM</abbr>|<abbr title="Champions Cup">CC</abbr>|<abbr title="Relegation">REL</abbr>|<abbr title="BOT / adh">BOT</abbr>|<abbr title="Jugend(-DM?)">JUG</abbr>|<abbr title="Junioren(-DM?)">JUN</abbr>|<abbr title="Freies Turnier">FT</abbr>];
<u>SpielNr</u>: zur Sortierung;
<u>Spezial</u>=[<abbr title="Overtime">OT</abbr>|<abbr title="Penalties">PEN</abbr>|<abbr title="Frozen Result">FR</abbr>].
</small>
</td>
</tr>
</table>
<hr style="color:#d3d3d3;" />
<table>
<tr>
<?php
$spieler_pro_zeile = 7;
foreach ($SpielerNamen as $i => $spieler)
{
	$value = '';
	if ($fehler==1)
	{
		$spieler_escaped = players_EscapeName($spieler);
		$value=" value='{$_POST[$spieler_escaped]}'";
	}
	echo "<td><label for='tore-{$spieler}'>{$spieler}:</label></td>"
		. "<td><input type='text' id='tore-{$spieler}' name='{$spieler}' size='5' maxlength='3'{$value} /></td>";

	if (($i+1) % $spieler_pro_zeile == 0)
	{
		echo "</tr><tr>";
	}
}
?>
</tr>
</table>
<input type="submit" value=" Absenden " />
<input type="button" value=" Abbrechen " onClick="window.location.href='index.php'" />
<input type="hidden" name="ID" value="<?php print @$_POST["ID"]; ?>" />
</form>
</div>

<?php
if ($edit!=1):
// liste aller spiele
?>

<h4>Letzte 10 Spiele</h4>
<?php
$games = games_FindLatest(10);
require_once './inc/tmpl_games_list.inc.php';
$sql->close();
?>

<h4>Weitere Funktionen</h4>
<!-- Add player form -->
<div class="fleft box">
<form action="./players_add.php" method="post" name="AddSP" onsubmit='return confirm("Neuen Spieler \""+AddName.value+"\" hinzufügen?")'>
<input type="hidden" name="Add" value="1" />
Spieler hinzufügen:<br />
<input name="AddName" type="text" size="10" maxlength="50">
<input type="submit" value="hinzufügen" />
</form>
</div>
<!-- /Add player form -->

<!-- Link zu Liste aller Spiele -->
<div class="fleft box">
<a href="./games_list.php">Liste aller Spiele</a><br />
<a href="./tournaments_list.php">Liste aller Turniere</a>
</div>
<!-- /Link zu Liste aller Spiele -->
<br style="clear:both;" />

<?php
endif; // $edit != 1
?>
</body>
</html>
