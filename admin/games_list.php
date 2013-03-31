<?php
// database wrapper class
require_once "./sql.inc.php";
// load config values
require_once '../config.inc.php';

require_once "./players.inc.php";
require_once "./games.inc.php";

require_once "./tmpl_header.inc.php";
?>
<a class="tab" href="./">Zurück zur Startseite</a>
<h4>Alle Spiele</h4>
<?php
$sql->db_connect();
$SpielerNamen = players_FindAll();
$games = games_FindAll();
require_once './tmpl_games_list.inc.php';
$sql->close();
?>

</body>
</html>
