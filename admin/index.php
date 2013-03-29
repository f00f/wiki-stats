<?php
require_once "./sql.inc.php";
$sql->db_connect();
$fehler = 0;
define('NUM_NONPLAYER_COLS', 9); //< number of columns at the beginning which are not player scores

// Spielernamen aus der Datenbank laden.
// Für jeden Spieler gibt es eine Spalte, die ersten N Spalten beschreiben das Spiel.
$res = $sql->query('DESCRIBE stats_games');
mysql_data_seek($res, NUM_NONPLAYER_COLS);
$SpielerNamen = array();
while($row = mysql_fetch_array($res))
{
	array_push($SpielerNamen,$row['Field']);
}

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
		$sqlres = $sql->query("SELECT * FROM stats_games WHERE ID=".$_POST["ID"]);
		if (mysql_num_rows($sqlres) != 1) {
			print '<div class="error"><b>Fehler:</b> Spiel nicht gefunden. <a href="./">Zurück zur Liste</a></div>';
			die();
		}
		$sqlobj = mysql_fetch_object($sqlres);

		// TODO: in Model speichern, statt in $_POST
		$_POST['Datum']=$sqlobj->Datum;
		$_POST['SpielNr']=$sqlobj->SpielNr;
		$_POST['Turnier']=$sqlobj->Turnier;
		$_POST['Gegner']=$sqlobj->Gegner;
		$_POST['Tore']=$sqlobj->Tore;
		$_POST['Gegentore']=$sqlobj->Gegentore;
		$_POST['Art']=$sqlobj->Art;
		$_POST['Spezial']=$sqlobj->Spezial;
  
		foreach ($SpielerNamen as $i => $spieler)
		{
			$tore = $sqlobj->$spieler;
			$_POST[$spieler] = (255 == $tore) ? "-" : $tore;
		}

		$fehler=1;
	}
}
//Editwerte prüfen

