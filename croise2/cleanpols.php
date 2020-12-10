<?php
/*PhpDoc:
name: cleanpols
title: cleanpols - nettoyage d'anomalies géométriques de comhistog3 générées par la fusion d'entités
doc: |
  Détecte et corrige différentes anomalies dans comhistog3
  Mode de prévisualisation pour vérifier ce que l'on fait
journal: |
  10/12/2020:
    - création du script et correction de la base
*/
{/*Corrections effectuées le 10/12/2020
www-data@dmac:~/html/yamldoc/pub/admhisto$ php clean.php
s01033@2019-01-01:
    - 'erreur LPos sur iRing=1'
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
    - 'erreur sliver sur iRing=3'
    - 'erreur sliver sur iRing=4'
    - 'erreur sliver sur iRing=5'
s01036@2019-01-01:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
s02439@2016-01-01:
    - 'erreur sliver sur iRing=1'
s08115@2016-01-01:
    - 'erreur sliver sur iRing=1'
s08490@2016-06-01:
    - 'erreur sliver sur iRing=1'
s14011@2017-01-01:
    - 'erreur LPos sur iRing=2'
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
s14027@2017-01-01:
    - 'erreur sliver sur iRing=1'
s14061@2016-01-01:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
s14342@2017-01-01:
    - 'erreur sliver sur iRing=1'
s14431@2017-01-01:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
    - 'erreur sliver sur iRing=3'
s14654@2017-01-01:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
    - 'erreur sliver sur iRing=3'
    - 'erreur sliver sur iRing=4'
    - 'erreur sliver sur iRing=5'
    - 'erreur sliver sur iRing=6'
    - 'erreur sliver sur iRing=7'
    - 'erreur sliver sur iRing=8'
    - 'erreur sliver sur iRing=9'
s14735@1973-01-01:
    - 'erreur sliver sur iRing=1'
s14762@2016-01-01:
    - 'erreur sliver sur iRing=1'
s15108@2016-01-01:
    - 'erreur sliver sur iRing=1'
s16046@2017-01-01:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
    - 'erreur sliver sur iRing=3'
s16046@2019-01-01:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
    - 'erreur sliver sur iRing=3'
    - 'erreur sliver sur iRing=4'
s16230@2017-01-01:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
s22084@2016-01-01:
    - 'erreur LPos sur iRing=2'
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
    - 'erreur sliver sur iRing=3'
    - 'erreur sliver sur iRing=4'
s24064@2019-01-01:
    - 'erreur sliver sur iRing=1'
s24068@1974-01-01:
    - 'erreur sliver sur iRing=1'
s24068@1989-02-15:
    - 'erreur sliver sur iRing=1'
s25527@1974-06-01:
    - 'erreur sliver sur iRing=1'
s27105@2016-01-01:
    - 'erreur sliver sur iRing=1'
s27198@2019-01-01:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
    - 'erreur sliver sur iRing=3'
s27528@1969-04-15:
    'Polygone 1': ['erreur LPos sur iRing=1']
s28183@2016-01-01:
    - 'erreur sliver sur iRing=1'
s28199@2019-01-01:
    - 'erreur sliver sur iRing=1'
s28226@1972-12-26:
    - 'erreur LPos sur iRing=1'
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
s28226@1982-01-01:
    - 'erreur LPos sur iRing=1'
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
s35069@2017-01-01:
    - 'erreur sliver sur iRing=1'
s38022@2016-01-01:
    - 'erreur sliver sur iRing=1'
s39209@2018-01-01:
    - 'erreur sliver sur iRing=1'
s39331@2016-01-01:
    - 'erreur LPos sur iRing=2'
    - 'erreur sliver sur iRing=1'
s48099@2016-01-01:
    - 'erreur sliver sur iRing=1'
s49050@2016-12-15:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
s49092@2015-12-15:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
    - 'erreur sliver sur iRing=3'
s49125@2016-12-30:
    - 'erreur LPos sur iRing=1'
s49261@2018-01-01:
    - 'erreur sliver sur iRing=1'
s49301@2015-12-15:
    - 'erreur sliver sur iRing=1'
s50041@2017-01-01:
    - 'erreur sliver sur iRing=1'
s50129@2016-01-01:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
s50256@1973-03-15:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
s50273@2016-01-01:
    - 'erreur LPos sur iRing=1'
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
    - 'erreur sliver sur iRing=3'
    - 'erreur sliver sur iRing=4'
s50410@1973-01-01:
    - 'erreur LPos sur iRing=3'
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
s52064@2016-06-01:
    - 'erreur sliver sur iRing=1'
s52064@2019-01-01:
    - 'erreur sliver sur iRing=1'
s52158@1972-05-01:
    - 'erreur sliver sur iRing=1'
s52187@2013-02-28:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
s52332@1972-06-02:
    - 'erreur sliver sur iRing=1'
s52332@1974-03-09:
    - 'erreur sliver sur iRing=1'
s52332@2012-01-01:
    - 'erreur sliver sur iRing=1'
s57306@1972-01-01:
    - 'erreur sliver sur iRing=1'
s59183@2010-12-09:
    'Polygone 1': ['erreur LPos sur iRing=1', 'erreur sliver sur iRing=1']
s61167@2016-01-01:
    - 'erreur sliver sur iRing=1'
s61309@2016-01-01:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
    - 'erreur sliver sur iRing=3'
s61339@2016-01-01:
    - 'erreur sliver sur iRing=1'
s70285@2019-01-01:
    - 'erreur sliver sur iRing=1'
s70285@1972-12-31:
    - 'erreur sliver sur iRing=1'
s71014@1973-07-15:
    - 'erreur sliver sur iRing=1'
s73003@2019-01-01:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
s73006@2016-01-01:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
s73010@2016-01-01:
    - 'erreur sliver sur iRing=1'
s74010@2017-01-01:
    - 'erreur sliver sur iRing=1'
s79013@2016-01-01:
    - 'erreur LPos sur iRing=2'
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
s79049@1973-01-01:
    - 'erreur sliver sur iRing=1'
s79049@1983-02-15:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
s79078@2018-01-01:
    - 'erreur sliver sur iRing=1'
s79174@2019-01-01:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
s79196@1973-01-01:
    - 'erreur sliver sur iRing=1'
s79196@2019-01-01:
    - 'erreur sliver sur iRing=1'
s80621@2017-01-01:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
    - 'erreur sliver sur iRing=3'
s85051@1972-07-01:
    - 'erreur sliver sur iRing=1'
s88351@1973-01-01:
    - 'erreur sliver sur iRing=1'
    - 'erreur sliver sur iRing=2'
s89086@2016-01-01:
    - 'erreur sliver sur iRing=1'
Fin Ok
*/}

