<?php
// checkschemachg3.php - vérifie le schéma de comhistog3.geojson
ini_set('memory_limit', '4G');
set_time_limit(2*60);

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../../../../schema/jsonschema.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

$schema = Yaml::parsefile(__DIR__.'/comhistog3.schema.yaml');
$schema = new JsonSchema($schema, false);

//$document = ['type'=>'FeatureCollection','features'=>[]];
$docname = 'comhistog3';
//$docname = 'chg3';
$document = json_decode(file_get_contents(__DIR__."/$docname.geojson"), true);
  
$status = $schema->check($document);
if (!$status->ok()) {
  echo "Echec de la vérification<br>\n";
  $status->showErrors();
}
else {
  echo "Vérification ok<br>\n";
  $status->showWarnings();
}
