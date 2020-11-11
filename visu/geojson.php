<?php
// génère une FeatureCollection GeoJSON d'un n-uplet de comhistog3

require_once __DIR__.'/../../../../phplib/pgsql.inc.php';

PgSql::open('host=172.17.0.4 dbname=gis user=docker password=docker');

$sql = "select id, ddebut, edebut, dfin, efin, statut, crat, erats, elits, dnom, ST_AsGeoJSON(geom) geom
        from comhistog3 where id='$_GET[id]'";
$tuple = PgSql::getTuples($sql)[0];
foreach (['edebut','efin','erats','elits','geom'] as $prop)
  $tuple[$prop] = json_decode($tuple[$prop], true);
$geom = $tuple['geom'];
unset($tuple['geom']);
header('Content-Type: application/json');

echo json_encode([
  'type'=> 'FeatureCollection',
  'features'=> [
    [
      'type'=> 'Feature',
      'properties'=> $tuple,
      'geometry'=> $geom,
    ]
  ],
]);
