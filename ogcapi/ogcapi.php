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
      - calcul de $ld en fonction de $accept -> $ld ::= $format in ('jsond','html')
      - appel de getRecord() avec $path_info et $ld en paramètres
        - construction du résultat en fonction de ld
          - détermination du Content-Type en fonction du cas de figure
      - si format=='html' alors
        - génération de l'html et sortie en Html
      - sinonsi erreur
        - affichage de l'erreur
      - sinon
        - utilisation du Content-Type défini par getRecord et affichage du $record['body'] en JSON
  
  A faire:
    - exprimer le lien entre le geojson:Feature et un City, comment faire ?
journal: |
  2/12/2020:
    - assouplissement du format de datetime
  30/11/2020:
    - ajout d'un log
  29/11/2020:
    - améliorations
  28/11/2020:
    - ajout du lien de la collection vers son schema JSON
    - traitement des paramètres bbox et datetime
    - traitement des paramètres properties et de chaque property
  27/11/2020:
    - améliorations
    - chgt de l'id de vCom|vErat en retirant le type déjà présent dans le nom de la collection
  26/11/2020:
    - reconception de la gestion du format
  25/11/2020:
    - création
*/
require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../lib/openpg.inc.php';
require_once __DIR__.'/../lib/config.inc.php';
require_once __DIR__.'/../lib/log.inc.php';
require_once __DIR__.'/../lib/isodate.inc.php';

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

// transforme une chaine RFC 3339 en un array [start, end] où end est null ssi il s'agit d'une date ponctuelle
// start et end sont tronqués à la date
// Si chaine vide retourne []
// Si erreur retourne un array ['error'=> message]
function interval(string $datetime): array {
  if (!$datetime)
    return [];
  $date_pattern = '(([\d-]+)(T[^/]*)?)|(\.\.)'; // motif simplifié, la date est vérifiée par checkIsoDate()
  if (!preg_match("!^($date_pattern)(/($date_pattern))?$!", $datetime, $matches)) {
    return ['error'=> "Paramètre datetime=$datetime incorrect"];
  }
  //echo "<pre>matches="; print_r($matches); echo "</pre>\n";
  $start = $matches[3] ? $matches[3] : '..';
  if (!checkIsoDate($start))
    return ['error'=> "Paramètre datetime1=$start incorrect"];
  $end = !isset($matches[6]) ? null : ($matches[9] ? $matches[9] : '..');
  if ($end && !checkIsoDate($end))
    return ['error'=> "Paramètre datetime2=$end incorrect"];
  if (($start == '..') && !$end)
    return ['error'=> "Paramètre datetime=$datetime incorrect"];
  return [ $start, $end];
}

if (0) { // Tests de la fonction interval() 
  echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>ogcapi test interval</title></head><body><pre>\n";
  foreach ([
    '',
    'xx',
    '..',
    '2020-01-01',
    '2020-01-01/2020-01-02',
    '2020-01-01/..',
    '../2020-01-02',
    '2020-01-01T14/2020-01-02T18',
  ] as $datetime) {
    echo Yaml::dump(["interval($datetime)"=> interval($datetime)]);
  }
  die();
}

// fabrique une chaine avec les paramètres + le caractère '?' au début ssi il en existe au moins un
function showParams(array $params): string {
  if (!$params)
    return '';
  $url = '';
  foreach ($params as $key => $value)
    $url .= ($url ? '&' : '')."$key=".urlencode($value);
  return "?$url";
}

