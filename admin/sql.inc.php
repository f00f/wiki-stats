<?php
class my_Sql_cl {
  var $link_id   = 0;
  var $query_id  = 0;

  var $server    = "";
  var $user      = "";
  var $passwort  = "";
  var $datenbank = "";

  var $errstring = "";
  var $errnr     = 0;

  function db_connect() {
    if ($this->link_id == 0) {
      if ($this->passwort=="") {
        $this->link_id = @mysql_connect("$this->server","$this->user"); mysql_set_charset('utf8',$this->link_id );
      } else {
        $this->link_id = @mysql_connect("$this->server","$this->user","$this->passwort"); mysql_set_charset('utf8',$this->link_id );
      }
    }
    if (!$this->link_id) {
      $this->stop("Datenbank-Verbindung fehlgeschlagen!");
    }
    if ($this->datenbank!="") {
	  if (!@mysql_select_db("$this->datenbank",$this->link_id)) {
	    $this->stop("Datenbank nicht gefunden ( $this->datenbank ) !");
	  }
    }
  }

  function query($string) {
    $this->query_id = mysql_query($string,$this->link_id);
    if (!$this->query_id) {
      $this->stop("Fehlerhafte SQL Abfrage!");
    }
      return $this->query_id;
  }

  function insert_id() {
    return mysql_insert_id($this->link_id);
  }

  function close() {
    mysql_close();
  }

  function stop($msg) {
    $this->errstring=mysql_error();
    $this->errnr=mysql_errno();
    $error_script = getenv("REQUEST_URI");
    $fehler = "<Table border=0 width=450 align=\"center\">\n";
    $fehler.= "<Tr><Td><b>Datenbank Error :</b> $msg</Td></Tr>\n";
    $fehler.= "<Tr><Td><b>mySQL Error :</b> $this->errstring</Td></Tr>\n";
    $fehler.= "<Tr><Td><b>mySQL Error Nummer :</b> $this->errnr</Td></Tr>\n";
    $fehler.= "<Tr><Td><b>Error Script :</b> ".substr($error_script,0,50)."...</Td></Tr>\n";
    $fehler.= "</Table>\n";
    echo $fehler;
    die("");
  }
}

$sql   = new my_Sql_cl;

// configure database connection
require_once 'db_config.inc.php';
