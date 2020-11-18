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

  Ce script peut être exécuté directement en mode non CLI ou utilisé par inclusion dans un autre.
  Il peut aussi être utilisé en mode CLI pour effectuer des vérifications sur tous les objets existants.

  Je décide dans les évènements de début d'utiliser l'URI valable à cette date et non l'URI de l'objet précédent
  Par exemple:
    type: Feature
    id: 'https://comhisto.georef.eu/ERAT/01015/2016-01-01'
    properties:
        ddebut: '2016-01-01'
        edebut:
            devientDéléguéeDe: 'https://comhisto.georef.eu/COM/01015/2016-01-01'
        dfin: null
        efin: null
        statut: COMD
        dnom: Arbignieu
    
    devientDéléguéeDe: https://comhisto.georef.eu/COM/01015/2016-01-01

    J'aurais pu utiliser l'URI de l'objet précédent, ici https://comhisto.georef.eu/COM/01015/1943-01-01
    mais dans certain cas cet objet n'existe pas et j'aurais été obligé de changer le code Insee

  Cas particuliers:
    - 44180/44225 - 
journal: |
  16-17/11/2020:
    - ajout du traitement du format de sortie (en cours)
      - le format de sortie peut être défini dans le paramètre HTTP Accept et dans une extension de l'URL
      - cette seconde possibilité prend le pas sur la première
    - mise en oeuvre des cartes sur comhisto.georef.eu
      - -->> revoir la version affichée
      - --> améliorer l'IHM pour afficher une version particulière
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

class HttpError extends Exception {}; // sous-classe d'exceptions pour gérer les erreurs Http