require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/../../../phplib/pgsql.inc.php';
require_once __DIR__.'/../../../geovect/gegeom/gegeom.inc.php';
require_once __DIR__.'/../../../geovect/gegeom/gddrawing.inc.php';
require_once __DIR__.'/../../../geovect/coordsys/light.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use \gegeom\GdDrawing;
use \gegeom\GBox;
use \gegeom\Geometry;
use \gegeom\LineString;

if ((php_sapi_name() <> 'cli') && ('draw' <> ($_GET['action'] ?? null))) {
  echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>cleanpols</title></head><body><pre>\n";
}

if (0) { // Vérification sémantique == sur liste 
  echo [0,1,2]==[0,1,2] ? "oui\n" : "non\n";
  echo [0,1,2]===[0,1,2] ? "oui\n" : "non\n";
  echo [0,1,2]==[0,2,1] ? "oui\n" : "non\n";
  echo [['a','b'],['c','d'],['e','f']]==[['a','b'],['c','d'],['e','f']] ? "oui\n" : "non\n";
  echo [['a','b'],['c','d'],['e','f']]==[['a','b'],['c','d'],['e']] ? "oui\n" : "non\n";
  die();
}

// détecte les erreurs sur une liste de positions
// Si erreur alors retourne la LPos corrigée qui peut éventuellement être []
// Si correcte alors retourne null
function errorLPos(array $lpos): ?array {
  //echo Yaml::dump(['errorLPos'=> $lpos]);
  $newLpos = [$lpos[0]];
  $error = false;
  for ($ipt=1; $ipt < count($lpos)-1; $ipt++) {
    if (($lpos[$ipt-1] == $lpos[$ipt+1])) {
      $error = true;
      $ipt++;
    }
    else {
      $newLpos[] = $lpos[$ipt];
    }
  }
  if ($lpos[count($lpos)-1] != $newLpos[count($newLpos)-1])
    $newLpos[] = $lpos[count($lpos)-1];
  if (!$error)
    return null;
  elseif (count($newLpos)==1)
    return [];
  else
    return $newLpos;
}
if (0) { // Test unitaire de errorLpos 
  //echo Yaml::dump(["cas ok"=> errorLpos([1, 2, 4, 1])]),"\n";
  //echo Yaml::dump(["cas erreur"=> errorLpos([1, 2, 3, 2, 4, 1])]),"\n";
  $lpos = [
    [5.8668195, 46.1368363],
    [5.8502158503109, 46.132763637662],
    [5.8668195, 46.1368363],
  ];
  echo Yaml::dump(["cas"=> errorLPos($lpos)]),"\n";
  die();
}

