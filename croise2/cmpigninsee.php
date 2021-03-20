<?php
/*PhpDoc:
name: cmpigninsee.php
title: cmpigninsee.php - comparaison entre IGN et INSEE
doc: |
  Résultat:
    14114 (COMA) et 52224 (COMD) valides pour Insee et absents pour IGN
    Pb d'encodage IGN sur Œuilly/¼uilly (02565 + 51410), Œting/¼ting (57521) et Œuf-en-Ternois/¼uf-en-Ternois (62633)
journal: |
  9/11/2020:
    - création
*/
ini_set('memory_limit', '1G');

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../../../../phplib/pgsql.inc.php';
require_once __DIR__.'/histo.inc.php'; // classes Histo et Version stockant histelitp.yaml

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (php_sapi_name() <> 'cli') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>cmpigninsee</title></head><body><pre>\n";
}
else {
  $_GET['action'] = 'prod';
}

PgSql::open('host=pgsqlserver dbname=gis user=docker');

$entitesIgn= []; // liste des entités IGN
$sql = "select id, type, nom_com from commune_carto";
foreach (PgSql::query($sql) as $tuple) {
  $entitesIgn[$tuple['id']] = ['statut'=> $tuple['type'], 'name'=> $tuple['nom_com']];
}

$sql = "select id, type, nom_com, insee_ratt from entite_rattachee_carto";
foreach (PgSql::query($sql) as $tuple) {
  if (!isset($entitesIgn[$tuple['id']])) {
    $entitesIgn[$tuple['id']] = ['statut'=> $tuple['type'], 'name'=> $tuple['nom_com'], 'crat'=> $tuple['insee_ratt']];
  }
  elseif ($tuple['insee_ratt'] == $tuple['id']) {
    $entitesIgn[$tuple['id']]['nomCommeDéléguée'] = $tuple['nom_com'];
  }
  else {
    echo "Erreur de lecture IGN sur $id\n";
  }
}

Histo::load(__DIR__.'/histelitp.yaml');
//echo Yaml::dump(Histo::allAsArray(), 3, 2);

foreach (Histo::$all as $id => $histo) {
  if ($vvalide = $histo->vvalide()) {
    if (in_array($id, [97123, 97127])) continue; // exclusion StBarth et StMartin
    //print_r($vvalide);
    //echo Yaml::dump([$id => $vvalide->asArray()], 3, 2);
    if (!isset($entitesIgn[$id])) {
      echo "<b>$id valide pour Insee et absent pour IGN</b>\n";
      echo Yaml::dump([$id => $vvalide->asArray()], 3, 2);
    }
    elseif ($vvalide->asArray()['état'] <> $entitesIgn[$id]) {
      echo Yaml::dump([$id => ['insee'=> $vvalide->asArray()['état'], 'ign'=> $entitesIgn[$id]]], 3, 2);
    }
  }
}
foreach ($entitesIgn as $id => $entiteIgn) {
  if (!($histo = Histo::$all[$id]))
    echo "$id présent pour IGN et absent pour Insee\n";
  elseif (!$histo->vvalide())
    echo "$id présent pour IGN et non valide pour Insee\n";
}
