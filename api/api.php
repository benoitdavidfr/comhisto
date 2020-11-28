<?php
/*PhpDoc:
name: api.php
title: api/api.php - API d'accès à ComHisto
doc: |
  L'objet de ce script est double:
    1) opérationaliser la définition d'URI pour les objets ComHisto
    2) mettre en oeuvre le cas d'usage de récupération de la géométrie de la commune/ERAT correspondant à un code Insee
       donné et à une date donnée.
  La liste des motifs d'URI et d'URL est définie dans phpdoc.yaml

  Ce script est normalement exécuté directement en mode non CLI.
  Il peut aussi être utilisé en mode CLI pour effectuer des vérifications sur tous les objets existants.

journal: |
  23/11/2020:
    - amélioration de la description LD des comhisto:(COM|ERAT)
  20-22/11/2020:
    - déclaration DCAT du Dataset en JSON-LD à la racine 
    - évolutions de la diffusion LD
  18/11/2020:
    - fusion map.inc.php avec ../map/index.php
    - ajustements
  16-17/11/2020:
    - ajout du traitement du format de sortie (en cours)
      - le format de sortie peut être défini dans le paramètre HTTP Accept et dans une extension de l'URL
      - cette seconde possibilité prend le pas sur la première
    - mise en oeuvre des cartes sur comhisto.georef.eu
    - -->> intégrer le JSON-LD dans le Html
  14-15/11/2020:
    - remplacement des codes Insee par des URI
    - ajout du contexte et définition d'URI pour les statuts
    - rendre les résultats conformes à JSON-LD ???
  13/11/2020:
    - création
*/
require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../../../../phplib/pgsql.inc.php';
require_once __DIR__.'/../map/openpg.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

