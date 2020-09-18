<?php
// génération GeoJSON des chefs-lieux

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/cheflieu.inc.php';

ChefLieu::load(__DIR__.'/../cheflieu');
//print_r(ChefLieu::$all); die();
echo json_encode(ChefLieu::asGeoJsonFC(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),"\n";