/* retourne l'enregistrement correspondant au path_info passé en paramètre
  le paramètre $ld définit, dans certains cas, si l'enregistrement doit être structuré en JSON/GéoJSON ou en JSON-LD
  si ok alors le résultat est un array avec
  - 1) un champ 'header' avec notamment un sous-champ 'Content-Type' avec le type MIME du résultat
  - 2) un champ 'body' avec l'enregistrement lui-même.
  En cas d'erreur retourne un array avec un champ 'error' contenant
  - 1) un champ 'httpCode' qui est un code d'erreur Http
  - 2) un champ 'message' qui est un message d'erreur sous la forme d'un texte
*/
function getRecord(string $path_info, bool $ld): array {
  //echo "<pre>getRecord($path_info)\n"; print_r($_GET); echo "</pre>\n";
  $baseUrl = ($_SERVER['HTTP_HOST']=='localhost') ?
      'http://localhost/yamldoc/pub/comhisto/ogcapi/ogcapi.php'
      : 'https://comhisto.geoapi.fr';
  if (!$path_info || ($path_info == '/')) { // landingPage
    if ($ld) {
      return [
        'header'=> ['Content-Type'=> 'application/ld+json'],
        'body'=> [
          "@context" => [
            "adms" => "http://www.w3.org/ns/adms#",
            "dcat" => "http://www.w3.org/ns/dcat#",
            "dcterms" => "http://purl.org/dc/terms/",
            "foaf" => "http://xmlns.com/foaf/0.1/",
            "rdf" => "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
            "rdfs" => "http://www.w3.org/2000/01/rdf-schema#",
            "schema" => "http://schema.org/",
            "vcard" => "http://www.w3.org/2006/vcard/ns#",
            "xsd" => "http://www.w3.org/2001/XMLSchema#",
          ],
          '@type'=> 'dcat:DataService', // description du servide OGC API Features
          "dcterms:title" => ["@language" => "fr","@value" => "web-service OGC API Features sur comhisto"],
          "dcterms:description" => [
            "@language" => "fr",
            "@value" => "Web-service OGC API Features fournissant l'accès en GeoJSON aux versions d'entités",
          ],
          "dcterms:license" => ["@id" => "https://www.etalab.gouv.fr/licence-ouverte-open-licence"],
          'dcat:servesDataset'=> ['@id'=> 'https://comhisto.georef.eu/'],
          'dcat:endpointURL'=> "$baseUrl/",
          'dct:conformsTo'=> [
            ['@id'=> 'http://spec.openapis.org/'], // quel URI pour définir OpenAPI ?
            ['@id'=> 'http://www.opengis.net/spec/ogcapi-features-1/1.0'],
          ],
          'dcat:endpointDescription'=> "$baseUrl/api",
        ],
      ];
    }
    else {
      return [
        'header'=> ['Content-Type'=> 'application/json'],
        'body'=> [
          'title'=> "API d'accès au référentiel communal historique simplifié (ComHisto)",
          'description' => "Accès aux entités de ComHisto via une API Web conforme au standard OGC API Features.\n"
            ."Cette version 0 est limitée à l'accès aux versions d'entités (vCom et vErat).\n"
            ."De plus, certaines fonctionnalités ne sont pas implémentées, notamment les paramètres property de items.",
          'links'=> [
            [ 'href'=> "$baseUrl/", 'rel'=> 'self', 'type'=> 'application/json', 'title'=> "Ce document"],
            [
              'href'=> "$baseUrl/api", 'rel'=> 'service-desc', 'type'=> 'application/vnd.oai.openapi+json;version=3.0',
              'title'=> "La définition de l'API",
            ],
            [
              'href'=> "https://app.swaggerhub.com/apis-docs/benoitdavidfr/comhistoogcapi", // la version par défaut
              'rel'=> 'service-doc', 'type'=> 'text/html',
              'title'=> "La documentation de l'API",
            ],
            [
              'href'=> "$baseUrl/conformance", 'rel'=> 'conformance', 'type'=> 'application/json',
              'title'=> "Classes de conformité de l'API OGC implémentées par ce serveur",
            ],
            [
              'href'=> "$baseUrl/collections", 'rel'=> 'data', 'type'=> 'application/json',
              'title'=> "Informations sur les collections d'objets",
            ],
          ],
        ],
      ];
    }
  }
  
  if ($path_info == '/api') {
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
        [
          "href" => "$baseUrl/schema/vEntite",
          "rel" => "describedBy",
          "type" => "application/json",
          "title" => "Schema JSON d'une FeatureCollection correspondant à cette collection",
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
        [
          "href" => "$baseUrl/schema/vEntite",
          "rel" => "describedBy",
          "type" => "application/json",
          "title" => "Schema JSON d'une FeatureCollection correspondant à cette collection",
        ],
      ],
    ],
  ];
  if ($path_info == '/collections') { // /collections
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
          ],
          "collections" => array_values($collections),
      ],
    ];
  }
  
  if (preg_match('!^/collections/([^/]*)?$!', $path_info, $matches)) { // /collections/{collId}
    $collectionId = $matches[1];
    if (!isset($collections[$collectionId]))
      return ['error'=> ['httpCode'=> 404, 'message'=> "Collection $collectionId inexistante"]];
    else
      return [ // le seul résultat généré est celui en JSON
        'header'=> ['Content-Type'=> 'application/json'],
        'body'=> $collections[$collectionId],
      ];
  }
  
  if (preg_match('!^/schema/([^/]*)$!', $path_info, $matches)) { // /schema/{schemaId}
    $schemaId = $matches[1];
    try {
      return [ // le seul résultat généré est celui en JSON
        'header'=> ['Content-Type'=> 'application/json'],
        'body'=> Yaml::parseFile(__DIR__."/schemas/$schemaId.yaml"),
      ];
    } catch (ParseException $e) {
      return ['error'=> ['httpCode'=> 404, 'message'=> "Schema $schemaId non défini"]];
    }
  }

  if (preg_match('!^/collections/([^/]*)/items$!', $path_info, $matches)) { // /collections/{collId}/items
    $collectionId = $matches[1];
    if (!isset($collections[$collectionId]))
      return ['error'=> ['httpCode'=> 404, 'message'=> "Collection $collectionId inexistante"]];
    $limit = $_GET['limit'] ?? 10;
    if (!is_numeric($limit) || ($limit > 1000) || ($limit <= 0))
      return ['error'=> ['httpCode'=> 400, 'message'=> "Paramètre limit=$limit incorrect"]];
    $startindex = $_GET['startindex'] ?? 0;
    if (!is_numeric($startindex) || ($startindex < 0))
      return ['error'=> ['httpCode'=> 400, 'message'=> "Paramètre startindex=$startindex incorrect"]];
    $whereSupplement = '';
    //echo "_GET = "; print_r($_GET);
    
    // gestion du paramètre bbox
    if ($bbox = isset($_GET['bbox']) ? explode(',', $_GET['bbox']) : []) { // si bbox est défini
      if (!checkBbox($bbox))
        return ['error'=> ['httpCode'=> 400, 'message'=> "Paramètre bbox=$_GET[bbox] incorrect"]];
      if (count($bbox) == 4)
        $whereSupplement .= " and ST_Intersects(geom, ST_MakeEnvelope(".implode(',',$bbox).", 4326))";
      else
        $whereSupplement .= " and ST_Intersects(geom, ST_MakeEnvelope($bbox[0], $bbox[1], $bbox[3], $bbox[4], 4326))";
    }
    
    // Gestion du paramètre datetime
    if ($interval = interval($_GET['datetime'] ?? '')) {
      if ($errorMessage = ($interval['error'] ?? null)) // $_GET['datetime'] n'est pas un intervalle
        return ['error'=> ['httpCode'=> 400, 'message'=> $errorMessage]];
      list($start, $end) = $interval;
      //echo "start=$start, end=",($end ? $end : 'undef'),"\n";
      if (!$end) { // date ponctuelle
        $whereSupplement .= " and ddebut <= '$start' and (dfin > '$start' or dfin is null)";
      }
      elseif (($start == '..') && ($end == '..'))
        $whereSupplement .= '';
      elseif ($start == '..')
        $whereSupplement .= " and ddebut < '$end'";
      elseif ($end == '..')
        $whereSupplement .= " and (dfin > '$start' or dfin is null)";
      else
        $whereSupplement .= " and ddebut < '$end' and (dfin > '$start' or dfin is null)";
    }
    
    // Gestion des propriétés
    $properties = ['id', 'cinsee', 'ddebut', 'edebut', 'dfin', 'efin', 'statut', 'crat', 'erats', 'elits', 'dnom'];
    $selectOnProp = [];
    foreach ($_GET as $key => $value) {
      if (in_array($key, $properties))
        $whereSupplement .= " and $key='$value'";
    }
    if (isset($_GET['properties']))
      $properties = explode(',', $_GET['properties']);
    if (!in_array('id', $properties))
      $properties = array_merge(['id'], $properties);
    //print_r($properties);
    $properties = implode(',',$properties);
    
    try {
      $t = ['vCom'=>'s','vErat'=>'r'][$collectionId] ?? 'ERROR';
      $sql = "select count(*) numbermatched from comhistog3 where type='$t' $whereSupplement";
      $numberMatched = intval(PgSql::getTuples($sql)[0]['numbermatched']);

      $sql = "select $properties, ST_AsGeoJSON(geom) geom
        from comhistog3 where type='$t' $whereSupplement
        limit $limit offset $startindex";

      $features = [];
      foreach (PgSql::getTuples($sql) as $tuple) {
        foreach (['geom','edebut','efin','erats','elits'] as $prop)
          if (isset($tuple[$prop]))
            $tuple[$prop] = json_decode($tuple[$prop], true);;
        $geom = $tuple['geom'];
        unset($tuple['geom']);
        $id = substr($tuple['id'], 1);
        unset($tuple['id']);
        $features[] = [
          'type'=> 'Feature',
          'id'=> ($ld ? "$baseUrl/collections/$collectionId/items/$id" : $id),
          'properties'=> $tuple,
          'geometry'=> $geom,
        ];
      }
      
    } catch (Exception $e) {
      echo "<pre>sql=$sql</pre>\n";
      echo $e->getMessage();
      throw new Exception($e->getMessage());
    }
    return [ // résultat généré en GéoJSON ou JSON-LD
      'header'=> ['Content-Type'=> $ld ? 'application/ld+json' : 'application/geo+json'],
      'body'=> 
        ($ld ? ['@context'=> 'https://geojson.org/geojson-ld/geojson-context.jsonld'] : [])
      +[
        //'sql'=> $sql,
        'timeStamp'=> str_replace('+00:00','Z', date(DATE_ATOM)),
        'numberReturned'=> count($features),
        'numberMatched'=> $numberMatched,
        'type'=> 'FeatureCollection',
        'features' => $features,
        'links'=> [
          [
            "type" => "application/geo+json",
            "rel" => "self",
            "title" => "This document as GeoJSON",
            "href" => "$baseUrl/collections/$collectionId/items".showParams(array_merge($_GET, ['f'=>'json'])),
          ],
          [
            "rel" => "alternate",
            "type" => "application/ld+json",
            "title" => "This document as RDF (JSON-LD)",
            "href" => "$baseUrl/collections/$collectionId/items".showParams(array_merge($_GET, ['f'=>'jsonld'])),
          ],
          [
            "type" => "text/html",
            "rel" => "alternate",
            "title" => "This document as HTML",
            "href" => "$baseUrl/collections/$collectionId/items".showParams(array_merge($_GET, ['f'=>'html'])),
          ],
          [
            "type" => "application/geo+json",
            "rel" => "next",
            "title" => "items (next)",
            "href" =>
              "$baseUrl/collections/$collectionId/items"
                .showParams(array_merge($_GET, ['startindex'=> ($startindex+$limit)])),
          ],
          [
            "type" => "application/json",
            "title" => $collections[$collectionId]['title'],
            "rel" => "collection",
            "href" => "$baseUrl/collections/$collectionId",
          ],
        ],
      ],
    ];
  }
  
  if (preg_match('!^/collections/([^/]*)/items/([^/]*)$!', $path_info, $matches)) { // /collections/{collId}/items/{fId}
    $collectionId = $matches[1];
    $type = ($collectionId == 'vErat') ? 'r' : 's';
    $fId = $matches[2];
    if (!isset($collections[$collectionId]))
      return ['error'=> ['httpCode'=> 404, 'message'=> "Collection $collectionId inexistante"]];
    $sql = "select id, cinsee, ddebut, edebut, dfin, efin, statut, crat, erats, elits, dnom, ST_AsGeoJSON(geom) geom
      from comhistog3 where id='$type$fId'";
    try {
      $tuples = PgSql::getTuples($sql);
    } catch (Exception $e) {
      echo "<pre>sql=$sql</pre>\n";
      echo $e->getMessage();
      throw new Exception($e->getMessage());
    }
    if (!$tuples)
      return ['error'=> ['httpCode'=> 404, 'message'=> "Item $fId inexistant"]];
    $tuple = $tuples[0];
    foreach (['geom','edebut','efin','erats','elits'] as $prop)
      $tuple[$prop] = json_decode($tuple[$prop], true);;
    $geom = $tuple['geom'];
    unset($tuple['geom']);
    $id = substr($tuple['id'], 1);
    unset($tuple['id']);
    return [ // résultat généré en GéoJSON ou JSON-LD
      'header'=> ['Content-Type'=> $ld ? 'application/ld+json' : 'application/geo+json'],
      'body'=> 
        ($ld ? ['@context'=> 'https://geojson.org/geojson-ld/geojson-context.jsonld'] : [])
      +[
        'type'=> 'Feature',
        'id'=> ($ld ? "$baseUrl/collections/$collectionId/items/$id" : $id),
        'properties'=> $tuple,
        'geometry'=> $geom,
      ],
    ];
  }
  
  return ['error'=> ['httpCode'=> 400, 'message'=> "Erreur requête non interprétée pour $path_info"]];
}


define('JSON_ENCODE_OPTIONS', JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

write_log(true); // écriture d'un log dans une base décrite dans ../lib/secretconfig.inc.php

//echo "<pre>"; print_r($_SERVER); die();
// $format déduit de HTTP_ACCEPT
$http_accept = explode(',', $_SERVER['HTTP_ACCEPT'] ?? '');
if (in_array('text/html', $http_accept))
  $format = 'html';
elseif (in_array('application/ld+json', $http_accept))
  $format = 'jsonld';
else
  $format = 'json';

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
//$record['format'] = $format;
//print_r($record);

if ($format=='html') { // sortie Html
  echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>ogcapi</title></head><body>\n";
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
