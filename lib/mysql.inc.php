<?php
/*PhpDoc:
name: mysql.inc.php
title: mysql.inc.php - classes MySql et MySqlResult utilisées pour exécuter des requêtes MySql
classes:
doc: |
  Simplification de l'utilisation de MySql.
  La méthode statique MySql::open() prend en paramètre les paramètres MySql
  sous la forme mysql://{user}:{passwd}@{host}/{database}
  Voir utilisation en fin de fichier
  Sur localhost si la base utilisée n'existe pas alors elle est créée.
journal: |
  23/11/2019:
    sur localhost si la base à ouvrir n'existe pas alors elle est créée. Cela simplifie le redémérrage d'un serveur docker vide
  3/8/2018 15:00
    ajout MySql::server()
  3/8/2018
    création
*/
class MySql {
  static $mysqli=null; // handle MySQL
  static $server=null; // serveur MySql
    
  // ouvre une connexion MySQL et enregistre le handle en variable de classe
  // Il est nécessaire de passer les paramètres MySQL en paramètre pour 
  static function open(string $mysqlParams) {
    if (!preg_match('!^mysql://([^:]+):([^@]+)@([^/]+)/(.*)$!', $mysqlParams, $matches))
      throw new Exception("Erreur: dans MySql::open() params \"".$mysqlParams."\" incorrect");
    //print_r($matches);
    self::$mysqli = @new mysqli($matches[3], $matches[1], $matches[2], $matches[4]);
    self::$server = $matches[3];
    // La ligne ci-dessous ne s'affiche pas correctement si le serveur est arrêté !!!
    //    throw new Exception("Connexion MySQL impossible pour $server_name : ".mysqli_connect_error());
    if (mysqli_connect_error()) {
      if ($_SERVER['HTTP_HOST'] <> 'localhost')
        throw new Exception("Erreur: dans MySql::open() connexion MySQL impossible sur $mysqlParams");
      // Sur localhost j'essaie de créer la base
      self::$mysqli = @new mysqli($matches[3], $matches[1], $matches[2], 'sys');
      if (mysqli_connect_error())
        throw new Exception("Erreur: dans MySql::open() connexion MySQL impossible sur $mysqlParams");
      $sql = 'CREATE DATABASE IF NOT EXISTS `shomgt` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci';
      if (!($result = self::$mysqli->query($sql))) {
        //echo "sql:$sql\n";
        throw new Exception("Req. \"$sql\" invalide: ".self::$mysqli->error);
      }
      if (!self::$mysqli->select_db($matches[4]))
        throw new Exception("select_db($matches[4]) invalide: ".self::$mysqli->error);
    }
    if (!self::$mysqli->set_charset ('utf8'))
      throw new Exception("Erreur: dans MySql::open() mysqli->set_charset() impossible : ".self::$mysqli->error);
  }
  
  static function server(): string {
    if (!self::$server)
      throw new Exception("Erreur: dans MySql::server() server non défini");
    return self::$server;
  }
  
  // exécute une requête MySQL, soulève une exception en cas d'erreur, renvoie le résultat
  static function query(string $sql) {
    if (!self::$mysqli)
      throw new Exception("Erreur: dans MySql::query() mysqli non défini");
    if (!($result = self::$mysqli->query($sql))) {
      //echo "sql:$sql\n";
      if (strlen($sql) > 1000)
        $sql = substr($sql, 0, 800)." ...";
      throw new Exception("Req. \"$sql\" invalide: ".self::$mysqli->error);
    }
    if ($result === TRUE)
      return TRUE;
    else
      return new MySqlResult($result);
  }
};

// la classe MySqlResult permet d'utiliser le résultat d'une requête comme un itérateur
class MySqlResult implements Iterator {
  private $result = null; // l'objet mysqli_result
  private $ctuple = null; // le tuple courant ou null
  private $firstDone = false; // vrai ssi le first rewind a été effectué
  
  function __construct(mysqli_result $result) { $this->result = $result; }
  
  function rewind(): void {
    if ($this->firstDone) // nouveau rewind
      throw new Exception("Erreur dans MySqlResult::rewind() : un seul rewind() autorisé");
    $this->firstDone = true;
    $this->next();
  }
  function current(): array { return $this->ctuple; }
  function key(): int { return 0; }
  function next(): void { $this->ctuple = $this->result->fetch_array(MYSQLI_ASSOC); }
  function valid(): bool { return ($this->ctuple <> null); }
};

if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;


//MySql::open(require(__DIR__.'/mysqlparams.inc.php'));
$sql = "select id_rte500, nom_comm, insee_comm, population, superficie, statut, id_nd_rte, ST_AsText(geom) geom "
      ."from route500.noeud_commune where nom_comm like 'BEAUN%'";
$sql = "describe route500.noeud_commune";
if (0) {  // Test 2 rewind 
  $result = MySql::query($sql);
  foreach ($result as $tuple) {
    print_r($tuple);
  }
  echo "relance\n";
  foreach ($result as $tuple) {
    print_r($tuple);
  }
}
else {
  foreach (MySql::query($sql) as $tuple) {
    print_r($tuple);
  }
}