define('JSON_ENCODE_OPTIONS', JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

function showTrace(array $traces) {
  echo "<pre><h3>Trace de l'exception</h3>\n";
  echo Yaml::dump($traces);
}

// si $field='dfin' alors retourne l'URI de la précédente version correspondant à cinsee avant ddebut
// si $field='ddebut' alors retourne l'URI de l'objet correspondant à cinsee débutant à $ddebut
// de préférence de type défini par type
// Si l'objet n'existe pas retourne null
function makeUri(string $cinsee, string $field, string $ddebut, string $type=''): ?string {
  $sql = "select type, ddebut from comhistog3 where cinsee='$cinsee' and $field='$ddebut'";
  if (!($tuples = PgSql::getTuples($sql))) {
    //throw new Exception("comhistog3 non trouvé pour cinsee=$cinsee et $field=$ddebut");
    //echo "comhistog3 non trouvé pour cinsee=$cinsee et $field=$ddebut<br>\n";
    return null;
  }
  //echo '$tuples = '; print_r($tuples);
  //$baseUri = 'https://comhisto.georef.eu' | 'http://localhost/yamldoc/pub/comhisto/api/api.php'
  $baseUri = ($_SERVER['SERVER_NAME']=='localhost') ?
    "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]"
      : "https://$_SERVER[SERVER_NAME]";
  if ((count($tuples) == 1) || !$type) {
    $tuple = $tuples[0];
    $type = ($tuple['type']=='s') ? 'COM' : 'ERAT';
    return "$baseUri/$type/$cinsee/$tuple[ddebut]";
  }
  else {
    foreach ($tuples as $tuple) {
      if ($tuple['type'] == $type) {
        $type = ($tuple['type']=='s') ? 'COM' : 'ERAT';
        return "$baseUri/$type/$cinsee/$tuple[ddebut]";
      }
    }
  }
  echo "<pre>"; print_r($tuples);
}

// complète un ens. d'evts en remplacant les codes Insee par des Uri
function completeUriEvt(array &$evts, string $ddebut, string $cinsee): void {
  foreach ($evts as $verb => &$params) {
    //echo "completeUriEvt($cinsee@$ddebut, $verb)<br>\n";
    switch ($verb) {
      case 'entreDansLeRéférentiel': break;
      case 'sortDuPérimètreDuRéférentiel': break;
      case 'changeDeNomPour': break;
      case 'aucun': break;
      
      case 'avaitPourCode': { // Il faut prendre l'URI de la version précédente
        $params = makeUri($params, 'dfin', $ddebut, 's');
        break;
      }
      
      case 'changeDeCodePour': {
        $params = makeUri($params, 'ddebut', $ddebut, 's');
        break;
      }
      
      case 'absorbe': { // Il faut prendre l'URI de la version précédente
        foreach ($params as $i => $param) {
          $params[$i] = makeUri($param, 'dfin', $ddebut, 'r');
        }
        break;
      }
      
      case 'associe':
      case 'prendPourDéléguées':
      case 'gardeCommeRattachées':
      case 'détacheCommeSimples':
      case 'estModifiéeIndirectementPar': {
        foreach ($params as $i => $param) {
          $params[$i] = makeUri($param, 'ddebut', $ddebut, 'r');
        }
        break;
      }
      
      case 'seScindePourCréer': {
        foreach ($params as $i => $param) {
          $params[$i] = makeUri($param, 'ddebut', $ddebut);
        }
        break;
      }

      case 'fusionneDans': { // Il faut prendre l'URI de la version précédente
        $params = makeUri($params, 'dfin', $ddebut, 's');
        break;
      }
      
      case 'sAssocieA':
      case 'devientDéléguéeDe':
      case 'resteRattachéeA':
      case 'crééeCOMParScissionDe':
      case 'crééeCOMAParScissionDe':
      case 'crééARMParScissionDe':
      case 'seDétacheDe': {
        $params = makeUri($params, 'ddebut', $ddebut, 's');
        break;
      }
      
      default: {
        throw new Exception("verbe $verb non traité dans completeUriEvt()");
      }
    }
  }
}

// complète les URI pour un n-uplet, retourne geom
function completeUriTuple(array &$tuple, string $cinsee): array {
  //print_r($tuple);
  //$baseUri = 'https://comhisto.georef.eu' | 'http://localhost/yamldoc/pub/comhisto/api/api.php'
  $baseUri = ($_SERVER['SERVER_NAME']=='localhost') ?
    "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]"
      : "https://$_SERVER[SERVER_NAME]";
  foreach (['edebut','efin','erats','elits','geom'] as $prop)
    if (isset($tuple[$prop]))
      $tuple[$prop] = json_decode($tuple[$prop], true);
  if (isset($tuple['edebut']))
    completeUriEvt($tuple['edebut'], $tuple['ddebut'], $cinsee);
  if (isset($tuple['efin'])) 
    completeUriEvt($tuple['efin'], $tuple['dfin'], $cinsee);
  if (isset($tuple['crat']))
    $tuple['crat'] = "$baseUri/COM/$tuple[crat]/$tuple[ddebut]";
  foreach ($tuple['erats'] ?? [] as $i => $erat)
    $tuple['erats'][$i] = "$baseUri/ERAT/$erat/$tuple[ddebut]";
  foreach ($tuple['elits'] ?? [] as $i => $elit)
    $tuple['elits'][$i] = "$baseUri/elits2020/$elit";
  foreach ($tuple['elitsnd'] ?? [] as $i => $elit)
    $tuple['elitsnd'][$i] = "$baseUri/elits2020/$elit";
  $geom = $tuple['geom'] ?? [];
  unset($tuple['geom']);
  if ($geom && ($geom['type'] == 'MultiPolygon') && (count($geom['coordinates']) == 1))
    $geom = ['type'=> 'Polygon', 'coordinates'=> $geom['coordinates'][0]];
  return $geom;
}

//die("api.php ligne ".__LINE__."\n");
if (php_sapi_name() == 'cli') { // Vérifie systématiquement completeUriTuple()
  $sql = "select cinsee, ddebut, edebut, dfin, efin, statut, crat, erats, elits, dnom
          from comhistog3
          order by cinsee, ddebut";
  foreach (PgSql::getTuples($sql) as $tuple) {
    try {
      completeUriTuple($tuple, $tuple['cinsee']);
      //echo Yaml::dump(["$tuple[cinsee]@$tuple[ddebut]" => $tuple], 3, 2);
    }
    catch (Exception $e) {
      echo $e->getMessage();
      showTrace($e->getTrace());
    }
  }
  die();
}

// prend une structure GeoJSON:geometry et rend un https://schema.org/GeoShape ou un https://schema.org/GeoCoordinates
function geoShape(array $geom): array {
  $geoShape = '';
  $bbox = []; // [latMin, lonMin, latMax, lonMax];
  if ($geom['type']=='Polygon') {
    foreach ($geom['coordinates'][0] as $pt) { // je ne prend que l'extérieur
      $geoShape .= "$pt[1],$pt[0] ";
      $bbox = !$bbox ? [$pt[1], $pt[0], $pt[1], $pt[0]]
        : [min($pt[1], $bbox[0]), min($pt[0], $bbox[1]), max($pt[1], $bbox[2]), max($pt[0], $bbox[3])];
    }
    return ['@type'=> 'GeoShape', 'box'=> implode(',',$bbox), 'polygon'=> $geoShape];
  }
  elseif ($geom['type']=='MultiPolygon') {
    foreach ($geom['coordinates'] as $polygon) {
      foreach ($polygon[0] as $pt) {
        $geoShape .= "$pt[1],$pt[0] ";
        $bbox = !$bbox ? [$pt[1], $pt[0], $pt[1], $pt[0]]
          : [min($pt[1], $bbox[0]), min($pt[0], $bbox[1]), max($pt[1], $bbox[2]), max($pt[0], $bbox[3])];
      }
    }
    return ['@type'=> 'GeoShape', 'box'=> implode(',',$bbox), 'polygon'=> $geoShape];
  }
  elseif ($geom['type']=='LineString') {
    foreach ($geom['coordinates'] as $pt) {
      $geoShape .= "$pt[1],$pt[0] ";
      $bbox = !$bbox ? [$pt[1], $pt[0], $pt[1], $pt[0]]
        : [min($pt[1], $bbox[0]), min($pt[0], $bbox[1]), max($pt[1], $bbox[2]), max($pt[0], $bbox[3])];
    }
    return ['@type'=> 'GeoShape', 'box'=> implode(',',$bbox), 'line'=> $geoShape];
  }
  elseif ($geom['type']=='MultiLineString') {
    foreach ($geom['coordinates'] as $linestring) {
      foreach ($linestring as $pt) {
        $geoShape .= "$pt[1],$pt[0] ";
        $bbox = !$bbox ? [$pt[1], $pt[0], $pt[1], $pt[0]]
          : [min($pt[1], $bbox[0]), min($pt[0], $bbox[1]), max($pt[1], $bbox[2]), max($pt[0], $bbox[3])];
      }
    }
    return ['@type'=> 'GeoShape', 'box'=> implode(',',$bbox), 'line'=> $geoShape];
  }
  elseif ($geom['type']=='Point') {
    foreach ($geom['coordinates'] as $pt) {
      $geoShape .= "$pt[1],$pt[0] ";
      $bbox = !$bbox ? [$pt[1], $pt[0], $pt[1], $pt[0]]
        : [min($pt[1], $bbox[0]), min($pt[0], $bbox[1]), max($pt[1], $bbox[2]), max($pt[0], $bbox[3])];
    }
    return ['@type'=> 'GeoShape', 'box'=> implode(',',$bbox), 'line'=> $geoShape];
  }
  else
    throw new Exception("geoshape non défini");
}

// construit en fonction du tuple issu de la base et de $ld soit le Feature GeoJSON soit la https://schema.org/City
function buildFeatureOrCity(array $tuple, string $type, string $cinsee, ?string $outputFormat, bool $ld): array {
  $geom = completeUriTuple($tuple, $cinsee);
  $replaces = isset($tuple['edebut']['avaitPourCode']) ?
    $tuple['edebut']['avaitPourCode']
      : makeUri($cinsee, 'dfin', $tuple['ddebut'], '');
  $isReplacedBy = isset($tuple['efin']['changeDeCodePour']) ?
    $tuple['efin']['changeDeCodePour']
      : ($tuple['dfin'] ? makeUri($cinsee, 'ddebut', $tuple['dfin'], '') : null);
  if (($outputFormat=='json') || !$ld) { // structuration GéoJSON
    return [
      'header'=> ['Content-Type'=> 'application/geo+json'],
      'body'=> [
        'type'=> 'Feature',
        'id'=> "https://comhisto.georef.eu/$type/$cinsee/$tuple[ddebut]",
        'properties'=> [
          'created'=> $tuple['ddebut'],
          'startEvent'=> $tuple['edebut'],
          'deleted'=> $tuple['dfin'],
          'endEvent'=> $tuple['efin'],
          'replaces' => $replaces,
          'isReplacedBy'=> $isReplacedBy,
        ]
        + ['status'=> "https://comhisto.georef.eu/status/$tuple[statut]"]
        + (isset($tuple['crat']) ? ['crat'=> $tuple['crat']] : [])
        + (isset($tuple['erats']) ? ['erats'=> $tuple['erats']] : [])
        + ['elits'=> $tuple['elits']]
        + ['name'=> $tuple['dnom']],
        'geometry'=> $geom,
      ],
    ]
    + ($outputFormat ? ['outputFormat'=> $outputFormat] : []);
  }
  else { // structuration JSON-LD utilisant les contextes schema, dcterms et comhisto et typé schema:City
    $id = (($type=='COM') ? 's' : 'r').$cinsee;
    return [
      'header'=> ['Content-Type'=> 'application/ld+json'],
      'body'=> [
        '@context'=> [
          'schema'=> 'https://schema.org/',
          'dcterms' => 'http://purl.org/dc/terms/',
          'comhisto' => 'https://comhisto.georef.eu/',
        ],
        '@type'=> 'schema:City',
        '@id'=> "https://comhisto.georef.eu/$type/$cinsee/$tuple[ddebut]",
        'schema:name'=> $tuple['dnom'],
        'schema:temporalCoverage'=> $tuple['ddebut'].'/'.($tuple['dfin'] ?? '..'),
        'comhisto:startEvent'=> ['@type'=> 'comhisto:Event'] + $tuple['edebut'],
        'comhisto:endEvent'=> $tuple['efin'] ? ['@type'=> 'comhisto:Event'] + $tuple['efin'] : null,
        'dcterms:replaces' => $replaces,
        'dcterms:isReplacedBy'=> $isReplacedBy,
      ]
      + ['comhisto:status'=> "https://comhisto.georef.eu/status/$tuple[statut]"]
      + (isset($tuple['crat']) ? ['schema:geoWithin'=> $tuple['crat']] : [])
      + (isset($tuple['erats']) ? ['schema:geoContains'=> $tuple['erats']] : [])
      + ['comhisto:elits'=> $tuple['elits']]
      + ['schema:geo'=> geoShape($geom)]
      + ['schema:hasMap'=> "https://comhisto.georef.eu/map/$id/$tuple[ddebut]"],
    ]
    + ($outputFormat ? ['outputFormat'=> $outputFormat] : []);
  }
}

// retourne l'enregistrement correspondant au path_info passé en paramètre
// le paramètre $ld définit, dans certains cas, si l'enregistrement doit être structuré en JSON/GéoJSON ou en JSON-LD
// si ok alors le résultat est un array avec
// - 1) un champ 'header' avec notamment un sous-champ 'Content-Type' avec le type MIME du rasultat
// - 2) un champ 'body' avec l'enregistrement lui-même.
// - 3) Si le path_info impose un format de sortie alors il est retourné dans le champ 'outputFormat'
// En cas d'erreur retourne un array avec un champ 'error' contenant
// - 1) un champ 'httpCode' qui est un code d'erreur Http
// - 2) un champ 'message' qui est un message d'erreur sous la forme d'un texte
function getRecord(string $path_info, bool $ld): array {
  //echo "json(path_info=$path_info, ld=",$ld?'true':'false',")<br>\n";
  if (!$path_info || ($path_info == '/')) { // la racine correspond à la déclaration DCAT du Dataset en JSON-LD
    // inspiré de https://github.com/SEMICeu/dcat-ap_validator/blob/master/pages/samples/sample-json-ld.jsonld
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
        "@id" => "https://comhisto.georef.eu/",
        "@type" => "dcat:Dataset",
        "dcterms:title" => ["@language" => "fr","@value" => "Référentiel communal historique simplifié (ComHisto)"],
        "dcterms:description" => [
          "@value"=> "Ce jeu de données contient l'historique du découpage des communes en France depuis 1943 avec la localisation géographique de chaque version. Voir documentation sur https://github.com/benoitdavidfr/comhisto",
          "@language" => "fr",
        ],
        "adms:contactPoint" => [
          "@type" => "vcard:VCard",
          "vcard:fn" => "Benoit David",
          "vcard:hasEmail" => [
            "@id" => "mailto:benoit.david@free.fr",
          ],
        ],
        "dcat:distribution" => [
          [
            "@type" => "dcat:Distribution", // description en JSON-LD du téléchargement GéoJSON
            "dcterms:title" => ["@language" => "fr","@value" => "Téléchargement GeoJSON"],
            "dcat:accessURL" => [
              "@id" => "https://static.data.gouv.fr/resources/code-officiel-geographique-cog/20200920-175314/comhistog3.geojson",
            ],
            "dcterms:description" => [
              "@language" => "fr",
              "@value" => "Fichier des versions d'entités téléchargeable en GeoJSON. Voir https://github.com/benoitdavidfr/comhisto/blob/master/export/README.md",
            ],
            "dcterms:format" => 'application/geo+json', // GeoJSON
            "dcat:mediaType" => "https://www.iana.org/assignments/media-types/application/geo+json",
            "dcterms:license" => ["@id" => "https://www.etalab.gouv.fr/licence-ouverte-open-licence"],
          ],
          [
            "@type" => "dcat:Distribution", // description du servide OGC API Features
            "dcterms:title" => ["@language" => "fr","@value" => "web-service OGC API Features"],
            "dcterms:description" => [
              "@language" => "fr",
              "@value" => "Les versions d'entités sont accesibles en GeoJSON par ce web-service OGC API Features",
            ],
            "dcterms:license" => ["@id" => "https://www.etalab.gouv.fr/licence-ouverte-open-licence"],
            'dcat:accessService'=> [
              '@type'=> 'Dcat:DataService',
              '@id'=> 'https://comhisto.geoapi.fr/',
            ],
          ],
        ],
        "dcat:keyword" => ["Historique","Découpage des communes","Découpage administratif","France","ComHisto"],
        "dcat:landingPage" => ["@id" => "https://github.com/benoitdavidfr/comhisto"],
        "dcat:theme" => ["@id" => "http://eurovoc.europa.eu/362"], // découpage administratif
        "dcterms:accrualPeriodicity" => ["@id" => "http://purl.org/cld/freq/monthly"],
        "dcterms:conformsTo" => ["@id" => "https://github.com/benoitdavidfr/comhisto"],
        "dcterms:issued" => "2020-11-20",
        "dcterms:language" => ["@id" => "http://publications.europa.eu/resource/authority/language/FRA"],
        "dcterms:modified" => "2020-11-28",
        "dcterms:publisher" => [
          "@type" => "vcard:VCard",
          "vcard:fn" => "Benoit David",
          "vcard:hasEmail" => [
            "@id" => "mailto:contact@georef.eu",
          ],
        ],
        "dcterms:spatial" => ["@id" => "http://sws.geonames.org/3017382"], // France
        "dcterms:temporal" => [
          "@type" => "dcterms:PeriodOfTime",
          "schema:endDate" => ["@type" => "xsd:date","@value" => "2020-01-01"],
          "schema:startDate" => ["@type" => "xsd:date","@value" => "1943-01-01"],
        ],
        "dcat:spatialResolutionInMeters" => ["@value"=> "100.0","@type"=> "xsd:decimal"],
      ],
    ];
  }

  if ($path_info == '/test') { // pour effectuer les tests 
    $baseUrl = "http://$_SERVER[SERVER_NAME]".(($_SERVER['SERVER_NAME']=='localhost') ? "$_SERVER[SCRIPT_NAME]" : '');
    return [
      'header'=> ['Content-Type'=> 'application/json'],
      'body'=> [
        'examples'=> "$baseUrl/examples",
        '_SERVER'=> $_SERVER,
      ],
    ];
  }
  
  if ($path_info == '/examples') { // exemples pour effectuer les tests 
    $baseUrl = ($_SERVER['SERVER_NAME']=='localhost') ?
        "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]"
        : "https://$_SERVER[SERVER_NAME]";
    $examples = [
      'dcat:dataset'=> $baseUrl,
      '/ERAT/01015'=> "$baseUrl/ERAT/01015",
      '/COM/01015/2016-01-01'=> "$baseUrl/COM/01015/2016-01-01",
      '/ERAT/01015/2016-01-01'=> "$baseUrl/ERAT/01015/2016-01-01",
      '/ERAT/01015/2019-01-01 Erreur'=> "$baseUrl/ERAT/01015/2019-01-01",
      '/ERAT/01015?date=2019-01-01'=> "$baseUrl/ERAT/01015?date=2019-01-01",
      '/elits2020/01015'=> "$baseUrl/elits2020/01015",
      '/codeInsee/01015'=> "$baseUrl/codeInsee/01015",
      '/codeInsee/01015/2016-01-01 ok'=> "$baseUrl/codeInsee/01015/2016-01-01",
      '/codeInsee/01015/2019-01-01 KO'=> "$baseUrl/codeInsee/01015/2019-01-01",
      '/codeInsee/01015/2019-01-01?date=2019-01-01'=> "$baseUrl/codeInsee/01015?date=2019-01-01",
      '/codeInsee/44180'=> "$baseUrl/codeInsee/44180",
      '/COM = liste des COM valides'=> "$baseUrl/COM",
      '/contexts/skos = contexte skos'=> "$baseUrl/contexts/skos",
      '/status = liste des statuts'=> "$baseUrl/status",
      '/status/COM'=> "$baseUrl/status/COM",
    ];
    //if (in_array('application/json', $accept))
      return [
        'header'=> [
          'Content-Type'=> 'application/json',
        ],
        'body'=> [
          'examples'=> $examples,
        ]
      ];
    /*else {
      $html = "<ul>\n";
      foreach ($examples as $key => $value) {
        $html .= "<li><a href='$value'>$key</a></li>\n";
      }
      $html .= "</ul>\n";
      return [
        'header'=> [
          'Content-Type'=> 'text/html',
        ],
        'body'=> $html,
      ];
    }*/
  }

  if (preg_match('!^/contexts/([^/]*)!', $path_info, $matches)) { // définition de contexts 
    define('CONTEXTS', // Liste des contextes
    [
      'skos' => [
        'dcterms' => 'http://purl.org/dc/terms/',
        'dc' => 'http://purl.org/dc/elements/1.1/',
        'skos' => 'http://www.w3.org/2004/02/skos/core#',
        'comhisto' => 'https://comhisto.georef.eu/',
        'skos:broader' => ['@type'=> '@id'],
        'skos:inScheme' => ['@type'=> '@id'],
        'skos:related' => ['@type'=> '@id'],
        'skos:narrower' => ['@type'=> '@id'],
        'skos:hasTopConcept' => ['@type'=> '@id'],
        'skos:topConceptOf' => ['@type'=> '@id'],
      ],
      /*'rdf' => [],*/
    ]
    );
    $contextId = $matches[1];
    if (!isset(CONTEXTS[$contextId]))
      return ['error'=> ['httpCode'=> 404, 'message'=> "Erreur contexte $contextId inexistant"]];
    else
      return ['header'=> ['Content-Type'=> 'application/json'], 'body'=> CONTEXTS[$contextId]];
  }

  if ($path_info == '/status') { // Déclaration du thésaurus des statuts des entités de ComHisto
    return [
      'header'=> [
        'Content-Type'=> 'application/ld+json',
      ],
      'body'=> [
        '@context' => 'https://comhisto.georef.eu/contexts/skos',
        '@id' => 'comhisto:status',
        'skos:prefLabel' => [
          'fr' => "Thésaurus des statuts des entités de ComHisto",
        ],
        'skos:hasTopConcept'=> [
          'comhisto:status/COM',
          'comhisto:status/ERAT',
        ],
      ],
    ];
  }

  if (preg_match('!^/status/([^/]*)!', $path_info, $matches)) { // valeur des statuts comme concept Skos 
    define('STATUS_CONCEPTS', // Définition des statuts des entités de ComHisto
    [
      'COM' => [
        '@context' => 'https://comhisto.georef.eu/contexts/skos',
        '@id' => 'comhisto:status/COM',
        '@type' => 'skos:Concept',
        'skos:prefLabel'=> [
          '@language' => 'fr',
          '@value' => "Commune"
        ],
        'skos:inScheme' => 'comhisto:status',
        'skos:topConceptOf' => 'comhisto:status',
        'skos:narrower' => [
          'comhisto:status/BASE',
          'comhisto:status/ASSO',
          'comhisto:status/NOUV',
          'comhisto:status/CARM',
        ],
      ],
      'BASE' => [
        '@context' => 'https://comhisto.georef.eu/contexts/skos',
        '@id' => 'comhisto:status/BASE',
        '@type' => 'skos:Concept',
        'skos:prefLabel'=> [
          '@language' => 'fr',
          '@value' => "commune de base, cad ni associée, ni déléguée et n'ayant aucune entité rattachée",
        ],
        'skos:inScheme' => 'comhisto:status',
        'skos:broader' => [
          'comhisto:status/COM',
        ],
      ],
      'ASSO' => [
        '@context' => 'https://comhisto.georef.eu/contexts/skos',
        '@id' => 'comhisto:status/ASSO',
        '@type' => 'skos:Concept',
        'skos:prefLabel'=> [
          '@language' => 'fr',
          '@value' => "Commune ayant des communes associées (association)",
        ],
        'skos:inScheme' => 'comhisto:status',
        'skos:broader' => [
          'comhisto:status/COM',
        ],
      ],
      'NOUV' => [
        '@context' => 'https://comhisto.georef.eu/contexts/skos',
        '@id' => 'comhisto:status/NOUV',
        '@type' => 'skos:Concept',
        'skos:prefLabel'=> [
          '@language' => 'fr',
          '@value' => "Commune ayant des communes déléguées (commune nouvelle)",
        ],
        'skos:inScheme' => 'comhisto:status',
        'skos:broader' => [
          'comhisto:status/COM',
        ],
      ],
      'CARM' => [
        '@context' => 'https://comhisto.georef.eu/contexts/skos',
        '@id' => 'comhisto:status/CARM',
        '@type' => 'skos:Concept',
        'skos:prefLabel'=> [
          '@language' => 'fr',
          '@value' => "Commune ayant des arrondissements municipaux",
        ],
        'skos:inScheme' => 'comhisto:status',
        'skos:broader' => [
          'comhisto:status/COM',
        ],
      ],
      'ERAT' => [
        '@context' => 'https://comhisto.georef.eu/contexts/skos',
        '@id' => 'comhisto:status/ERAT',
        '@type' => 'skos:Concept',
        'skos:prefLabel'=> [
          '@language' => 'fr',
          '@value' => "Entité rattachée"
        ],
        'skos:inScheme' => 'comhisto:status',
        'skos:topConceptOf' => 'comhisto:status',
        'skos:narrower' => [
          'comhisto:status/COMA',
          'comhisto:status/COMD',
          'comhisto:status/ARM',
        ],
      ],
      'COMA' => [
        '@context' => 'https://comhisto.georef.eu/contexts/skos',
        '@id' => 'comhisto:status/COMA',
        '@type' => 'skos:Concept',
        'skos:prefLabel'=> [
          '@language' => 'fr',
          '@value' => "Commune associée",
        ],
        'skos:inScheme' => 'comhisto:status',
        'skos:broader' => [
          'comhisto:status/ERAT',
        ],
      ],
      'COMD' => [
        '@context' => 'https://comhisto.georef.eu/contexts/skos',
        '@id' => 'comhisto:status/COMD',
        '@type' => 'skos:Concept',
        'skos:prefLabel'=> [
          '@language' => 'fr',
          '@value' => "Commune déléguée",
        ],
        'skos:inScheme' => 'comhisto:status',
        'skos:broader' => [
          'comhisto:status/ERAT',
        ],
      ],
      'ARM' => [
        '@context' => 'https://comhisto.georef.eu/contexts/skos',
        '@id' => 'comhisto:status/ARM',
        '@type' => 'skos:Concept',
        'skos:prefLabel'=> [
          '@language' => 'fr',
          '@value' => "Arrondissement municipal",
        ],
        'skos:inScheme' => 'comhisto:status',
        'skos:broader' => [
          'comhisto:status/ERAT',
        ],
      ],
    ]
    );
    $statusId = $matches[1];
    if ($statusDef = (STATUS_CONCEPTS[$statusId] ?? null))
      return [
        'header'=> ['Content-Type'=> 'application/ld+json'],
        'body'=> $statusDef,
      ];
    else
      return ['error'=> ['httpCode'=> 404, 'message'=> "Erreur statut $statusId inexistant"]];
  }

  if (preg_match('!^/(COM|ERAT)$!', $path_info, $matches)) { // /(COM|ERAT) -> liste des COM|ERAT valides 
    $type = $matches[1];
    $t = ($type=='COM') ? 's': 'r';
    $sql = "select cinsee id, ddebut, dnom
            from comhistog3
            where type='$t' and dfin is null";
    try {
      foreach (PgSql::getTuples($sql) as &$tuple) {
        completeUriTuple($tuple, $tuple['id']);
        $tuples[] = [
          '@type'=> 'schema:City',
          '@id'=> "https://comhisto.georef.eu/$type/$tuple[id]/$tuple[ddebut]",
          'schema:name'=> $tuple['dnom'],
        ];
      }
    }
    catch (Exception $e) {
      echo "<pre>sql=$sql</pre>\n";
      echo $e->getMessage();
      showTrace($e->getTrace());
      throw new Exception($e->getMessage());
    }
    return [
      'header'=> ['Content-Type'=> 'application/ld+json'],
      'body'=> [
        '@context' => [
          "rdf" => "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
          "dcterms" => "http://purl.org/dc/terms/",
          "schema" => "https://schema.org/",
        ],
        '@id'=> "https://comhisto.georef.eu/$type",
        'dcterms:hasPart'=> [
          'rdf:Bag'=> $tuples,
        ],
      ],
    ];
  }

  if (preg_match('!^/map/((s|r|)\d[\dAB]\d\d\d)(/(\d{4,4}-\d\d-\d\d))?$!', $path_info, $matches)) { // /map/{id}/{ddebut}?
    $id = $matches[1];
    $ddebut = $matches[4] ?? null;
    $path_info = pathInfoFromId($ddebut ? "$id@$ddebut" : $id);
    return [
      'header'=> ['Content-Type'=> 'application/ld+json'],
      'body'=> [
        '@context'=> [
          'schema'=> 'https://schema.org/',
          'dcterms' => 'http://purl.org/dc/terms/',
          'comhisto' => 'https://comhisto.georef.eu/',
        ],
        '@type'=> 'schema:Map',
        '@id'=> "https://comhisto.georef.eu/map/$id".($ddebut ? "/$ddebut" : ''),
        'mapType'=> ['@id'=> 'https://schema.org/VenueMap'],
        'mainEntity'=> "https://comhisto.georef.eu$path_info",
        'url'=> "https://comhisto.georef.eu/map/$id".($ddebut ? "/$ddebut" : ''),
      ],
    ];
  }

  // Sinon cas général
  // ! /(COM|ERAT|elits2020|codeInsee)/{cinsee}(/{ddebut})?
  elseif (!preg_match('!^/(COM|ERAT|codeInsee|elits2020)/(\d[\dAB]\d\d\d)(/(\d{4,4}-\d\d-\d\d))?(\.(json))?$!',
       $path_info, $matches))
    return ['error'=> ['httpCode'=> 400, 'message'=> "Erreur $path_info non reconnu"]];

  $type = $matches[1];
  $cinsee = $matches[2];
  $ddebut = $matches[4] ?? null;
  $outputFormat = $matches[6] ?? null; // si le format de sortie est défini dans l'URL alors il s'impose
  //echo "type=$type, cinsee=$cinsee, ddebut=$ddebut<br>\n";
  

  // https://comhisto.georef.eu/(COM|ERAT)/{cinsee}/{ddebut} -> URI de la version de COM/ERAT comhisto
  //   retourne le Feature GeoJSON si elle existe, sinon Erreur 404
  // https://comhisto.georef.eu/(COM|ERAT)/{cinsee} -> URI de la version valide COM/ERAT comhisto
  //   comme Feature GeoJSON si elle existe, sinon Erreur 404
  // https://comhisto.geoapi.fr/(COM|ERAT)/{cinsee}?date={date}
  //   -> accès à la version correspondant à cette date comme Feature GeoJSON
  if ($cinsee && in_array($type, ['COM','ERAT'])) {
    //echo "https://comhisto.georef.eu/$type/$cinsee/$ddebut<br>\n";
    $t = ($type=='COM') ? 's': 'r';
    $sql = "select ddebut, edebut, dfin, efin, statut, ".(($type=='COM') ? 'erats' : 'crat').",
              elits, dnom, ST_AsGeoJSON(geom) geom
            from comhistog3
            where ";
    if ($ddebut) { // https://comhisto.georef.eu/(COM|ERAT)/{cinsee}/{ddebut} -> URI de la COM/ERAT
      $sql .= "id='$t$cinsee@$ddebut'";
    }
    elseif (!isset($_GET['date'])) { // https://comhisto.georef.eu/(COM|ERAT)/{cinsee} -> URI de la COM/ERAT valide
      $sql .= "type='$t' and cinsee='$cinsee' and dfin is null";
    }
    else { // https://comhisto.geoapi.fr/(COM|ERAT)/{cinsee}?date={date}
      $sql .= "type='$t' and cinsee='$cinsee' and (ddebut <= '$_GET[date]' and (dfin > '$_GET[date]' or dfin is null))";
    }
  
    if (!($tuples = PgSql::getTuples($sql))) {
      //echo "<pre>sql=$sql\n";
      $id = $ddebut ?
        "id $type$cinsee@$ddebut"
        : (isset($_GET['date']) ? "$type$cinsee/date=$_GET[date]" : "$type$cinsee");
      return ['error'=> ['httpCode'=> 404, 'message'=> "$id not found in comhistog3"]];
    }
    $tuple = $tuples[0];
    //print_r($tuple);
    return buildFeatureOrCity($tuple, $type, $cinsee, $outputFormat, $ld);
  }

  // /codeInsee/{cinsee} -> URI du code Insee, retourne la liste des versions utilisant ce code
  if (($type == 'codeInsee') && !$ddebut && !isset($_GET['date'])) {
    //echo "/codeInsee/{cinsee}<br>\n";
    $sql = "select id, ddebut, edebut, dfin, efin, statut, crat, erats, elits, dnom from comhistog3
      where cinsee='$cinsee'";
    try {
      if (!($tuples = PgSql::getTuples($sql)))
        return ['error'=> ['httpCode'=> 404, 'message'=> "cinsee $cinsee not found in comhistog3"]];
    }
    catch (Exception $e) {
      echo "<pre>sql=$sql\n";
      echo $e->getMessage();
      throw new Exception($e->getMessage());
    }

    foreach ($tuples as &$tuple) {
      $geom = completeUriTuple($tuple, $cinsee);
      $type = in_array($tuple['statut'], ['COMA','COMD','ARM']) ? 'ERAT' : 'COM'; 
      $tuple = [
        '@type'=> 'schema:City',
        '@id'=> "https://comhisto.georef.eu/$type/$cinsee/$tuple[ddebut]",
        'schema:name'=> $tuple['dnom'],
      ];
    }
    return [
      'header'=> ['Content-Type'=> 'application/ld+json'],
      'body'=> [
        '@context'=> [
          "rdf" => "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
          "dcterms" => "http://purl.org/dc/terms/",
          "schema" => "https://schema.org/",
        ],
        '@id'=> "https://comhisto.georef.eu/codeInsee/$cinsee",
        'dcterms:hasVersion'=> [
          'rdf:Bag'=> $tuples,
        ],
      ],
    ];
  }

  // /codeInsee/{cinsee}/{ddebut} -> URI de la version débutant à {ddebut} soit commune s'il y en a une, sinon ERAT
  // /codeInsee/{cinsee}?date={date} -> version existant à cette date soit de la commune s'il y en a une, sinon de l'ERAT
  //  JSON: Feature GeoJSON, LD: https://schema.org/City, Html: carte Leaflet, ou erreur 404
  if (($type == 'codeInsee') && ($ddebut || isset($_GET['date']))) {
    //echo "cas /codeInsee/{cinsee}/{ddebut} | /codeInsee/{cinsee}?date={date}<br>\n";
    $sql = "select id, ddebut, edebut, dfin, efin, statut, crat, erats, elits, dnom, ST_AsGeoJSON(geom) geom
            from comhistog3
            where cinsee='$cinsee' and "
            .($ddebut ? "ddebut='$ddebut'" : "ddebut <= '$_GET[date]' and (dfin > '$_GET[date]' or dfin is null)");
    //echo "<pre>sql=$sql\n";
    try {
      $tuples = PgSql::getTuples($sql);
    }
    catch (Exception $e) {
      echo "<pre>sql=$sql\n";
      echo $e->getMessage();
      throw new Exception($e->getMessage());
    }
    if (!($tuples = PgSql::getTuples($sql)))
      return ['error'=> [
        'httpCode'=> 404,
        'message'=> ($ddebut ? "id $cinsee@$ddebut" :  "$cinsee/date=$_GET[date]")." not found in comhistog3",
      ]];
    $theTuple = []; // le n-uplet conservé évet sur les 2
    foreach ($tuples as $tuple) {
      if (!$theTuple || !in_array($tuple['statut'], ['COMA','COMD','ARM']))
        $theTuple = $tuple;
    }
    $type = in_array($theTuple['statut'], ['COMA','COMD','ARM']) ? 'ERAT' : 'COM';
    return buildFeatureOrCity($theTuple, $type, $cinsee, $outputFormat, $ld);
  }

  // https://comhisto.georef.eu/elits2020/{cinsee} -> URI de l'élit 2020,
  //   si l'élit est encore valide alors retourne un Feature GeoJSON,
  //   si l'élit a été remplacé alors retourne la liste des élits le remplacant,
  //   si l'élit n'a jamais existé alors retourne une erreur 404
  // Pas de représentation LD
  if (($type == 'elits2020') && !$ddebut) {
    //echo "https://comhisto.georef.eu/elits2020/{cinsee}<br>\n";
    $sql = "select cinsee, ST_AsGeoJSON(geom) geom from elit where cinsee='$cinsee'";
    try {
      if (!($tuples = PgSql::getTuples($sql)))
        return ['error'=> ['httpCode'=> 404, 'message'=> "cinsee $cinsee not found in elit"]];
    }
    catch (Exception $e) {
      echo "<pre>sql=$sql\n";
      echo $e->getMessage();
      throw new Exception($e->getMessage());
    }

    $tuple = $tuples[0];
    $geom = $tuple['geom'];
    unset($tuple['geom']);
    return [
      'header'=> ['Content-Type'=> 'application/geo+json'],
      'body'=> [
        'type'=> 'Feature',
        'id'=> "https://comhisto.georef.eu/elits2020/$cinsee",
        'geometry'=> json_decode($geom, true),
      ],
    ];
  }

  return ['error'=> ['httpCode'=> 400, 'message'=> "Erreur requête non interprétée pour $path_info"]];
}

