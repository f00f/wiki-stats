<?php
// database wrapper class
require_once "./sql.inc.php";
// load config values
require_once '../config.inc.php';

require_once "./tournaments.inc.php";

require_once "./tmpl_header.inc.php";

$groupYears = false;
if (@$_GET['groupYears']) {
	$groupYears = $_GET['groupYears'];
}
?>
<a class="tab" href="./">Zurück zur Startseite</a>
<h4>Alle Turniere</h4>
<div class="box">
<strong>Optionen</strong>
<form action="./tournaments_list.php" method="get">
<input type="checkbox" name="groupYears" id="groupYears"<?php if ($groupYears) { print ' checked="checked"'; } ?> /> <label for="groupYears">Turniere mit gleichem Namen zusammenfassen</label><br />
<input type="submit" value="OK" />
</form>
</div>
<?php
$sql->db_connect();
$tournaments = tournaments_FindAll($groupYears);
$sql->close();

if (0 == count($tournaments)) {
	print 'Keine Turniere gefunden.';
} else {
	print '<ul>';
	foreach ($tournaments as $t) {
		print "<li>{$t->Turnier}</li>";
	}
	print '</ul>';
}
?>

</body>
</html>
