<?php
/*PhpDoc:
name: ogcapi.php
title: api/ogcapi.php - API d'accès à ComHisto conforme à OGC API Features
doc: |
  Logique sur les formats:
    Je définis 4 types MIME possibles pour le résultat:
     - json   = 'application/json'
     - gejson = 'application/geo+json'
     - jsonld = 'application/ld+json'
     - html = 'text/html'

    Le type MIME résultant dépend de 3 paramètres:
     - le paramètre Http Accept
     - un éventuel format défini dans le path_info
     - le Content-Type défini par getRecord()

    Par ailleurs, il existe 2 possibilités de structuration: ld ou !ld
    Je considère ld == type in (jsonld,html)

    Algo:
      - 4 types MIME sont détectés dans le paramètre Http Accept
      - $format est simplifié et contient une des 4 valeurs: json, geojson, jsonld ou html
      - si $path_info contient un format alors $format est corrigé
      - calcul de $ld en fonction de $accept
        $ld ::= $format in ('jsond','html')
      - appel de getRecord() avec ld en paramètre
        - construction du résultat en fonction de ld
          - détermination du Content-Type en fonction du cas de figure
      - si format=='html' alors
        - génération de l'html et sortie en Html
      - sinonsi erreur
        - affichage de l'erreur
      - sinon
        - utilisation du Content-Type défini par getRecord et affichage du $record['body'] en JSON
  
  A faire:
    - dans /collections/collId/items prise en compte des paramètres bbox, date, ...
    - mise en oeuvre de property
    - JSON-LD ?
journal: |
  27/11/2020:
    - améliorations
  26/11/2020:
    - reconception de la gestion du format
  25/11/2020:
    - création
*/
require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../../../../phplib/pgsql.inc.php';
require_once __DIR__.'/../map/openpg.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

function checkBbox(array $bbox): bool { // Vérifie que la Bbox est bien formée
  if (!in_array(count($bbox), [4,6]))
    return false;
  foreach ($bbox as $coord)
    if (!is_numeric($coord))
      return false;
  if (count($bbox) == 4) {
    if ($bbox[2] < $bbox[0])
      return false;
    if ($bbox[3] < $bbox[1])
      return false;
  }
  else {
    if ($bbox[3] < $bbox[0])
      return false;
    if ($bbox[4] < $bbox[1])
      return false;
    if ($bbox[5] < $bbox[2])
      return false;
  }
  return true;
}