function fileExtension(string $filePath) { // retourne l'extension à partir d'un chemin
  $ext = null;
  if ($pos = strrpos($filePath, '.')) {
    $ext = substr($filePath, $pos+1);
  }
  //echo "pos=$pos, ext=$ext\n";
  return $ext;
}

//ini_set('max_execution_time', 3*60);
function pathInfoFromId(string $id): ?string { // construit le path_info à partir d'un id, null indique un id non correct
  // l'id peut correspondre à 4 formes différentes, eg: 01015, r01015, 01015@2016-01-01, r01015@2016-01-01
  if (preg_match('!^([sr])?(\d[\dAB]\d\d\d)(@(\d\d\d\d-\d\d-\d\d))?$!', $id, $matches)) {
    //print_r($matches);
    $type = $matches[1];
    $cinsee = $matches[2];
    $ddebut = $matches[4] ?? null;
    $path_info = '/'.(($type=='s')? 'COM' : (($type=='r') ? 'ERAT':'codeInsee'))."/$cinsee".($ddebut? "/$ddebut" : '');
    //echo "pathInfoFromId($id) -> $path_info<br>\n";
    return $path_info;
  }
  else {
    return null;
  }
}

function idFromPathInfo(string $path_info): ?string { // construit un id à partir d'un path_info ou null
  if (in_array($path_info,['','/']))
    return '';
  if (preg_match('!^/map/((s|r|)\d[\dAB]\d\d\d)(/(\d{4,4}-\d\d-\d\d))?$!', $path_info, $matches)) { // /map/{id}/{ddebut}?
    $id = $matches[1];
    $ddebut = $matches[4] ?? null;
    return $ddebut ? "$id@$ddebut" : $id;
  }
  if (!preg_match('!^/(COM|ERAT|codeInsee)(/(\d(\d|A|B)\d\d\d)(/(\d{4,4}-\d\d-\d\d))?(\.json)?)?$!', $path_info, $matches))
    return null;
  $type = $matches[1];
  $cinsee = $matches[3] ?? null;
  $ddebut = $matches[6] ?? null;
  $outputFormat = $matches[7] ?? null; // si le format de sortie est défini dans l'URL alors il s'impose
  if ($type == 'codeInsee')
    return $cinsee;
  $id = (($type=='COM') ? 's' : 'r').$cinsee;
  if (!$ddebut)
    return $id;
  else
    return "$id@$ddebut";
}

