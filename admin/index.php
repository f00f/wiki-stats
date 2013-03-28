<?php
// move to /admin

include("../sql.inc.php");
$sql->db_connect();

//SpielerHinzufügen
if ($_GET['Add']=="1")
{
	$sql->query("ALTER TABLE `stats_games` ADD `".$_GET['AddName']."` TINYINT UNSIGNED NOT NULL DEFAULT '255'");
	sleep (2);
	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");  
	header('Location: index.php');
}
//SpielerHinzufügen


//$SpielerNamen = array("Nussi","Andi","Moritz","Luk","Felix","Nik","Hannes","Bela","Geza","Markus","Ardan","Veit","Lieven","Seb","Klemi","Jan","Mary","Flo","Oli","Olaf","Chrisi","Benni","Märtl","Manni","Michi","Sascha","Julia","Wölfels","Wenzel","Ariane","Lutscher","MSchottmüller","klFelix","Peter","MarkusL");

$res = $sql->query('DESCRIBE stats_games');
mysql_data_seek($res,9);
$SpielerNamen = array();
while($row = mysql_fetch_array($res)) 
{
	array_push($SpielerNamen,$row['Field']);
}

$submit=$_GET["submit"];
$edit=$_GET["edit"];
//Editwerte prüfen
if ($edit==1)
{
	echo "<font color=red>editieren des Eintrages: ".$_POST["ID"]."</font>";
	if (is_numeric($_POST["ID"]))
	{
		$sqlres = $sql->query("SELECT * FROM stats_games WHERE ID=".$_POST["ID"]);
		$sqlobj = mysql_fetch_object($sqlres);

		$_POST['Datum']=$sqlobj->Datum;
		$_POST['SpielNr']=$sqlobj->SpielNr;
		$_POST['Turnier']=$sqlobj->Turnier;
		$_POST['Gegner']=$sqlobj->Gegner;
		$_POST['Tore']=$sqlobj->Tore;
		$_POST['Gegentore']=$sqlobj->Gegentore;
		$_POST['Art']=$sqlobj->Art;
		$_POST['Spezial']=$sqlobj->Spezial;
  
		foreach ($SpielerNamen as $i => $value) 
		{
			if ($sqlobj->$SpielerNamen[$i]!=255)
			{
				$_POST[$SpielerNamen[$i]]=$sqlobj->$SpielerNamen[$i];
			}
			else
			{
				$_POST[$SpielerNamen[$i]]="-";
			}
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

	foreach ($SpielerNamen as $i => $value) 
	{
		if (!((is_numeric ($_POST[$SpielerNamen[$i]]) AND $_POST[$SpielerNamen[$i]] >= 0 AND $_POST[$SpielerNamen[$i]] <=255 ) OR $_POST[$SpielerNamen[$i]]=='-' OR $_POST[$SpielerNamen[$i]]==''))
		{
			$fehler=1;
			echo $SpielerNamen[$i]." falsch<br />";
		}
	}
//Werte überprüft

	if ($fehler!=1)
	{
		$SPNamen="";
		$SPValue=""; 
		foreach ($SpielerNamen as $i => $value) 
		{
			$SPNamen=$SPNamen." ,`".$SpielerNamen[$i]."`";

			if ($_POST[$SpielerNamen[$i]] == "" OR $_POST[$SpielerNamen[$i]] == "-")
			{$SPValue=$SPValue.",255";}
			else
			{$SPValue=$SPValue.",".$_POST[$SpielerNamen[$i]];}
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
			foreach ($SpielerNamen as $i => $value) 
			{
				if ($_POST[str_replace(" ","_",$SpielerNamen[$i])] == "" OR $_POST[str_replace(" ","_",$SpielerNamen[$i])] == "-")
				{$SPupdate=$SPupdate.",`".$SpielerNamen[$i]."`='255'";}
				else
				{$SPupdate=$SPupdate.",`".$SpielerNamen[$i]."`='".$_POST[str_replace(" ","_",$SpielerNamen[$i])]."'";}
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
   
			echo ("Eintrag geändert ID:".$_POST["ID"]);
		}
	}
}
?>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<meta http-equiv="cache-control" content="no-cache">
</head>
<html>

<form action="index.php?submit=<?php print ($edit==1) ? "2" : "1"; ?>" method="post"> 
<table border=1 width=1100><tr><td>

<table><tr>
<td>Datum</td><td>SpielNr</td><td>Turnier</td><td>Gegner</td><td>Tore</td><td>Gegentore</td><td>Art</td><td>Spezial</td>
</tr>
<tr>
<td><input name="Datum" type="text" size="10" maxlength="20" <?php print ($fehler==1) ? "value=\"".$_POST[Datum]."\"" :"";?>></td>
<td><input name="SpielNr" type="text" size="5" maxlength="3" <?php print ($fehler==1) ? "value=\"".$_POST[SpielNr]."\"" :"";?>></td>
<td><input name="Turnier" type="text" size="30" maxlength="150" <?php print ($fehler==1) ? "value=\"".$_POST[Turnier]."\"" :"";?>></td>
<td><input name="Gegner" type="text" size="30" maxlength="100" <?php print ($fehler==1) ? "value=\"".$_POST[Gegner]."\"" :"";?>></td>
<td><input name="Tore" type="text" size="6" maxlength="3" <?php print ($fehler==1) ? "value=\"".$_POST[Tore]."\"" :"";?>></td>
<td><input name="Gegentore" type="text" size="6" maxlength="3" <?php print ($fehler==1) ? "value=\"".$_POST[Gegentore]."\"" :"";?>></td>
<td><input name="Art" type="text" size="10" maxlength="50" <?php print ($fehler==1) ? "value=\"".$_POST[Art]."\"" :"";?>></td>
<td><input name="Spezial" type="text" size="10" maxlength="50" <?php print ($fehler==1) ? "value=\"".$_POST[Spezial]."\"" :"";?>></td>
</tr>
</table>

<table>
<tr>
<?php
  foreach ($SpielerNamen as $i => $value) 
   {
    if ($fehler==1)
    {$value="value=\"".$_POST[$SpielerNamen[$i]]."\"";}
    echo ("<td>".$SpielerNamen[$i]."</td><td><input name=\"".$SpielerNamen[$i]."\" type=\"text\" size=\"5\" maxlength=\"3\" ".$value."></></td>");
    if (($i+1)%9==0)
     {echo ("</tr><tr>");}
   }
   
   ?>


</table>
<input type="submit" value=" Absenden "> <input type="button" value=" Abbrechen " onClick="window.location.href='index.php'">
<input name="ID" type="text" size="10" maxlength="50" <?php print "value=\"".$_POST["ID"]."\""?> style="visibility:hidden">


</td></tr></table>
</form>
<br /><br />

<?php //Edit?>
<table><tr><td>
<form action="index.php?edit=1" method="post"> 
<table border=1 width=100><tr><td>

ID: <input name="ID" type="text" size="10" maxlength="50"><br />
<input type="submit" value=" editieren ">
</td></tr></table>
</form>
<?php //Edit?>

</td><td>

<?php //Add?>
<script language="JavaScript">
<!--
function add(AddName) {
if (confirm("Neuen Spieler \""+AddName+"\" hinzufügen?"))
{window.location.href="index.php?Add=1&AddName="+AddName;}
else 
{}
}
// -->
</script>


<form action="" method="post" name="AddSP"> 
<table border=1 width=130><tr><td>

Spieler hinzufügen: <input name="AddName" type="text" size="10" maxlength="50"><br />
<input type="button" value=" hinzufügen " onclick="add(document.AddSP.AddName.value)">
<font size=1>anschließend Reload drücken!</font>
</td></tr></table>
</form>
<?php //Add?>
</td></tr></table>

<br /><br /><br /><br /><br />


<table width=2000 border=1>
<?php
$sqlres = $sql->query("SELECT * FROM stats_games ORDER BY Datum DESC, SpielNr DESC");

$Ueberschrift=40;
while ($sqlobj = mysql_fetch_object($sqlres))
{
	$Ueberschrift++;
	if ($Ueberschrift>=40)
	{
		echo("<tr>");
		echo("<td>ID</td><td width=100>Datum</td><td>Turnier</td><td>Gegner</td><td>Tore</td><td>Gegentore</td><td>Art</td><td width=150>Spezial</td>");
		foreach ($SpielerNamen as $i => $value) 
		{
			echo ("<td>".$SpielerNamen[$i]."</td>");
		}

		echo("</tr>");
		$Ueberschrift=0;
	}

 //$Tore = array($sqlobj->Nussi,$sqlobj->Andi,$sqlobj->Moritz,$sqlobj->Luk,$sqlobj->Felix,$sqlobj->Nik,$sqlobj->Hannes,$sqlobj->Bela,$sqlobj->Geza,$sqlobj->Markus,$sqlobj->Ardan,$sqlobj->Veit,$sqlobj->Lieven,$sqlobj->Seb,$sqlobj->Klemi,$sqlobj->Jan,$sqlobj->Mary,$sqlobj->Flo,$sqlobj->Oli,$sqlobj->Olaf,$sqlobj->Chrisi,$sqlobj->Benni,$sqlobj->Märtl,$sqlobj->Manni,$sqlobj->Michi,$sqlobj->Sascha,$sqlobj->Julia,$sqlobj->Wölfels,$sqlobj->Wenzel,$sqlobj->Ariane,$sqlobj->Lutscher,$sqlobj->MSchottmüller,$sqlobj->klFelix,$sqlobj->Peter,$sqlobj->MarkusL);

	$Tore = array();
	foreach ($SpielerNamen as $i => $value) 
	{
		array_push($Tore,$sqlobj->$SpielerNamen[$i]);
	}

	echo("<tr>");
	echo("<td>".$sqlobj->ID."</td><td>".$sqlobj->Datum."</td><td>".$sqlobj->Turnier."</td><td>".$sqlobj->Gegner."</td><td>".$sqlobj->Tore."</td><td>".$sqlobj->Gegentore."</td><td>".$sqlobj->Art."</td><td>".$sqlobj->Spezial."</td>");
	foreach ($Tore as $i => $value) 
	{
		if ($Tore[$i]==255)
		{$Tore[$i]="-";}
		echo("<td>".$Tore[$i]."</td>");   
	}
	echo("<tr>");   
}
@$sql->close();
?>
</table>
</html>
