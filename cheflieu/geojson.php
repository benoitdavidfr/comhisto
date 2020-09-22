<?php
/*PhpDoc:
name: geojson.php
title: geojson.php - génération GeoJSON des chefs-lieux
doc: |
journal: |
  18/9/2020:
    - création
*/
require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/cheflieu.inc.php';

ChefLieu::load(__DIR__.'/../cheflieu');
//print_r(ChefLieu::$all); die();
echo json_encode(ChefLieu::asGeoJsonFC(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),"\n";