//echo "SCRIPT_NAME=$_SERVER[SCRIPT_NAME]<br>\n";
if (!$_SERVER['SCRIPT_NAME']) { // lors execution https://comhisto.georef.eu/ lecture .php et fichiers divers
  $path_info = $_SERVER['PATH_INFO'] ?? null;
  // scripts Php de ../map/
  //echo "api.php> path_info=$path_info<br>\n";
  //echo "api.php> basename(path_info)=",basename($path_info),"<br>\n";
  if (in_array($path_info, ['/map.php','/geojson.php','/neighbor.php','/doc.php'])) {
    //echo "api.php> path_info $path_info détecté, inclusion de ".__DIR__."/../map$path_info<br>\n";
    require __DIR__."/../map$path_info";
    if (function_exists('main')) // Pour map.php il faut appeler main(), pas pour les autres
      main($_GET);
    die();
  }
  // scripts Php dans le répertoire api
  elseif (in_array($path_info, ['/sitemap.php'])) {
    require __DIR__."/$path_info";
    die();
  }
  // fichiers js/css/png/... dans ../map/leaflet
  elseif ((substr($path_info,0,9) == '/leaflet/') || ($path_info == '/favicon.ico')) {
    switch (fileExtension($path_info)) {
      case 'css': header('Content-Type: text/css'); break;
      case 'js':  header('Content-Type: text/javascript'); break;
      case 'png': header('Content-Type: image/png'); break;
      case 'ico': header('Content-Type: image/x-icon'); break;
    }
    die(file_get_contents(__DIR__.'/../map'.$path_info));
  }
}

