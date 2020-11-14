<?php
/*PhpDoc:
name: api.php
title: api/api.php - API d'accès à ComHisto
doc: |
  L'objet de ce script est double:
    1) définir et opérationaliser la définition d'URI pour les objets ComHisto
    2) mettre en oeuvre le cas d'usage de récupération de la géométrie de la commune/ERAT correspondant à un code Insee
       donné et à une date donnée.

  Identification des objets de ComHisto au travers d'URI et accès aux objets:
    http://comhisto.georef.eu/ -> Doc de l'API
    http://comhisto.georef.eu/(COM|ERAT)/{cinsee} -> URI de la version valide de la commune/ERAT comme Feature GeoJSON
      si elle existe, sinon Erreur 404
    http://comhisto.georef.eu/(COM|ERAT)/{cinsee}/{ddebut} -> URI de la version de commune/ERAT comme Feature GeoJSON
      si elle existe, sinon Erreur 404
    http://comhisto.georef.eu/elits2020/{cinsee} -> URI de l'élit 2020,
      si l'élit est encore valide alors retourne un Feature GeoJSON,
      si l'élit a été remplacé alors retourne la liste des élits le remplacant (A VOIR),
      si l'élit n'a jamais existé alors retourne une erreur 404
    http://comhisto.georef.eu/codeInsee/{cinsee} -> URI du code Insee, retourne la liste des versions de COM/ERAT
      utilisant ce code
    http://comhisto.georef.eu/codeInsee/{cinsee}/{ddebut} -> URI de la version du code Insee,
      retourne comme FeatureCollection GeoJSON les objets COM/ERAT

  Requêtes - Points d'entrée:
    http://comhisto.geoapi.fr/ -> doc
    http://comhisto.geoapi.fr/codeInsee/{cinsee} -> liste des versions sans accès à la géométrie
    http://comhisto.geoapi.fr/codeInsee/{cinsee}?date={date} -> accès à la ou les 2 versions correspondant à cette date
      comme FeatureCollection GeoJSON
    http://comhisto.geoapi.fr/(communes|ERAT)/{cinsee}?date={date}
      -> accès à la version correspondant à cette date comme Feature GeoJSON

  Les 2 ensembles d'URL sont compatibles.
  Ainsi http://comhisto.georef.eu/ et http://comhisto.geoapi.fr/
  sont mappés vers /prod/georef/yamldoc/pub/comhisto/api/api.php/
 
  Cas particuliers:
    - 44180/44225 - 
journal: |
  14/11/2020:
    - remplacement des codes Insee par des URI
    - rendre les résultats conformes à JSON-LD ???
  13/11/2020:
    - création
*/
{ // doc complémentaire
/*
  http://www.jenitennison.com/2010/02/27/versioning-uk-government-linked-data.html
    http://{sector}.data.gov.uk/doc/{concept}/{identifier}/{version} 

  Rappel motifs d'URI référentiel administratif http://admin.georef.eu :
    - /regionmetro : découpage de la métropole en régions
      - /{cinsee} : désignation d'une région
      - /{cinsee}-{annee} : désignation d'une région au 1er janvier d'une année donnée
    - /deptmetro : découpage de la métropole en départements
      - /{cinsee} : désignation d'un département
    - /dom : départements d'outre-mer
      - /{cinsee} : désignation d'un DOM
    - /com : autres espaces d'outre-mer
      - /{cinsee} : désignation d'un espace OM
    - /commune : découpage en communes
      - /{cinsee} : désignation d'une commune
      - /{cinsee}-{annee} : désignation d'une commune au 1er janvier d'une année donnée
    - /dreal : liste des DREAL + 3 DRI IdF
      - /{nom} : désignation d'une DREAL
    - /deal : liste des DEAL
      - /{nom} : désignation d'une DEAL
    - /dirm : liste des DIRM
      - /{nom} : désignation d'une DIRM
    - /drom : liste des DROM
      - /{nom} : désignation d'une DROM
    - /dir : liste des DIR
      - /{nom} : désignation d'une DIR
    - /draaf : liste des DRAAF
      - /{nom} : désignation d'une DRAAF
    - /daaf : liste des DAAF
      - /{nom} : désignation d'une DAAF
    - /ddt : liste des DDT(M)
      - /{cinsee} : désignation d'une DDT(M)
    - /dtam : liste de la DTAM 975
      - /975 : désignation de la DTAM 975
    - /cr : liste des conseils régionaux + collectivités ayant ces attributions
      - /{cinsee} : désignation d'un CR
    - /cd : liste des conseils départementaux + collectivités ayant ces attributions
      - /{cinsee} : désignation d'un CR
    - /ct : liste des collectivités à statut spécial
      - /{cinsee}
    - /epci : liste des EPCI
      - /{csirene}
    - /ministere
      - /environnement
      - /logement
      - /transport
      - /agriculture
journal: |
  13/11/2020:
    - création
*/}

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