/* retourne l'enregistrement correspondant au path_info passé en paramètre
  le paramètre $ld définit, dans certains cas, si l'enregistrement doit être structuré en JSON/GéoJSON ou en JSON-LD
  si ok alors le résultat est un array avec
  - 1) un champ 'header' avec notamment un sous-champ 'Content-Type' avec le type MIME du résultat
  - 2) un champ 'body' avec l'enregistrement lui-même.
  - 3) Si le path_info impose un format de sortie alors il est retourné dans le champ 'outputFormat'
  En cas d'erreur retourne un array avec un champ 'error' contenant
  - 1) un champ 'httpCode' qui est un code d'erreur Http
  - 2) un champ 'message' qui est un message d'erreur sous la forme d'un texte
*/
function getRecord(string $path_info, bool $ld): array {
  $baseUrl = ($_SERVER['HTTP_HOST']=='localhost') ?
      'http://localhost/yamldoc/pub/comhisto/ogcapi/ogcapi.php'
      : 'https://comhisto.geoapi.fr';
  if (!$path_info || ($path_info == '/')) {
    return [
      'header'=> ['Content-Type'=> 'application/json'],
      'body'=> [
        'title'=> "Référentiel communal historique simplifié (ComHisto)",
        'description' => "Accès aux entités de ComHisto via une API Web conforme au standard OGC API Features",
        'links'=> [
          [ 'href'=> "$baseUrl/",
            'rel'=> 'self',
            'type'=> 'application/json',
            'title'=> "Ce document",
          ],
          [ 'href'=> "$baseUrl/openapi",
            'rel'=> 'service-desc',
            'type'=> 'application/vnd.oai.openapi+json;version=3.0',
            'title'=> "La définition de l'API",
          ],
          [ //'href'=> "$baseUrl/api.html",
            'href'=> "https://app.swaggerhub.com/apis-docs/benoitdavidfr/comhistoogcapi/0.5",
            'rel'=> 'service-doc',
            'type'=> 'text/html',
            'title'=> "La documentation de l'API",
          ],
          [ 'href'=> "$baseUrl/conformance",
            'rel'=> 'conformance',
            'type'=> 'application/json',
            'title'=> "Classes de conformité de l'API OGC implémentées par ce serveur",
          ],
          [ 'href'=> "$baseUrl/collections",
            'rel'=> 'data',
            'type'=> 'application/json',
            'title'=> "Informations sur les collections d'objets",
          ],
        ],
      ],
    ];
  }
  
  if ($path_info == '/openapi') {
    return [
      'header'=> ['Content-Type'=> 'application/vnd.oai.openapi+json;version=3.0'],
      'body'=> Yaml::parseFile(__DIR__.'/openapi.yaml'),
    ];
  }

  if ($path_info == '/conformance') {
    return [
      'header'=> ['Content-Type'=> 'application/json'],
      'body'=> [
        'conformsTo'=> [
          'http://www.opengis.net/spec/ogcapi-features-1/1.0/conf/core',
          'http://www.opengis.net/spec/ogcapi-features-1/1.0/conf/oas30',
          'http://www.opengis.net/spec/ogcapi-features-1/1.0/conf/html',
          'http://www.opengis.net/spec/ogcapi-features-1/1.0/conf/geojson',
        ],
      ],
    ];
  }

  $collections = [
    'vCom' => [
      "id" => "vCom",
      "title" => "Version de commune avec une résolution de 1e-3 degré",
      "description" => "Version de commune constituant l'historique du découpage communal français, avec une résolution de 1e-3 degré.",
      "extent" => [
        "spatial" => [
          "bbox" => [
            [-5.16, 42.32, 8.24, 51.09], // France métropolitaine hors Corse
            [ 8.53, 41.33, 9.57, 43.03], // Corse
            [-61.81, 15.83, -61.00, 16.52], // Guadeloupe
            [-61.24, 14.38, -60.80, 14.89], // Martinique
            [-54.61,  2.11, -51.63,  5.75], // Guyane
            [55.21, -21.40, 55.84, -20.87], // La Réunion
            [44.95, -13.08, 45.31, -12.58], // Mayotte
          ],
        ],
        "temporal" => [
          "interval" => [
            [
              '1943-01-01',
              '2020-01-01',
            ],
          ],
        ],
      ],
      "links" => [
        [
          "href" => "$baseUrl/collections/vCom/items",
          "rel" => "items",
          "type" => "application/geo+json",
          "title" => "vCommune",
        ],
        [
          "href" => "https://www.etalab.gouv.fr/licence-ouverte-open-licence",
          "rel" => "license",
          "type" => "text/html",
          "title" => "Licence ouverte Etalab",
        ],
      ],
    ],
    'vErat' => [
      "id" => "vErat",
      "title" => "Version d'entité rattachée avec une résolution de 1e-3 degré",
      "description" => "Versions d'entités rattachées constituant l'historique du découpage communal français, avec une résolution de 1e-3 degré.",
      "extent" => [
        "spatial" => [
          "bbox" => [
            [-5.16, 42.32, 8.24, 51.09], // France métropolitaine hors Corse
            [ 8.53, 41.33, 9.57, 43.03], // Corse
            [-61.81, 15.83, -61.00, 16.52], // Guadeloupe
            [-61.24, 14.38, -60.80, 14.89], // Martinique
            [-54.61,  2.11, -51.63,  5.75], // Guyane
            [55.21, -21.40, 55.84, -20.87], // La Réunion
            [44.95, -13.08, 45.31, -12.58], // Mayotte
          ],
        ],
        "temporal" => [
          "interval" => [
            [
              '1943-01-01',
              '2020-01-01',
            ],
          ],
        ],
      ],
      "links" => [
        [
          "href" => "$baseUrl/collections/vErat/items",
          "rel" => "items",
          "type" => "application/geo+json",
          "title" => "vErat",
        ],
        [
          "href" => "https://www.etalab.gouv.fr/licence-ouverte-open-licence",
          "rel" => "license",
          "type" => "text/html",
          "title" => "Licence ouverte Etalab",
        ],
      ],
    ],
  ];
  if ($path_info == '/collections') {
    return [ // le seul résultat généré est celui en JSON
      'header'=> ['Content-Type'=> 'application/json'],
      'body'=> [
          "links" => [
            [
              "href" => "$baseUrl/collections.json",
              "rel" => "self",
              "type" => "application/json",
              "title" => "this document",
            ],
            [
              "href" => "$baseUrl/collections.html",
              "rel" => "alternate",
              "type" => "text/html",
              "title" => "this document as HTML",
            ],
            /*[
              "href" => "http://schemas.example.org/1.0/buildings.xsd",
              "rel" => "describedBy",
              "type" => "application/xml",
              "title" => "GML application schema for Acme Corporation building data",
            ],
            [
              "href" => "http://download.example.org/buildings.gpkg",
              "rel" => "enclosure",
              "type" => "application/geopackage+sqlite3",
              "title" => "Bulk download (GeoPackage)",
              "length" => 472546,
            ],*/
          ],
          "collections" => array_values($collections),
      ],
    ];
  }
  
  if (preg_match('!^/collections/([^/]*)?$!', $path_info, $matches)) {
    $collectionId = $matches[1];
    if (!isset($collections[$collectionId]))
      return ['error'=> ['httpCode'=> 404, 'message'=> "Collection $collectionId inexistante"]];
    else
      return [ // le seul résultat généré est celui en JSON
        'header'=> ['Content-Type'=> 'application/json'],
        'body'=> $collections[$collectionId],
      ];
  }
  
  if (preg_match('!^/collections/([^/]*)/items$!', $path_info, $matches)) {
    $collectionId = $matches[1];
    if (!isset($collections[$collectionId]))
      return ['error'=> ['httpCode'=> 404, 'message'=> "Collection $collectionId inexistante"]];
    $limit = $_GET['limit'] ?? 10;
    if (!is_numeric($limit) || ($limit > 1000) || ($limit <= 0))
      return ['error'=> ['httpCode'=> 400, 'message'=> "Paramètre limit=$limit incorrect"]];
    $startindex = $_GET['startindex'] ?? 0;
    if (!is_numeric($startindex) || ($startindex < 0))
      return ['error'=> ['httpCode'=> 400, 'message'=> "Paramètre startindex=$startindex incorrect"]];
    $bbox = isset($_GET['bbox']) ? implode(',', $_GET['bbox']) : [];
    if ($bbox && !checkBbox($bbox))
      return ['error'=> ['httpCode'=> 400, 'message'=> "Paramètre bbox=$_GET[bbox] incorrect"]];
    $datetime = $_GET['datetime'] ?? null;
    $properties = [];
    foreach ($_GET as $key => $value) {
      if (!in_array($key, ['limit','bbox','datetime']))
        $properties[$key] = $value;
    }
          
    $t = ($collectionId == 'vCom') ? 's': (($collectionId == 'vErat') ? 'r': 'ERROR');
    $sql = "select id, cinsee, ddebut, edebut, dfin, efin, statut, crat, erats, elits, dnom, ST_AsGeoJSON(geom) geom
      from comhistog3 where type='$t'
      limit $limit offset $startindex";

    try {
      $features = [];
      foreach (PgSql::getTuples($sql) as $tuple) {
        foreach (['geom','edebut','efin','erats','elits'] as $prop)
          $tuple[$prop] = json_decode($tuple[$prop], true);;
        $geom = $tuple['geom'];
        unset($tuple['geom']);
        $id = $tuple['id'];
        unset($tuple['id']);
        $features[] = [
          'type'=> 'Feature',
          'id'=> $id,
          'properties'=> $tuple,
          'geometry'=> $geom,
        ];
      }
      
    }
    catch (Exception $e) {
      echo "<pre>sql=$sql</pre>\n";
      echo $e->getMessage();
      throw new Exception($e->getMessage());
    }
    return [ // résultat généré en GéoJSON ou JSON-LD
      'header'=> ['Content-Type'=> $ld ? 'application/ld+json' : 'application/geo+json'],
      'body'=> [
        'type'=> 'FeatureCollection',
        'features' => $features,
        'links'=> [
          [
            "type" => "application/geo+json",
            "rel" => "self",
            "title" => "This document as GeoJSON",
            "href" => "$baseUrl/collections/$collectionId/items.json",
          ],
          [
            "rel" => "alternate",
            "type" => "application/ld+json",
            "title" => "This document as RDF (JSON-LD)",
            "href" => "$baseUrl/collections/$collectionId/items.jsonld",
          ],
          [
            "type" => "text/html",
            "rel" => "alternate",
            "title" => "This document as HTML",
            "href" => "$baseUrl/collections/$collectionId/items.html",
          ],
          [
            "type" => "application/geo+json",
            "rel" => "next",
            "title" => "items (next)",
            "href" => "$baseUrl/collections/$collectionId/items?startindex=".($startindex+$limit),
          ],
          [
            "type" => "application/json",
            "title" => $collections[$collectionId]['title'],
            "rel" => "collection",
            "href" => "$baseUrl/collections/$collectionId",
          ],
        ]
      ],
    ];
  }
  
  if (preg_match('!^/collections/([^/]*)/items/([^/]*)$!', $path_info, $matches)) {
    $collectionId = $matches[1];
    $fId = $matches[2];
    if (!isset($collections[$collectionId]))
      return ['error'=> ['httpCode'=> 404, 'message'=> "Collection $collectionId inexistante"]];
    $sql = "select id, cinsee, ddebut, edebut, dfin, efin, statut, crat, erats, elits, dnom, ST_AsGeoJSON(geom) geom
      from comhistog3 where id='$fId'";

    try {
      $tuples = PgSql::getTuples($sql);
    }
    catch (Exception $e) {
      echo "<pre>sql=$sql</pre>\n";
      echo $e->getMessage();
      throw new Exception($e->getMessage());
    }
    if (count($tuples) == 0)
      return ['error'=> ['httpCode'=> 404, 'message'=> "Item $fId inexistant"]];
    $tuple = $tuples[0];
    foreach (['geom','edebut','efin','erats','elits'] as $prop)
      $tuple[$prop] = json_decode($tuple[$prop], true);;
    $geom = $tuple['geom'];
    unset($tuple['geom']);
    $id = $tuple['id'];
    unset($tuple['id']);
    $feature = [
      'type'=> 'Feature',
      'id'=> $id,
      'properties'=> $tuple,
      'geometry'=> $geom,
    ];

      
    return [ // résultat généré en GéoJSON ou JSON-LD
      'header'=> ['Content-Type'=> $ld ? 'application/ld+json' : 'application/geo+json'],
      'body'=> $feature,
    ];
  }
  
  return ['error'=> ['httpCode'=> 400, 'message'=> "Erreur requête non interprétée pour $path_info"]];
}