define('JSON_ENCODE_OPTIONS', JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

function showTrace(array $traces) {
  echo "<pre><h3>Trace de l'exception</h3>\n";
  echo Yaml::dump($traces);
}

// si $field='dfin' alors retourne l'URI de la précédente version correspondant à cinsee avant ddebut
// si $field='ddebut' alors retourne l'URI de l'objet correspondant à cinsee débutant à $ddebut
// de préférence de type défini par type
function makeUri(string $cinsee, string $field, string $ddebut, string $type=''): string {
  $sql = "select type, ddebut from comhistog3 where cinsee='$cinsee' and $field='$ddebut'";
  if (!($tuples = PgSql::getTuples($sql))) {
    throw new Exception("comhistog3 non trouvé pour cinsee=$cinsee et $field=$ddebut");
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

// prend en paramètre le path_info et la liste des formats demandés (HTTP_ACCEPT)
// si ok retourne un array avec d'une part un champ 'header' avec notamment un champ 'Content-Type'
// et d'autre part un champ 'body' avec soit l'objet JSON à retourner, soit un texte.
// Dans le second cas, peut aussi effectuer l'affichage et s'arrêter sans retour.
// En cas d'erreur lance une exception HttpError avec le code d'erreur Http et le message à afficher
function api(string $path_info, array $accept): array {
  if (!$path_info || ($path_info == '/')) { // racine
    $baseUrl = "http://$_SERVER[SERVER_NAME]".(($_SERVER['SERVER_NAME']=='localhost') ? "$_SERVER[SCRIPT_NAME]" : '');
    if (in_array('text/html', $accept)) {
      require_once __DIR__.'/../map/index.php';
      if (!isset($_GET['id']))
        return ['header'=> ['Content-Type'=> 'text/html'], 'body'=> map()];
      else
        return ['header'=> ['Content-Type'=> 'text/html'], 'body'=> map($_GET['id'])];
    }
    else
      return [
        'header'=> ['Content-Type'=> 'application/json'],
        'body'=> [
          'examples'=> "$baseUrl/examples",
          '_SERVER'=> $_SERVER,
        ],
      ];
  }

  if ($path_info == '/examples') { // exemples pour effectuer les tests 
    $baseUrl = "http://$_SERVER[SERVER_NAME]".(($_SERVER['SERVER_NAME']=='localhost') ? $_SERVER['SCRIPT_NAME'] : '');
    $examples = [
      'doc'=> "$baseUrl",
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
    if (in_array('application/json', $accept))
      return [
        'header'=> [
          'Content-Type'=> 'application/json',
        ],
        'body'=> [
          'examples'=> $examples,
        ]
      ];
    else {
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
    }
  }

  if (preg_match('!^/contexts/([^/]*)!', $path_info, $matches)) {
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
      'rdf' => [
        
      ],
    ]
    );
    $contextId = $matches[1];
    if (!isset(CONTEXTS[$contextId]))
      throw new HttpError("Erreur contexte $contextId inexistant", 404);
    else
      return [
        'header'=> ['Content-Type'=> 'application/json'],
        'body'=> CONTEXTS[$contextId],
      ];
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

  if (preg_match('!^/status/([^/]*)!', $path_info, $matches)) {
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
      throw new HttpError("Erreur statut $statusId inexistant", 404);
  }

  // ! /(COM|ERAT|elits2020|codeInsee)(/{cinsee}(/{ddebut})?)?
  elseif (!preg_match('!^/(COM|ERAT|elits2020|codeInsee)(/(\d(\d|AB)\d\d\d)(/(\d{4,4}-\d\d-\d\d))?(\.json)?)?$!',
       $path_info, $matches))
    throw new HttpError("Erreur $path_info non reconnu", 400);

  $type = $matches[1];
  $cinsee = $matches[3] ?? null;
  $ddebut = $matches[6] ?? null;
  $format = $matches[7] ?? null; // si le format est défini dans l'URL alors il s'impose
  if (!$format) // sinon il dépend du paramètre Accept de la requête
    $format = in_array('text/html', $accept) ? '.html' : '.json';
  
  // https://comhisto.georef.eu/(COM|ERAT) -> liste des COM|ERAT 
  if (!$cinsee && in_array($type, ['COM','ERAT'])) {
    $t = ($type=='COM') ? 's': 'r';
    $sql = "select cinsee id, ddebut, statut, ".(($type=='COM') ? 'erats' : 'crat').", dnom
            from comhistog3
            where type='$t' and dfin is null";
    try {
      foreach (PgSql::getTuples($sql) as &$tuple) {
        completeUriTuple($tuple, $tuple['id']);
        $tuple['id'] = "https://comhisto.georef.eu/$type/$tuple[id]/$tuple[ddebut]";
        $tuples[] = $tuple;
      }
    }
    catch (Exception $e) {
      echo "<pre>sql=$sql</pre>\n";
      echo $e->getMessage();
      showTrace($e->getTrace());
      throw new Exception($e->getMessage());
    }
    return [
      'header'=> ['Content-Type'=> 'application/json'],
      //'Content-Type'=> 'application/ld+json',
      'body'=> [
        '@context' => 'https://comhisto.georef.eu/contexts/rdf',
        '@id'=> "https://comhisto.georef.eu/$type",
        'list'=> $tuples,
      ],
    ];
  }

  // https://comhisto.georef.eu/(COM|ERAT)/{cinsee}/{ddebut} -> URI de la version de COM/ERAT comhisto
  //   retourne le Feature GeoJSON si elle existe, sinon Erreur 404
  // https://comhisto.georef.eu/(COM|ERAT)/{cinsee} -> URI de la version valide COM/ERAT comhisto
  //   comme Feature GeoJSON si elle existe, sinon Erreur 404
  // https://comhisto.geoapi.fr/(COM|ERAT)/{cinsee}?date={date}
  //   -> accès à la version correspondant à cette date comme Feature GeoJSON
  if ($cinsee && in_array($type, ['COM','ERAT'])) {
    $t = ($type=='COM') ? 's': 'r';
    if (($format == '.html') && !isset($_GET['date'])) {
      require_once __DIR__.'/../map/index.php';
      return [
        'header'=> ['Content-Type'=> 'text/html'],
        'body'=> map(!$ddebut ? $cinsee : "$t$cinsee@$ddebut"),
      ];
    }
    $sql = "select ddebut, edebut, dfin, efin, statut, ".(($type=='COM') ? 'erats' : 'crat').", elits, dnom,
              ST_AsGeoJSON(geom) geom
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
      $message = ($ddebut ? "id $type$cinsee@$ddebut" :
            (isset($_GET['date']) ? "$type$cinsee/date=$_GET[date]" : "$type$cinsee"))
                  ." not found in comhistog3";
      throw new HttpError($message, 404);
    }
    $tuple = $tuples[0];
    $geom = completeUriTuple($tuple, $cinsee);
    try {
      $replaces = makeUri($cinsee, 'dfin', $tuple['ddebut'], '');
    }
    catch (Exception $e) {
      $replaces = null;
    }
    $isReplacedBy = $tuple['dfin'] ? makeUri($cinsee, 'ddebut', $tuple['dfin'], '') : null;
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
        + [
          'elits'=> $tuple['elits'],
          'name'=> $tuple['dnom'],
        ],
        'geometry'=> $geom,
      ]
    ];
  }

  // https://comhisto.georef.eu/elits2020/{cinsee} -> URI de l'élit 2020,
  //   si l'élit est encore valide alors retourne un Feature GeoJSON,
  //   si l'élit a été remplacé alors retourne la liste des élits le remplacant,
  //   si l'élit n'a jamais existé alors retourne une erreur 404
  if (($type == 'elits2020') && !$ddebut) {
    //echo "https://comhisto.georef.eu/elits2020/{cinsee}<br>\n";
    $sql = "select cinsee, ST_AsGeoJSON(geom) geom from elit where cinsee='$cinsee'";
    try {
      if (!($tuples = PgSql::getTuples($sql)))
        throw new HttpError("cinsee $cinsee not found in elit", 404);
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

  // https://comhisto.georef.eu/codeInsee/{cinsee} -> URI du code Insee, retourne la liste des objets utilisant ce code
  // https://comhisto.geoapi.fr/codeInsee/{cinsee} -> liste des versions
  if (($type == 'codeInsee') && !$ddebut && !isset($_GET['date'])) { // /codeInsee/{cinsee} -> retourne liste objets avec code
    //echo "/codeInsee/{cinsee}<br>\n";
    $sql = "select id, ddebut, edebut, dfin, efin, statut, crat, erats, elits, dnom from comhistog3 where cinsee='$cinsee'";
    try {
      if (!($tuples = PgSql::getTuples($sql)))
        throw new HttpError("cinsee $cinsee not found in comhistog3", 404);
    }
    catch (Exception $e) {
      echo "<pre>sql=$sql\n";
      echo $e->getMessage();
      throw new Exception($e->getMessage());
    }

    foreach ($tuples as &$tuple) {
      $geom = completeUriTuple($tuple, $cinsee);
      $type = in_array($tuple['statut'], ['COMA','COMD','ARM']) ? 'ERAT' : 'COM'; 
      $tuple['id'] = "https://comhisto.georef.eu/$type/$cinsee/$tuple[ddebut]";
    }
    return [
      'header'=> ['Content-Type'=> 'application/geo+json'],
      'body'=> [
        '@id'=> "https://comhisto.georef.eu/codeInsee/$cinsee",
        'versions'=> $tuples,
      ],
    ];
  }

  // /codeInsee/{cinsee}/{ddebut} || /codeInsee/{cinsee}?date={date}
  if (($type == 'codeInsee') && ($ddebut || isset($_GET['date']))) {
    // -> accès à la ou les 2 versions correspondant à cette date comme FeatureCollection GeoJSON
    $sql = "select id, ddebut, edebut, dfin, efin, statut, crat, erats, elits, dnom, ST_AsGeoJSON(geom) geom
            from comhistog3
            where cinsee='$cinsee' and "
            .($ddebut ? "ddebut='$ddebut'" : "ddebut <= '$_GET[date]' and (dfin > '$_GET[date]' or dfin is null)");
  
    try {
      $tuples = PgSql::getTuples($sql);
    }
    catch (Exception $e) {
      echo "<pre>sql=$sql\n";
      echo $e->getMessage();
      throw new Exception($e->getMessage());
    }
    if (!($tuples = PgSql::getTuples($sql)))
      throw new HttpError(($ddebut ? "id $cinsee@$ddebut" :  "$cinsee/date=$_GET[date]")." not found in comhistog3", 404);
    $features = [];
    foreach ($tuples as $tuple) {
      foreach (['edebut','efin','erats','elits','geom'] as $prop)
        $tuple[$prop] = json_decode($tuple[$prop], true);
      $geom = $tuple['geom'];
      unset($tuple['geom']);
      unset($tuple['id']);
      $type = in_array($tuple['statut'], ['COMA','COMD','ARM']) ? 'ERAT' : 'COM'; 
      $features[] = [
        'type'=> 'Feature',
        'id'=> "https://comhisto.georef.eu/$type/$cinsee/$tuple[ddebut]",
        'properties'=> $tuple,
        'geometry'=> $geom,
      ];
    }
    return [
      'header'=> ['Content-Type'=> 'application/geo+json'],
      'body'=> [
        'type'=> 'FeatureCollection',
        'features'=> $features,
      ],
    ];
  }

  throw new HttpError("Erreur requête non interprétée", 400);
}

if (!$_SERVER['SCRIPT_NAME']) { // execution https://comhisto.georef.eu/
  if (in_array($_SERVER['PATH_INFO'], ['/map.php','/geojson.php','/neighbor.php'])) {
    require __DIR__."/../map$_SERVER[PATH_INFO]";
    die();
  }
  elseif (in_array($_SERVER['PATH_INFO'], ['/map/map.php','/map/geojson.php','/map/neighbor.php'])) {
    require __DIR__."/..$_SERVER[PATH_INFO]";
    die();
  }
  elseif ((substr($_SERVER['PATH_INFO'],0,13) == '/map/leaflet/') || ($_SERVER['PATH_INFO'] == '/favicon.ico')) {
    $ext = null;
    if ($pos = strrpos($_SERVER['PATH_INFO'], '.')) {
      $ext = substr($_SERVER['PATH_INFO'], $pos+1);
    }
    //echo "pos=$pos, ext=$ext\n";
    if ($ext == 'css')
      header('Content-Type: text/css');
    if ($ext == 'js')
      header('Content-Type: text/javascript');
    if ($ext == 'ico')
      header('Content-Type: image/x-icon');
    die(file_get_contents(__DIR__.'/..'.$_SERVER['PATH_INFO']));
  }
  /*elseif (!in_array($_SERVER['PATH_INFO'], ['/',''])) {
    echo "<pre>"; print_r($_SERVER);
    die("Erreur ligne ".__LINE__);
  }*/
}
if ((basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) || !$_SERVER['SCRIPT_NAME']) { // Exécution lorsque le script est appelé directement
  //echo "<pre>"; print_r($_SERVER); die();
  try {
    $accept = explode(',', $_SERVER['HTTP_ACCEPT'] ?? '');
    $result = api($_SERVER['PATH_INFO'] ?? '', $accept);
    header('Content-Type: '.$result['header']['Content-Type']);
    if (in_array($result['header']['Content-Type'], ['application/json','application/ld+json','application/geo+json'])) {
      die(json_encode($result['body'], JSON_ENCODE_OPTIONS));
    }
    elseif ($result['header']['Content-Type']=='text/html') {
      die($result['body']);
    }
  } catch (HttpError $e) {
    define('HTTP_ERROR_LABELS', [
      400 => 'Bad Request',
      404 => 'Not Found',
    ]);
    header('HTTP/1.1 '.$e->getCode().' '.(HTTP_ERROR_LABELS[$e->getCode()] ?? 'Undefined http error'));
    header('Content-type: text/plain');
    die($e->getMessage());
  }
}
