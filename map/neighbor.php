<?php
/*PhpDoc:
name: neighbor.php
title: map/neighbor.php - génère une FeatureCollection GeoJSON des communes voisines de la commune définie par le code Insee
doc: |
  1er essai
    l'entité de référence doit être valide et soit de type 's' soit rattachée non propre à l'id
    les entités voisines doivent être valides et soit 's' soit rattachée propre à l'id
    Si aucune voisine n'est sélectionnée
  2ème essai
    moins de contraintes sur l'entité de référence, not. qui peut être non valide
journal: |
  11/11/2020:
    - création
*/
require_once __DIR__.'/../../../../phplib/pgsql.inc.php';

PgSql::open('host=172.17.0.4 dbname=gis user=docker password=docker');

$features = [];
$sqls = [
 "select n.cinsee, n.type, n.dnom, ST_AsGeoJSON(n.geom) geom
  from comhistog3 n, comhistog3 c
  where c.cinsee='$_GET[id]' and c.dfin is null and (c.type='s' or c.crat<>c.cinsee)
    and ST_Distance(n.geom, c.geom) < 1
    and n.cinsee<>'$_GET[id]' and n.dfin is null
    and (n.type='s' or (n.type='r' and n.crat='$_GET[id]'))",
 "select n.cinsee, n.type, n.dnom, ST_AsGeoJSON(n.geom) geom
  from comhistog3 n, comhistog3 c
  where c.cinsee='$_GET[id]'
    and ST_Distance(n.geom, c.geom) < 1
    and n.cinsee<>'$_GET[id]' and n.dfin is null
    and (n.type='s' or (n.type='r' and n.crat='$_GET[id]'))"
];

foreach ($sqls as $sql) {
  foreach (PgSql::query($sql) as $tuple) {
    $geom = $tuple['geom'];
    unset($tuple['geom']);
    $features[] = [
      'type'=> 'Feature',
      'properties'=> $tuple,
      'geometry'=> json_decode($geom, true),
    ];
  }
  if ($features) {
    header('Content-Type: application/json');
    die(json_encode(['type'=> 'FeatureCollection', 'features'=> $features], JSON_PRETTY_PRINT));
  }
}
header('Content-Type: application/json');
die(json_encode(['type'=> 'FeatureCollection', 'features'=> []]));
