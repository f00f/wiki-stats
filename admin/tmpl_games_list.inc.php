<?php
// template which displays a table with all games it finds in $games
// @uses $games
// @uses $SpielerNamen
?>
<table width=2000 border=1>
<?php
$Ueberschrift=40;
foreach ($games as $game)
{
	$Ueberschrift++;
	if ($Ueberschrift>=40)
	{
		echo "<tr>";
		echo "<th>ID</th><th width=100>Datum</th><th>Turnier</th><th>Gegner</th><th>Tore</th><th>Gegentore</th><th>Art</th><th width=150>Spezial</th>";
		foreach ($SpielerNamen as $i => $spieler)
		{
			echo "<th>".$spieler."</th>";
		}

		echo "</tr>";
		$Ueberschrift=0;
	}

	$Tore = array();
	foreach ($SpielerNamen as $i => $spieler)
	{
		array_push($Tore, $game->$spieler);
	}

	echo "<tr>";
	echo "<td><a href='./?edit=1&ID={$game->ID}'>{$game->ID}</a></td><td>{$game->Datum}</td><td>{$game->Turnier}</td><td>{$game->Gegner}</td><td>{$game->Tore}</td><td>{$game->Gegentore}</td><td>{$game->Art}</td><td>{$game->Spezial}</td>";
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
?>
</table>