define('JSON_ENCODE_OPTIONS', JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

//echo "<pre>"; print_r($_SERVER); die();
// $format déduit de HTTP_ACCEPT
$http_accept = explode(',', $_SERVER['HTTP_ACCEPT'] ?? '');
if (array_intersect($http_accept, ['application/json','application/geo+json']))
  $format = 'json';
elseif (in_array('application/ld+json', $http_accept))
  $format = 'jsonld';
else
  $format = 'html';

$path_info = $_SERVER['PATH_INFO'] ?? '';
//echo "path_info=$path_info<br>\n";
// $format est corrigé s'il est défini dans le path_info
if (preg_match('!\.(json|geojson|jsonld|html)$!', $path_info, $matches)) {
  $format = $matches[1];
  $path_info = substr($path_info, 0, strlen($path_info)-strlen($format)-1);
} 

if (isset($_GET['f'])) {
  $format = $_GET['f'];
}

$record = getRecord($path_info, in_array($format, ['jsonld','html']));
$record['format'] = $format;
//print_r($record);

if ($format=='html') { // sortie Html
  echo "<pre><b>Sortie en Html</b>\n";
  echo Yaml::dump($record, 4, 2);
}
elseif ($error = $record['error'] ?? null) { // erreur
  define('HTTP_ERROR_LABELS', [
    400 => 'Bad Request', // La syntaxe de la requête est erronée.
    404 => 'Not Found', // Ressource non trouvée. 
    500 => 'Internal Server Error', // Erreur interne du serveur. 
    501 => 'Not Implemented', // Fonctionnalité réclamée non supportée par le serveur.
  ]);
  header("HTTP/1.1 $error[httpCode] ".(HTTP_ERROR_LABELS[$error['httpCode']] ?? "Undefined httpCode $error[httpCode]"));
  header('Content-type: text/plain');
  die($error['message']);
}
else { // Sortie JSON/GéoJSON/JSON-LD
  header('Content-Type: '.$record['header']['Content-Type']);
  die(json_encode($record['body'], JSON_ENCODE_OPTIONS));
}
