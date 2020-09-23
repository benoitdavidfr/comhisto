<?php
// Ajout St Barth et St Martin comme edaming3

require_once __DIR__.'/../../../../phplib/pgsql.inc.php';

if (php_sapi_name() <> 'cli') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>ajoutead</title></head><body><pre>\n";
}

PgSql::open('host=172.17.0.4 dbname=gis user=docker password=docker');

$fc = json_decode(file_get_contents(__DIR__.'/stbarthmartin.geojson'), true);
if ($fc === null) {
  die("Erreur JSON ".json_last_error());
}
foreach ($fc['features'] as $feature) {
  print_r($feature);
  $eid = 's'.$feature['properties']['cinsee'];
  $geom = json_encode($feature['geometry']);
  $sql = "insert into eadming3(eid,geom) values('$eid',ST_SetSRID(ST_GeomFromGeoJSON('$geom'),4326))";
  echo "$sql\n";
  PgSql::query($sql);
}