//Werte überprüfen
if ($submit==1||$submit==2)
{ 
	if (! preg_match ("!^(19|20)\d\d[- /.](0[1-9]|1[012])[- /.](0[1-9]|[12][0-9]|3[01])$!", $_POST['Datum']))
	{
		$fehler=1;
		echo("Datum falsch<br />");
	}
	if (! (is_numeric ( $_POST['SpielNr'] ) AND $_POST['SpielNr'] >= 0 AND $_POST['SpielNr'] <=255))
	{
		$fehler=1;
		echo("SpielNr falsch<br />");
	}
	if (strlen ( $_POST['Turnier'] )>150 OR strlen ( $_POST['Turnier'] ) == 0)
	{
		$fehler=1;
		echo("Turnier falsch<br />");
	}
	if (strlen ( $_POST['Gegner'] )>100 OR strlen ( $_POST['Gegner'] ) == 0)
	{
		$fehler=1;
		echo("Gegner falsch<br />");
	}
	if (! (is_numeric ($_POST['Tore']) AND $_POST['Tore'] >= 0 AND $_POST['Tore'] <=255))
	{
		$fehler=1;
		echo("Tore falsch<br />");
	}
	if (!(is_numeric ($_POST['Gegentore'])AND $_POST['Gegentore'] >= 0 AND $_POST['Gegentore'] <=255))
	{
		$fehler=1;
		echo("Gegentore falsch<br />");
	}
	if (strlen ( $_POST['Art'] )>50 OR strlen ( $_POST['Art'] ) == 0)
	{
		$fehler=1;
		echo("Art falsch<br />");
	}
	if (strlen ( $_POST['Spezial'] )>50)
	{
		$fehler=1;
		echo("Spezial falsch<br />");
	}

	foreach ($SpielerNamen as $i => $spieler)
	{
		$tore = @$_POST[$spieler];
		if (!((is_numeric($tore) AND $tore >= 0 AND $tore <=255 ) OR $tore == '-' OR $tore == ''))
		{
			$fehler=1;
			echo $spieler." falsch<br />";
		}
	}
//Werte überprüft

	if ($fehler!=1)
	{
		$SPNamen="";
		$SPValue="";
		foreach ($SpielerNamen as $i => $spieler)
		{
			$tore = @$_POST[$spieler];

			$SPNamen .= ",`{$spieler}`";
			$SPValue .= ',' . ($tore == "" OR $tore == "-") ? '255' : $tore;
		}

// Werte eintragen 
		if($submit==1)
		{
			$sql->query("INSERT INTO stats_games (Datum, SpielNr, Turnier, Gegner, Tore, Gegentore, Art, Spezial".$SPNamen.")
					VALUES ('".$_POST['Datum']."',".$_POST['SpielNr'].", '".mysql_real_escape_string($_POST['Turnier'])."', '".mysql_real_escape_string($_POST['Gegner'])."',".$_POST['Tore'].",".$_POST['Gegentore'].", '".mysql_real_escape_string($_POST['Art'])."', '".mysql_real_escape_string($_POST['Spezial'])."'".$SPValue.")");
			echo ("Neuen Eintrag angelegt");
		}

// Werte ändern 
		if($submit==2 && is_numeric($_POST["ID"]))
		{
			$SPupdate="";
			foreach ($SpielerNamen as $i => $spieler)
			{
				$tore = $_POST[str_replace(" ","_",$spieler)];
				$SPupdate .= ",`{$spieler}`='"
							. ($tore == "" OR $tore == "-") ? "255" : $tore
							. "'";
			}

			$sql->query("UPDATE stats_games
			SET Datum='".$_POST['Datum']."', 
			SpielNr='".$_POST['SpielNr']."', 
			Turnier='".$_POST['Turnier']."',
			Gegner='".$_POST['Gegner']."', 
			Tore='".$_POST['Tore']."', 
			Gegentore='".$_POST['Gegentore']."', 
			Art='".$_POST['Art']."', 
			Spezial='".$_POST['Spezial']."'".$SPupdate."
			WHERE ID=".$_POST["ID"]);
   
			echo "Das Spiel mit der ID {$_POST['ID']} wurde geändert.";
		}
	}
}
?>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<meta http-equiv="cache-control" content="no-cache">
<style>
.fleft { float:left; }
.box {
	border: 2px solid #d3d3d3;
	border-radius:3px;
	padding:3px;
	margin-right:10px;
	}
</style>
</head>
<html>

<div class="box">
<form action="index.php?submit=<?php print ($edit==1) ? "2" : "1"; ?>" method="post"> 
<table>
<tr>
<td>Datum</td><td><input name="Datum" type="text" size="10" maxlength="20" <?php print ($fehler==1) ? "value='{$_POST['Datum']}'" :"";?> /></td>
<td>Turnier</td><td><input name="Turnier" type="text" size="30" maxlength="150" <?php print ($fehler==1) ? "value='{$_POST['Turnier']}'" :"";?> /></td>
<td>Art</td>
<td><input name="Art" type="text" size="10" maxlength="50" <?php print ($fehler==1) ? "value='{$_POST['Art']}'" :"";?> /></td>
<td colspan="4"></td>
</tr>
<tr>
<td>SpielNr</td><td><input name="SpielNr" type="text" size="5" maxlength="4" <?php print ($fehler==1) ? "value='{$_POST['SpielNr']}'" :"";?> /></td>
<td>Gegner</td><td><input name="Gegner" type="text" size="30" maxlength="100" <?php print ($fehler==1) ? "value='{$_POST['Gegner']}'" :"";?> /></td>
<td>Tore</td>
<td><input name="Tore" type="text" size="6" maxlength="3" <?php print ($fehler==1) ? "value='{$_POST['Tore']}'" :"";?> /></td>
<td>Gegentore</td>
<td><input name="Gegentore" type="text" size="6" maxlength="3" <?php print ($fehler==1) ? "value='{$_POST['Gegentore']}'" :"";?> /></td>
<td>Spezial</td>
<td><input name="Spezial" type="text" size="10" maxlength="50" <?php print ($fehler==1) ? "value='{$_POST['Spezial']}'" :"";?> /></td>
</tr>
<tr>
<td colspan="10">
<small>
Hinweise (bei <abbr title="Abkürzungen">Abk.</abbr> Mouse-Over):
<u>Datum</u>: Format=yyyy-mm-dd;
<u>Turnier</u>: Freitext;
<u>Art</u>=[<abbr title="Landesliga">LL</abbr>|<abbr title="2. Bundesliga">2BL</abbr>|<abbr title="1. Bundesliga">BUL</abbr>|<abbr title="Bay. Meisterschaft">BM</abbr>|<abbr title="Dt. Meisterschaft">DM</abbr>|<abbr title="Champions Cup">CC</abbr>|<abbr title="Relegation">REL</abbr>|<abbr title="BOT / adh">BOT</abbr>|<abbr title="Jugend(-DM?)">JUG</abbr>|<abbr title="Junioren(-DM?)">JUN</abbr>|<abbr title="Freies Turnier">FT</abbr>];
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
		$value=" value='{$_POST[$spieler]}'";
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

<br /><br />

<!-- Edit form -->
<div class="fleft box">
<form action="./" method="get"> 
<input type="hidden" name="edit" value="1" />
Spiel-ID:<br />
<input name="ID" type="text" size="10" maxlength="4" /><br />
<input type="submit" value=" editieren " />
</form>
</div>
<!-- /Edit form -->

<!-- Add player form -->
<div class="fleft box">
<form action="./add_player.php" method="post" name="AddSP" onsubmit='return confirm("Neuen Spieler \""+AddName.value+"\" hinzufügen?")'>
<input type="hidden" name="Add" value="1" />
Spieler hinzufügen:<br />
<input name="AddName" type="text" size="10" maxlength="50">
<input type="submit" value="hinzufügen" />
</form>
</div>
<!-- /Add player form -->

<br style="clear:both;" />
<br /><br />


<table width=2000 border=1>
<?php
// Daten aller Spiele aus der Datenbank laden
$sqlres = $sql->query("SELECT * FROM stats_games ORDER BY Datum DESC, SpielNr DESC");

$Ueberschrift=40;
while ($match = mysql_fetch_object($sqlres))
{
	$Ueberschrift++;
	if ($Ueberschrift>=40)
	{
		echo("<tr>");
		echo("<th>ID</th><th width=100>Datum</th><th>Turnier</th><th>Gegner</th><th>Tore</th><th>Gegentore</th><th>Art</th><th width=150>Spezial</th>");
		foreach ($SpielerNamen as $i => $spieler)
		{
			echo "<th>".$spieler."</th>";
		}

		echo("</tr>");
		$Ueberschrift=0;
	}

	$Tore = array();
	foreach ($SpielerNamen as $i => $spieler)
	{
		array_push($Tore, $match->$spieler);
	}

	echo("<tr>");
	echo("<td><a href='./?edit=1&ID={$match->ID}'>{$match->ID}</a></td><td>{$match->Datum}</td><td>{$match->Turnier}</td><td>{$match->Gegner}</td><td>{$match->Tore}</td><td>{$match->Gegentore}</td><td>{$match->Art}</td><td>{$match->Spezial}</td>");
	foreach ($Tore as $i => $tore)
	{
		if ($tore==255)
		{
			$tore="-";
		}
		echo "<td>".$tore."</td>";
	}
	echo "<tr>";
}
@$sql->close();
?>
</table>
<?php
endif; // $edit != 1
?>
</html>