// itère errorLPos() tant que correction ou [],
// retourne null si le paramètre est ok, sinon la dernière valeur correcte
function erreurLPosIter(array $lpos): ?array {
  if (($lpos = errorLPos($lpos)) === null) // si $lpos correcte
    return null; // alors retourne null
  while ($lpos) {
    $precLPos = $lpos;
    $lpos = errorLPos($lpos);
  }
  if ($lpos === [])
    return [];
  else
    return $precLPos;
}
if (0) { // Test unitaire 
  echo Yaml::dump(["cas ok"=> erreurLPosIter([0, 1, 2, 3, 0])]),"\n";
  echo Yaml::dump(["cas dégénéré"=> erreurLPosIter([0, 1, 2, 3, 2, 1, 0])]),"\n";
  $lpos = [
      [5.8668195, 46.1368363],
      [5.8502158503109, 46.132763637662],
      [5.850179688428, 46.132676035072],
      [5.8502158503109, 46.132763637662],
      [5.8668195, 46.1368363],
  ];
  echo Yaml::dump(["cas réel"=> erreurLPosIter($lpos)]),"\n";
  die();
}

function sliverIndicator(array $lpos) { // indicateur pour détecter un sliver, cad un polygone très applati
  // je calcule le rapport de la surface sur le quart de la longueur au carré
  // l'indicateur n'a pas de dimension, il est identique quelle que soit la taille de l'objet
  // pour un carré il vaut 1
  $gegeom = new LineString($lpos);
  $qlen = $gegeom->length() / 4;
  if ($qlen == 0)
    return true;
  else
    return abs($gegeom->areaOfRing() / ($qlen*$qlen));
}
if (0) { // Test unitaire 
  foreach ([
    'carré 1'=> [[0,0],[0,1],[1,1],[1,0],[0,0]],
    'carré 1000'=> [[0,0],[0,1000],[1000,1000],[1000,0],[0,0]],
    'sliver'=> [[0,0],[1000,1],[1000,0],[0,0]],
  ] as $label => $lpos)
    echo "$label -> ",sliverIndicator($lpos),"\n";
  die();
}

function sliver(array $lpos): bool { // détecte un sliver, retourne true ssi c'en est un, fondé sur sliverIndicator()
  /*echo Yaml::dump(['$lpos'=> $lpos]);
  $sliverIndicator = sliverIndicator($lpos);
  echo "sliverIndicator=$sliverIndicator\n";*/
  return (count($lpos) < 4) || (sliverIndicator($lpos) < 1e-3);
}

// détecte les erreurs dans la géométrie et s'il y en a renvoie une géométrie corrigée, sinon retourne null
// 3 types d'erreurs sont recherchées:
//  - les points pour lesquels le pt précédent et le pt suivant sont identiques
//  - les trous constitués uniquement d'une ligne sans surface
//  - les trous qui sont des slivers
function cleanGeom(array $geom, array &$errors): ?array {
  $errors = [];
  $newCoords = []; // stoke les erreurs détectées pour les retourner
  switch ($geom['type']) {
    case 'Polygon': {
      //foreach ($geom['coordinates'] as $iRing => $ring) {
        //echo "$iRing -> ",sliverIndicator($ring)," -> ",sliver($ring) ? 'sliver' : 'NOT sliver',"\n";
      //}
      foreach ($geom['coordinates'] as $iRing => $ring) {
        if (($lpos = erreurLPosIter($ring)) === null) { // pas d'erreur
          $newCoords[] = $ring; // recopie de la version d'origine
        }
        else { // erreur détectée
          if ($lpos === []) {
            if ($iRing == 0)
              throw new Exception("Erreur, contour extérieur vide");
          }
          else {
            $newCoords[] = $lpos; // nouvelle LPos
          }
          $errors[] = "erreur LPos sur iRing=$iRing";
        }
      }
      // supprime les trous sliver
      $newCoords2 = [];
      foreach ($newCoords as $iRing => $ring) {
        if (($iRing == 0) || !sliver($ring))
          $newCoords2[] = $ring;
        else
          $errors[] = "erreur sliver sur iRing=$iRing";
      }
      return $errors ? ['type'=> 'Polygon', 'coordinates'=> $newCoords2] : null;
    }
  
    case 'MultiPolygon': {
      $errorsForPolygon=[];
      foreach ($geom['coordinates'] as $iPol => $polygon) {
        if (($cleaned = cleanGeom(['type'=> 'Polygon', 'coordinates'=> $polygon], $errorsForPolygon)) === null) {
          $newCoords[] = $polygon;
        }
        else {
          $newCoords[] = $cleaned['coordinates'];
          $errors["Polygone $iPol"] = $errorsForPolygon;
        }
      }
      return $errors ? ['type'=> 'MultiPolygon', 'coordinates'=> $newCoords] : null;
    }
    
    default: throw new Exception("Type $geom[type] non traité");
  }
}

