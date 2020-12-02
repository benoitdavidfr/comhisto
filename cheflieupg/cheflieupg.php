<?php
/*PhpDoc:
name: cheflieupg.php
title: cheflieupg.php - création d'une table des chefs-lieux dans PostGis
doc: |
  Mise en oeuvre de la solution définie dans phpdoc.yaml
  Algo:
    - création de la table et initialisation à partir de elit comme code Insee ancien
    - ajout en Php des nouveaux codes à partir de histolitp
    - ajout du denir nom local à partir de histolitp
    - remplissage des points de la table en Php en filtrant chef_lieu_carto un point par code Insee
    - complétion avec les chefs-lieux définis dans ../cheflieu

journal: |
  2/12/2020:
    - création
*/
ini_set('memory_limit', '1G');

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../cheflieu/cheflieu.inc.php'; // classe ChefLieu donnant accès aux chefs-lieux
require_once __DIR__.'/../lib/openpg.inc.php'; // Extension de PgSql pour simplifier l'appel des fonctions d'analyse spatiale
require_once __DIR__.'/../croise2/histo.inc.php'; // classes Histo et Version stockant histelitp.yaml

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class HistoChfl extends Histo {
  function lastLocalName(): string { // dernier nom local, cad que le nomCommeDéléguée est priviligié au nom de la commune nouvelle
    $name = null;
    foreach ($this->versions as $dv => $version) {
      if ($etat = $version->etat()) {
        $name = $etat['nomCommeDéléguée'] ?? $etat['name'];
      }
    }
    return $name;
  }
};

if (php_sapi_name() == 'cli') { // en cli on enchaine les différentes actions
  $_GET['action'] = 'cli';
}
else {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>chargecheflieupg</title></head><body><pre>";
  if (!isset($_GET['action'])) {
    echo "</pre><a href='?action=create'>création de la table et initialisation à partir d'elit</a><br>\n";
    echo "<a href='?action=cinseea'>ajout des nouveaux codes à partir de histolitp</a><br>\n";
    echo "<a href='?action=chef_lieu_carto'>remplissage des points en filtrant chef_lieu_carto un point par code Insee</a><br>\n";
    echo "<a href='?action=wpgp'>complétion avec les chefs-lieux définis dans ../cheflieu</a><br>\n";
    die();
  }
}

if (in_array($_GET['action'], ['create','cli'])) { // création de la table et initialisation à partir d'elit 
  PgSql::query("drop table if exists cheflieu");
  PgSql::query("drop type if exists cheflieu_source");
  PgSql::query("create type cheflieu_source AS enum (
    'absent',  -- géométrie absente
    'chef_lieu_carto', -- AdminExpress
    'wpgp' -- saisie à partir de WP ou du GP 
  )");
  PgSql::query("create table cheflieu(
    cinsee0 char(5) not null primary key, -- le code initial sert de clé
    cinseea char(5) not null, -- le code actuel évt identique à cinsee0
    dlnom varchar(256), -- dernier nom local, cad que le nom comme déléguée est priviligié au nom de la commune nouvelle
    source cheflieu_source not null, -- source du point
    pt geometry(POINT, 4326)
  )");
  PgSql::query("insert into cheflieu(cinsee0, cinseea, source) select cinsee, cinsee, 'absent' from elit");
  if ($_GET['action']=='create')
    die("create ok\n");
}

HistoChfl::load(__DIR__.'/../elits2/histelitp.yaml');
//print_r(HistoChfl::$all['01001']); die("Fin Test");

// Attention, Qqs codes changent plus de'1 fois de code comme Châteaufort (78143/91143/78143)
if (in_array($_GET['action'], ['cinseea','cli'])) { // ajout des nouveaux codes à partir de histolitp et ajout du dlnom
  foreach (Histo::$all as $cinsee0 => $histo) {
    if ($cinseea = $histo->changeDeCodePour()) { // ancien code ayant changé
      PgSql::query("update cheflieu set cinseea='$cinseea' where cinsee0='$cinsee0'");
      //echo "changeDeCodePour: $cinsee0 -> $cinseea\n";
    }
  }
  foreach (Histo::$all as $cinsee0 => $histo) {
    if (!$histo->changeDeCodePour()) { // pas de chgt de code ou le nouveau code
      $dlnom = $histo->lastLocalName();
      //echo "dlnom: $cinsee0 -> $dlnom\n";
      $dlnom = str_replace("'","''",$dlnom);
      PgSql::query("update cheflieu set dlnom='$dlnom' where cinseea='$cinsee0'");
    }
  }
  if ($_GET['action']=='cinseea')
    die("cinseea ok\n");
}

if (in_array($_GET['action'], ['chef_lieu_carto','cli'])) { // remplissage pt en filtrant chef_lieu_carto un par code Insee
  foreach (PgSql::query("select insee_com, ST_AsText(wkb_geometry) wkt from chef_lieu_carto") as $tuple) {
    PgSql::query("update cheflieu
      set source='chef_lieu_carto', pt=ST_GeomFromText('$tuple[wkt]',4326)
      where cinseea='$tuple[insee_com]'");
  }
  if ($_GET['action']=='chef_lieu_carto')
    die("chef_lieu_carto ok\n");
}

if (in_array($_GET['action'], ['wpgp','cli'])) { // complétion avec les chefs-lieux définis dans ../cheflieu
  $nbabsents = 0;
  ChefLieu::load(__DIR__.'/../cheflieu');
  foreach (PgSql::query("select cinsee0, cinseea from cheflieu where pt is null") as $tuple) {
    $cinseea = $tuple['cinseea'];
    try {
      $point = Histo::get($cinseea)->chefLieu();
    } catch(Exception $e) { // Si le chef-lieu n'existe pas dans ../cheflieu
      echo "$cinseea absent de chefLieu\n";
      //echo Yaml::dump([$cinseea => Histo::get($cinseea)->asArray()], 3, 2);
      $nbabsents++;
      continue;
    }
    try {
      $geom = ['type'=> 'Point', 'coordinates'=> $point];
      $sql2 = "update cheflieu
        set source='wpgp', pt=ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($geom)."'), 4326)
        where cinsee0='$tuple[cinsee0]'";
      PgSql::query($sql2);
    }
    catch(Exception $e) {
      echo "$sql2\n";
      echo $e->getMessage();
    }
  }
  echo "$nbabsents absents\n";
  if ($_GET['action']=='wpgp')
    die("wpgp ok\n");
}

die("Fin ok\n");
