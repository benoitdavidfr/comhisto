<?php
/*PhpDoc:
name: neighbor.php
title: map/neighbor.php - génère une FeatureCollection GeoJSON des communes voisines de la commune définie par le code Insee
doc: |
journal: |
  11/11/2020:
    - création
*/
require_once __DIR__.'/../../../../phplib/pgsql.inc.php';

PgSql::open('host=172.17.0.4 dbname=gis user=docker password=docker');

$features = [];
$sql = "select n.cinsee, n.type, n.dnom, ST_AsGeoJSON(n.geom) geom
        from comhistog3 n, comhistog3 c
        where ST_Distance(n.geom, c.geom) < 1
          and c.cinsee='$_GET[id]' and c.dfin is null and (c.type='s' or c.crat<>c.cinsee)
          and n.cinsee<>'$_GET[id]' and n.dfin is null
          and (n.type='s' or (n.type='r' and n.crat='$_GET[id]'))"; # soit COM soit ER non propre
foreach (PgSql::query($sql) as $tuple) {
  foreach (['geom'] as $prop)
    $tuple[$prop] = json_decode($tuple[$prop], true);
  $geom = $tuple['geom'];
  unset($tuple['geom']);
  $features[] = [
    'type'=> 'Feature',
    'properties'=> $tuple,
    'geometry'=> $geom,
  ];
}

if (!$features) {
  $sql = "select n.cinsee, n.type, n.dnom, ST_AsGeoJSON(n.geom) geom
          from comhistog3 n, comhistog3 c
          where ST_Distance(n.geom, c.geom) < 1
            and c.cinsee='$_GET[id]'
            and n.cinsee<>'$_GET[id]' and n.dfin is null
            and (n.type='s' or (n.type='r' and n.crat='$_GET[id]'))"; # soit COM soit ER non propre
  foreach (PgSql::query($sql) as $tuple) {
    foreach (['geom'] as $prop)
      $tuple[$prop] = json_decode($tuple[$prop], true);
    $geom = $tuple['geom'];
    unset($tuple['geom']);
    $features[] = [
      'type'=> 'Feature',
      'properties'=> $tuple,
      'geometry'=> $geom,
    ];
  }
}
header('Content-Type: application/json');
echo json_encode([
  'type'=> 'FeatureCollection',
  'features'=> $features,
],  JSON_PRETTY_PRINT);