function gboxFromTuple(array $tuple): ?GBox { // crée un GBox à partir du résultat de la requête
  if ($tuple['xmin'] === null) return null;
  return new GBox([$tuple['xmin'], $tuple['ymin'], $tuple['xmax'], $tuple['ymax']]);
}

PgSql::open('host=172.17.0.4 dbname=gis user=docker');

if ($id = ($_GET['id'] ?? null)) { // Visualisation d'un tuple particulier
  //echo "visu id=$id, ligne=",__LINE__,"\n";
  $sql = "select id, ST_AsGeoJSON(geom) geom from comhistog3 where id='$id'";
  if ('draw' == ($_GET['action'] ?? null)) {
    $sqlbbox = "select min(ST_XMin(geom)) xmin, min(ST_YMin(geom)) ymin, max(ST_XMax(geom)) xmax, max(ST_YMax(geom)) ymax
                from comhistog3 where id='$id'";
    $bbox = gboxFromTuple(PgSql::getTuples($sqlbbox)[0]);
  
    //echo "visu id=$id, ligne=",__LINE__,"\n"; print_r($bbox);
    $projWebMerc = function(array $pos) { return WebMercator::proj($pos); };
    $drawing = new GdDrawing(600, 600, $bbox->proj($projWebMerc));
  
    // `__construct(int $width, int $height, ?BBox $world=null, int $bgColor=0xFFFFFF, float $bgOpacity=1)` - initialisation du dessin  
    foreach (PgSql::query($sql) as $tuple) {
      $geom = json_decode($tuple['geom'], true);
      $errors = [];
      if ($newgeom = cleanGeom($geom, $errors)) {
        if (!isset($_GET['new']))
          Geometry::FromGeoJSON($geom)
            ->proj($projWebMerc)
              ->draw($drawing, ['stroke'=> 0x800000, 'fill'=> 0xFF0000, 'fill-opacity'=> 0.2]);
        else
          Geometry::FromGeoJSON($newgeom)
            ->proj($projWebMerc)
              ->draw($drawing, ['stroke'=> 0x000080, 'fill'=> 0x0000FF, 'fill-opacity'=> 0.5]);
      }
      /*
      - stroke: couleur RVB de dessin d'une ligne brisée ou d'un contour de polygone
      - stroke-opacity : opacité entre 0 (transparent) et 1 (opaque)
      - fill: couleur RVB de remplissage d'un polygone
      - fill-opacity : opacité entre 0 (transparent) et 1 (opaque)
      */
    }
    $drawing->flush('image/png', false);
  }
  else {
    foreach (PgSql::query($sql) as $tuple) {
      $geom = json_decode($tuple['geom'], true);
      $errors = [];
      if ($newgeom = cleanGeom($geom, $errors)) {
        $yamlDepth = ($geom['type']=='Polygon') ? 4 : 5;
        echo "<table border=1><tr>",
          "<td valign='top'>",Yaml::dump([$tuple['id']=> $geom], $yamlDepth, 2),"</td>\n",
          "<td valign='top'>",Yaml::dump([$tuple['id']=> $newgeom], $yamlDepth, 2),"</td>\n",
          "<td valign='top'><img src='?action=draw&amp;id=$tuple[id]'></td>",
          "<td valign='top'><img src='?action=draw&amp;id=$tuple[id]&amp;new=1'></td>",
          "</tr></table>\n";
      }
    }
  }
}
elseif (1) { // visualisation interactive en mode web
  $sql = "select id, ST_AsGeoJSON(geom) geom from comhistog3";
  $errors = [];
  foreach (PgSql::query($sql) as $tuple) {
    $geom = json_decode($tuple['geom'], true);
    if ($newgeom = cleanGeom($geom, $errors)) {
      echo "<a href='?id=$tuple[id]'>Erreur détectée sur $tuple[id]</a>\n";
      echo Yaml::dump($errors);
    }
  }
}
else { // mise à jour de la base
  $sql = "select id, ST_AsGeoJSON(geom) geom from comhistog3";
  $errors = [];
  foreach (PgSql::query($sql) as $tuple) {
    $geom = json_decode($tuple['geom'], true);
    if ($newgeom = cleanGeom($geom, $errors)) {
      echo Yaml::dump([$tuple['id'] => $errors]);
      $newgeom = json_encode($newgeom);
      $sql = "update comhistog3 set geom=ST_SetSRID(ST_GeomFromGeoJSON('$newgeom'),4326) where id='$tuple[id]'";
      //echo "$sql\n";
      PgSql::query($sql);
    }
  }
  die("Fin Ok\n");
}
