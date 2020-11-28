<?php
// checkschema.php - vérifie que le résultat d'un items respecte le schema
ini_set('memory_limit', '1G');
if (php_sapi_name() <> 'cli')
  set_time_limit(2*60);

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../../../../schema/jsonschema.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

function getJson(string $url): array {
  $opts = [
    'http'=> [
      'method'=> 'GET',
      'header'=> "Accept: application/json\r\n",
    ],
  ];
  $context = stream_context_create($opts);
  //echo "url=$url\n";
  if (FALSE === $contents = @file_get_contents($url, false, $context)) {
    echo "<pre>Erreur de lecture de $url\nhttp_response_header = ";
    die(Yaml::dump(['header'=> response_header($http_response_header ?? null)], 3, 2));
  }
  if (($array = json_decode($contents, true)) === null)
    die("<pre><b>Erreur de décodage JSON</b>\n$contents");
  return $array;
}

// Url de la collection
$oafUrl = "http://localhost/yamldoc/pub/comhisto/ogcapi/ogcapi.php/collections/vCom";

$schema = getJson("$oafUrl/schema");
//print_r($schema);
$schema = new JsonSchema($schema, false);

$document = getJson("$oafUrl/items");

$status = $schema->check($document);
if (1) { // Vérification en une seule fois
  if (!$status->ok()) {
    echo "Echec de la vérification<br>\n";
    $status->showErrors();
  }
  else {
    echo "Vérification ok<br>\n";
    $status->showWarnings();
  }
}
else { // vérification feature par feature
  $features = $document['features'];
  foreach ($features as $feature) {
    $document['features'] = [$feature];
    $status = $schema->check($document);
    if (!$status->ok()) {
      echo "Echec de la vérification pour $feature[id]<br>\n";
      $status->showErrors();
    }
  }
}
die("Fin ok\n");