//echo "<pre>"; print_r($_SERVER); die();
$accept = explode(',', $_SERVER['HTTP_ACCEPT'] ?? '');

// si le paramètre id est défini et correct alors il remplace ceux définis dans path_info
$path_info = pathInfoFromId($_GET['id'] ?? '') ?? $_SERVER['PATH_INFO'] ?? '';
//echo "path_info=$path_info<br>\n";

$record = getRecord($path_info, !array_intersect($accept, ['application/json','application/geo+json']));
//print_r($record);
if (array_intersect($accept, ['application/json','application/ld+json','application/geo+json'])
|| (($record['outputFormat'] ?? '') == 'json') || (idFromPathInfo($path_info) === null)) {
  if ($error = $record['error'] ?? null) {
    define('HTTP_ERROR_LABELS', [
      400 => 'Bad Request',
      404 => 'Not Found',
    ]);
    header("HTTP/1.1 $error[httpCode] ".(HTTP_ERROR_LABELS[$error['httpCode']] ?? "Undefined httpCode $error[httpCode]"));
    header('Content-type: text/plain');
    die($error['message']);
  }
  else {
    header('Content-Type: '.$record['header']['Content-Type']);
    die(json_encode($record['body'], JSON_ENCODE_OPTIONS));
  }
}
elseif (preg_match('!^/map/!', $path_info)) { // cas d'appel de la carte par son URI https://comhisto.georef.eu/map/...
  require_once __DIR__.'/../map/map.php';
  main(isset($_GET['id']) ? $_GET : ['id'=> idFromPathInfo($path_info)], $record);
}
else {
  require_once __DIR__.'/../map/index.php'; // autre cas d'URI
  showComHisto($_GET['id'] ?? idFromPathInfo($path_info), $record);
}
