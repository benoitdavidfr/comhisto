<?php
/*PhpDoc:
name: cmphisto.php
title: cmphisto.php - comparaison le fichier histo.yaml v2 avec le fichier histov.yaml v1
doc: |
  De nbx écarts sont dus à la gestion de la déléguée propre mal gérée dans l'ancienne version,
  notamment la suppression de la déléguée propre formalisée par une absorption d'un code par lui même.
journal: |
  6-7/11/2020:
    - création
*/
ini_set('memory_limit', '2G');

require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

{
  if (php_sapi_name() <> 'cli')
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>cmphisto</title></head><body><pre>\n";
}

class AutoDescribed { // Pour garder une compatibilité avec YamlDoc, le pser est enregistré comme objet AutoDescribed
  protected $_id;
  protected $_c;

  function __construct(array $c, string $docid) { $this->_c = $c; $this->_id = $docid; }
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; } // lit les champs
  function asArray() { return $this->_c; }

  static function readfile(string $name): array { // lit un fichier si possible en pser sinon en Yaml, renvoit son contenu ss ses MD
    if (is_file(__DIR__."/$name.pser") && (filemtime(__DIR__."/$name.pser") > filemtime(__DIR__."/$name.yaml"))) {
      $file = unserialize(file_get_contents(__DIR__."/$name.pser"));
      return $file->contents;
    }
    else {
      $yaml = Yaml::parseFile(__DIR__."/$name.yaml");
      file_put_contents(__DIR__."/$name.pser", serialize(new AutoDescribed($yaml, '')));
      return $yaml['contents'];
    }
  }
};


// le nouveau fichier
$histos = AutoDescribed::readfile('histo');

// l'ancien fichier
$histovs = AutoDescribed::readfile('../insee/histov');

// change une ancienne valeur dans le nouveau format
function old2newFmt(string $cinsee, array $oldV): array {
  $statusConv = [ // chgt de statut
    'COMS'=> 'COM',
    'COMA'=> 'COMA',
    'COMD'=> 'COMD',
    'ARDM'=> 'ARM',
    'COMM'=> 'COM',
  ];
  $evtKeyConv = [ // chgt de clé d'évt
    'resteAssociéeA' => 'resteRattachéeA',
    'resteDéléguéeDe' => 'resteRattachéeA',
    'prendPourAssociées' => 'associe',
    'crééeCommeSimpleParScissionDe'=> 'crééeCOMParScissionDe',
    'crééeCommeAssociéeParScissionDe'=> 'crééeCOMAParScissionDe',
    'crééCommeArrondissementMunicipalParScissionDe'=> 'crééARMParScissionDe',
    'gardeCommeAssociées'=> 'gardeCommeRattachées',
    'gardeCommeDéléguées'=> 'gardeCommeRattachées',
  ];
  $newV = [];
  if (isset($oldV['evts'])) {
    foreach ($oldV['evts'] as $key => $value) {
      if (isset($evtKeyConv[$key]))
        $key = $evtKeyConv[$key];
      $newV['évts'][$key] = $value;
    }
  }
  $newV = 
      $newV
    + (isset($oldV['etat']) ?
      [
        'état'=> [
          'statut'=> $statusConv[$oldV['etat']['statut']],
          'name'=> $oldV['etat']['name'],
        ]
        + (isset($oldV['etat']['crat']) ? ['crat'=> $oldV['etat']['crat']] : [])
      ] : [])
    + (isset($oldV['erat']['aPourAssociées']) ? ['erat'=> $oldV['erat']['aPourAssociées']] : [])
    + (isset($oldV['erat']['aPourDéléguées']) ? ['erat'=> $oldV['erat']['aPourDéléguées']] : []);
  if (isset($newV['évts']['devientDéléguéeDe']) && ($newV['évts']['devientDéléguéeDe']==$cinsee))
    unset($newV['évts']['devientDéléguéeDe']);
  return $newV;
}

$nbEcarts = 0;

if (!isset($_GET['cinsee'])) {
  // 1) détection des codes/ddebut en +/- dans histos / histovs
  $cinsees = [];
  foreach ($histos as $cinsee => $histo) {
    $cinsees[$cinsee] = 1;
  }
  foreach ($histovs as $cinsee => $histo) {
    $cinsees[$cinsee] = 1;
  }
  ksort($cinsees);
  $cinsees = array_keys($cinsees);
  foreach ($cinsees as $cinsee) {
    $histo = $histos[$cinsee] ?? [];
    $histov = $histovs[$cinsee] ?? [];
    if (!$histov) {
      echo "> <a href='?cinsee=$cinsee'>$cinsee</a>\n";
    }
    elseif (!$histo) {
      echo "< <a href='?cinsee=$cinsee'>$cinsee</a>\n";
    }
    else {
      $ddebs = array_merge(array_keys($histos[$cinsee] ?? []), array_keys($histovs[$cinsee] ?? []));
      sort($ddebs);
      $ddebs = array_unique($ddebs);
      //echo Yaml::dump(['$ddebs' => $ddebs]);
      foreach ($ddebs as $ddeb) {
        if (!isset($histov[$ddeb])) {
          echo "> <a href='?cinsee=$cinsee'>$cinsee/$ddeb</a>\n";
          $nbEcarts++;
        }
        elseif (!isset($histo[$ddeb])) {
          echo "< <a href='?cinsee=$cinsee'>$cinsee/$ddeb</a>\n";
          $nbEcarts++;
        }
      }
    }
  }
  echo "--\n";
  
  // 2) détection des enregistrements différents
  foreach ($histovs as $cinsee => $histov) {
    foreach ($histov as $ddebut => $oldV) {
      $oldV = old2newFmt($cinsee, $oldV);
      unset($histos[$cinsee][$ddebut]['état']['nomCommeDéléguée']);
      if (isset($histos[$cinsee][$ddebut]) && ($oldV <> $histos[$cinsee][$ddebut])) {
        echo "<> <a href='?cinsee=$cinsee'>$cinsee@$ddebut</a>\n";
        echo "  < ",Yaml::dump($oldV, 0),"\n";
        echo "  > ",Yaml::dump($histos[$cinsee][$ddebut], 0),"\n";
        $nbEcarts++;
      }
      else {
        //echo "== $cinsee/$ddebut\n";
      }
    }
  }
  echo "$nbEcarts écarts détectés\n";
}
else {
  $cinsee = $_GET['cinsee'];
  $ancien = [];
  foreach ($histovs[$cinsee] as $ddebut => $version) {
    $ancien[$ddebut] = old2newFmt($cinsee, $version);
  }
  echo "<table border=1><th>cinsee</th><th>ancien</th><th>nouveau</th>\n";
  echo "<tr><td>$cinsee</td>";
  echo "<td>",Yaml::dump($ancien),"</td>";
  echo "<td>",Yaml::dump($histos[$cinsee]),"</td>";
  echo "</tr></table>\n";
}