function httpError(int $code, string $message) { // Génère une erreur Http et affiche comme texte le message 
  $httpErrorLabels = [
    400 => 'Bad Request',
    404 => 'Not Found',
  ];
  header("HTTP/1.1 $code ".$httpErrorLabels[$code] ?? 'Undefined http error');
  header('Content-type: text/plain');
  die ($message);
}

// si $field='dfin' alors retourne l'URI de la précédente version correspondant à cinsee avant ddebut
// si $field='ddebut' alors retourne l'URI de l'objet correspondant à cinsee débutant à $ddebut
// de préférence de type défini par type
function makeUri(string $cinsee, string $field, string $ddebut, string $type=''): string {
  $sql = "select type, ddebut from comhistog3
          where cinsee='$cinsee' and $field='$ddebut'";
  if (!($tuples = PgSql::getTuples($sql))) {
    echo "<pre>";
    throw new Exception("comhistog3 non trouvé pour cinsee=$cinsee et $field=$ddebut");
  }
  //echo '$tuples = '; print_r($tuples);
  if ((count($tuples) == 1) || !$type) {
    $tuple = $tuples[0];
    $type = ($tuple['type']=='s') ? 'COM' : 'ERAT';
    return "http://comhisto.georef.eu/$type/$cinsee/$tuple[ddebut]";
  }
  else {
    foreach ($tuples as $tuple) {
      if ($tuple['type'] == $type) {
        $type = ($tuple['type']=='s') ? 'COM' : 'ERAT';
        return "http://comhisto.georef.eu/$type/$cinsee/$tuple[ddebut]";
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
      
      case 'changeDeCodePour':
      case 'avaitPourCode': {
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

/*// complète un ens. d'evts en remplacant les codes Insee par des Uri - SAUVE
function completeUriEvt(array &$evts, string $ddebut, string $cinsee): void {
  foreach ($evts as $verb => &$params) {
    echo "completeUriEvt($cinsee@$ddebut, $verb)<br>\n";
    switch ($verb) {
      case 'entreDansLeRéférentiel': break;
      case 'changeDeNomPour': break;
      case 'aucun': break;
      
      case 'changeDeCodePour':
      case 'avaitPourCode': {
        break;
      }
      
      case 'absorbe':
      case 'associe':
      case 'prendPourDéléguées':
      case 'gardeCommeRattachées':
      case 'détacheCommeSimples':
      case 'estModifiéeIndirectementPar': {
        foreach ($params as $i => $param) {
          $params[$i] = makeUri($param, 'dfin', $ddebut);
        }
        break;
      }
      
      case 'seScindePourCréer': {
        foreach ($params as $i => $param) {
          $params[$i] = makeUri($param, 'ddebut', $ddebut);
        }
        break;
      }

      case 'sAssocieA':
      case 'fusionneDans':
      case 'devientDéléguéeDe':
      case 'crééeCOMParScissionDe':
      case 'seDétacheDe': {
        $params = makeUri($params, 'dfin', $ddebut, 's');
        break;
      }
      
      default: {
        throw new Exception("verbe $verb non traité dans completeUriEvt()");
      }
    }
  }
}*/

// complète les URI pour un n-uplet, retourne geom
function completeUriTuple(array &$tuple, string $cinsee): array {
  foreach (['edebut','efin','erats','elits','geom'] as $prop)
    if (isset($tuple[$prop]))
      $tuple[$prop] = json_decode($tuple[$prop], true);
  if (isset($tuple['edebut']))
    completeUriEvt($tuple['edebut'], $tuple['ddebut'], $cinsee);
  if (isset($tuple['efin'])) 
    completeUriEvt($tuple['efin'], $tuple['dfin'], $cinsee);
  if (isset($tuple['crat']))
    $tuple['crat'] = "http://comhisto.georef.eu/COM/$tuple[crat]/$tuple[ddebut]";
  foreach ($tuple['erats'] as $i => $erat)
    $tuple['erats'][$i] = "http://comhisto.georef.eu/ERAT/$erat/$tuple[ddebut]";
  if (isset($tuple['elits']))
    foreach ($tuple['elits'] as $i => $elit)
      $tuple['elits'][$i] = "http://comhisto.georef.eu/elits2020/$elit";
  $geom = $tuple['geom'] ?? [];
  unset($tuple['geom']);
  return $geom;
}

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

if (!($path_info = $_SERVER['PATH_INFO'] ?? null) || ($path_info == '/')) { // racine
  $url = "http://$_SERVER[SERVER_NAME]".(($_SERVER['SERVER_NAME']=='localhost') ? "$_SERVER[SCRIPT_NAME]" : '');
  header('Content-Type: application/json');
  die(json_encode([
    'examples'=> "$url/examples",
  ]));
}

if ($path_info == '/examples') { // exemples pour effectuer les tests 
  $url = "http://$_SERVER[SERVER_NAME]".(($_SERVER['SERVER_NAME']=='localhost') ? "$_SERVER[SCRIPT_NAME]" : '');
  header('Content-Type: application/json');
  die(json_encode([
    'examples'=> [
      ''=> "$url",
      '/COM'=> "$url/COM",
      '/ERAT/01015'=> "$url/ERAT/01015",
      '/COM/01015/2016-01-01'=> "$url/COM/01015/2016-01-01",
      '/ERAT/01015/2016-01-01'=> "$url/ERAT/01015/2016-01-01",
      '/ERAT/01015/2019-01-01 Erreur'=> "$url/ERAT/01015/2019-01-01",
      '/ERAT/01015?date=2019-01-01'=> "$url/ERAT/01015?date=2019-01-01",
      '/elits2020/01015'=> "$url/elits2020/01015",
      '/codeInsee/01015'=> "$url/codeInsee/01015",
      '/codeInsee/01015/2016-01-01 ok'=> "$url/codeInsee/01015/2016-01-01",
      '/codeInsee/01015/2019-01-01 KO'=> "$url/codeInsee/01015/2019-01-01",
      '/codeInsee/01015/2019-01-01?date=2019-01-01'=> "$url/codeInsee/01015?date=2019-01-01",
    ],
    '_SERVER'=> $_SERVER,
  ]));
}

// ! /(COM|ERAT|elits2020|codeInsee)/{cinsee}(/{ddebut})?
elseif (!preg_match('!^/(COM|ERAT|elits2020|codeInsee)(/(\d(\d|AB)\d\d\d))?(/(\d\d\d\d-\d\d-\d\d))?$!',
     $path_info, $matches))
  httpError(400, "Erreur $path_info non reconnu");

$type = $matches[1];
$cinsee = $matches[3] ?? null;
$ddebut = $matches[6] ?? null;

// http://comhisto.georef.eu/(COM|ERAT) -> liste des COM/ERAT 
if (!$cinsee && in_array($type, ['COM','ERAT'])) {
  $t = ($type=='COM') ? 's': 'r';
  $sql = "select cinsee id, ddebut, statut, crat, erats, dnom
          from comhistog3
          where type='$t' and dfin is null";
  try {
    foreach (PgSql::getTuples($sql) as &$tuple) {
      completeUriTuple($tuple, $tuple['id']);
      $tuple['id'] = "http://comhisto.georef.eu/$type/$tuple[id]/$tuple[ddebut]";
      $tuples[] = $tuple;
    }
  }
  catch (Exception $e) {
    echo $e->getMessage();
    showTrace($e->getTrace());
    throw new Exception($e->getMessage());
  }
  header('Content-Type: application/json');
  die(json_encode([
    'id'=> "http://comhisto.georef.eu/$type",
    'list'=> $tuples,
  ], JSON_ENCODE_OPTIONS));
}

// http://comhisto.georef.eu/(COM|ERAT)/{cinsee}/{ddebut} -> URI de la version de COM/ERAT comhisto
//   retourne le Feature GeoJSON si elle existe, sinon Erreur 404
// http://comhisto.georef.eu/(COM|ERAT)/{cinsee} -> URI de la version valide COM/ERAT comhisto
//   comme Feature GeoJSON si elle existe, sinon Erreur 404
// http://comhisto.geoapi.fr/(COM|ERAT)/{cinsee}?date={date}
//   -> accès à la version correspondant à cette date comme Feature GeoJSON
if ($cinsee && in_array($type, ['COM','ERAT'])) {
  $t = ($type=='COM') ? 's': 'r';
  $sql = "select ddebut, edebut, dfin, efin, statut, crat, erats, elits, dnom, ST_AsGeoJSON(geom) geom
          from comhistog3
          where ";
  if ($ddebut) { // http://comhisto.georef.eu/(COM|ERAT)/{cinsee}/{ddebut} -> URI de la COM/ERAT
    $sql .= "id='$t$cinsee@$ddebut'";
  }
  elseif (!isset($_GET['date'])) { // http://comhisto.georef.eu/(COM|ERAT)/{cinsee} -> URI de la COM/ERAT valide
    $sql .= "type='$t' and cinsee='$cinsee' and dfin is null";
  }
  else { // http://comhisto.geoapi.fr/(COM|ERAT)/{cinsee}?date={date}
    $sql .= "type='$t' and cinsee='$cinsee'
             and (ddebut <= '$_GET[date]' and (dfin > '$_GET[date]' or dfin is null))";
  }
  
  try {
    if (!($tuples = PgSql::getTuples($sql))) {
      echo "<pre>sql=$sql\n";
      httpError(404,
        ($ddebut ? "id $type$cinsee@$ddebut" :
          (isset($_GET['date']) ? "$type$cinsee/date=$_GET[date]" :
           "$type$cinsee"))
        ." not found in comhistog3");
    }
  }
  catch (Exception $e) {
    echo "<pre>sql=$sql\n";
    echo $e->getMessage();
    throw new Exception($e->getMessage());
  }
  $tuple = $tuples[0];
  $geom = completeUriTuple($tuple, $cinsee);
  header('Content-Type: application/json');
  die(json_encode([
    'type'=> 'Feature',
    'id'=> "http://comhisto.georef.eu/$type/$cinsee/$tuple[ddebut]",
    'properties'=> $tuple,
    'geometry'=> $geom,
  ], JSON_ENCODE_OPTIONS));
}

// http://comhisto.georef.eu/elits2020/{cinsee} -> URI de l'élit 2020,
//   si l'élit est encore valide alors retourne un Feature GeoJSON,
//   si l'élit a été remplacé alors retourne la liste des élits le remplacant,
//   si l'élit n'a jamais existé alors retourne une erreur 404
if (($type == 'elits2020') && !$ddebut) {
  //echo "http://comhisto.georef.eu/elits2020/{cinsee}<br>\n";
  
  $sql = "select cinsee, ST_AsGeoJSON(geom) geom from elit where cinsee='$cinsee'";
  
  try {
    if (!($tuples = PgSql::getTuples($sql)))
      httpError(404, "cinsee $cinsee not found in elit");
  }
  catch (Exception $e) {
    echo "<pre>sql=$sql\n";
    echo $e->getMessage();
    throw new Exception($e->getMessage());
  }

  $tuple = $tuples[0];
  $geom = $tuple['geom'];
  unset($tuple['geom']);
  header('Content-Type: application/json');
  die(json_encode([
    'type'=> 'Feature',
    'id'=> "http://comhisto.georef.eu/elits2020/$cinsee",
    'geometry'=> json_decode($geom, true),
  ]));
}

// http://comhisto.georef.eu/codeInsee/{cinsee} -> URI du code Insee, retourne la liste des objets utilisant ce code
// http://comhisto.geoapi.fr/codeInsee/{cinsee} -> liste des versions
if (($type == 'codeInsee') && !$ddebut && !isset($_GET['date'])) { // /codeInsee/{cinsee} -> retourne liste objets avec code
  //echo "/codeInsee/{cinsee}<br>\n";
  $sql = "select id, ddebut, edebut, dfin, efin, statut, crat, erats, elits, dnom from comhistog3 where cinsee='$cinsee'";
  try {
    if (!($tuples = PgSql::getTuples($sql)))
      httpError(404, "cinsee $cinsee not found in comhistog3");
  }
  catch (Exception $e) {
    echo "<pre>sql=$sql\n";
    echo $e->getMessage();
    throw new Exception($e->getMessage());
  }

  foreach ($tuples as &$tuple) {
    $geom = completeUriTuple($tuple, $cinsee);
    $type = in_array($tuple['statut'], ['COMA','COMD','ARM']) ? 'ERAT' : 'COM'; 
    $tuple['id'] = "http://comhisto.georef.eu/$type/$cinsee/$tuple[ddebut]";
  }
  header('Content-Type: application/json');
  die(json_encode([
    '@id'=> "http://comhisto.georef.eu/codeInsee/$cinsee",
    'versions'=> $tuples,
  ]));
}

// /codeInsee/{cinsee}/{ddebut} || /codeInsee/{cinsee}?date={date}
if (($type == 'codeInsee') && ($ddebut || isset($_GET['date']))) {
  // -> accès à la ou les 2 versions correspondant à cette date comme FeatureCollection GeoJSON
  $sql = "select id, ddebut, edebut, dfin, efin, statut, crat, erats, elits, dnom, ST_AsGeoJSON(geom) geom
          from comhistog3
          where cinsee='$cinsee' and "
          .($ddebut ? "ddebut='$ddebut'" : "ddebut <= '$_GET[date]' and (dfin > '$_GET[date]' or dfin is null)");
  
  try {
    if (!($tuples = PgSql::getTuples($sql)))
      httpError(404, ($ddebut ? "id $cinsee@$ddebut" :  "$cinsee/date=$_GET[date]")." not found in comhistog3\n");
  }
  catch (Exception $e) {
    echo "<pre>sql=$sql\n";
    echo $e->getMessage();
    throw new Exception($e->getMessage());
  }
  $features = [];
  foreach ($tuples as $tuple) {
    foreach (['edebut','efin','erats','elits','geom'] as $prop)
      $tuple[$prop] = json_decode($tuple[$prop], true);
    $geom = $tuple['geom'];
    unset($tuple['geom']);
    $type = in_array($tuple['statut'], ['COMA','COMD','ARM']) ? 'ERAT' : 'COM'; 
    $features[] = [
      'type'=> 'Feature',
      'id'=> "http://comhisto.georef.eu/$type/$cinsee/$tuple[ddebut]",
      'properties'=> $tuple,
      'geometry'=> $geom,
    ];
  }
  header('Content-Type: application/json');
  die(json_encode([
    'type'=> 'FeatureCollection',
    'features'=> $features,
  ]));
}

httpError(400, "Erreur requête non interprétée\n");
