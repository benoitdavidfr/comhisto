<?php
/*PhpDoc:
name: bzones.php
title: bzones.php - construit une forêt de zones géographiques structurée selon leur graphe d'inclusions stockée dans zones.yaml
screens:
doc: |
  Les zones sont les classes d'équivalence des entités (cs+er) ayant même zone géographique
  Elles sont structurées hiérarchiquement par l'inclusion géométrique

  L'algorithme pour les créer est le suivant:
    - traduction des infos Insee sous la forme de relations topologiques entre zones, soit égalité (sameAs) soit inclusion (includes)
    - construction des zones comme classes d'équivalence des sameAs et structuration avec la relation d'inclusion
    - j'associe à chacune le référentiel dans lequel elle est définie
    - calcul des stats

  Sur 40661 zones créées, il en reste environ 2000 définies dans aucun des réf. disponibles
  L'idée est de définir ces 2000 zones définies dans aucun des réf. disponibles en utilisant le diagramme de Voronoi
  sur leur chef-lieu.

  Ce script produit le fichier zones.yaml

journal:
  22/8/2020:
    - utilisation des éléments positifs produits par defeltp.php
  16/8/2020:
    - utilisation des éléments produits par defelt.php
  20/7/2020:
    - fork de ../../rpicom/rpigeo/bzone.php
*/
ini_set('memory_limit', '1G');
if (php_sapi_name() <> 'cli')
  set_time_limit (30);

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/histo.inc.php';
require_once __DIR__.'/zone.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (php_sapi_name() <> 'cli') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>bzones</title></head><body><pre>\n";
  if (!isset($_GET['action'])) {
    echo "</pre><a href='?action=bzones'>Construit les zones</a><br>\n";
    echo "<a href='?action=stats'>stats</a><br>\n";
    die();
  }
}
else {
  $_GET['action'] = 'bzones';
}

// gère diff. comptages définis chacun par un label
class Stats {
  static $stats=[];
  
  static function incr(string $label) {
    if (!isset(self::$stats[$label]))
      self::$stats[$label] = 1;
    else
      self::$stats[$label]++;
  }
  
  static function set(string $label, int $val): void { self::$stats[$label] = $val; }
  
  static function get(string $label): int { return self::$stats[$label]; }
  
  static function dump() { return Yaml::dump(['stats'=> self::$stats]); }
};

if ($_GET['action']=='bzones') {
  //Histo::load('histeltp');
  Histo::load('histeltptest');
  Histo::buildAllZones();
  echo "title: Liste des zones\n";
  echo "creator: bzones.php\n";
  echo "created: ",date(DATE_ATOM),"\n";
  //echo Yaml::dump(Zone::allAsArray(), 12, 2);
  Zone::dumpAll();
  //print_r(Zone::get('s14653@1943-01-01'));
  die("eof:\n");
}
