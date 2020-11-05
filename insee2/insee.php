<?php
/*PhpDoc:
name: insee.php
title: insee.php - fabrication du fichier com20200101.yaml à partir du fichier ../data/communes2020.csv
doc: |
  structuration avec un enregistrement par code Insee avec statut + name + crat pour les erats
  statut dans COM, COMD, COMA et ARM
journal: |
  30/10/2020:
    - modif statuts
  1/10/2020:
    - refonte, format txt non traité
*/
ini_set('memory_limit', '2G');

require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;


if (php_sapi_name() == 'cli') {
  if ($argc <> 4)
    die("usage: php $argv[0] {chemin du fichier Insee} {date de validité} csv|txt\n");
  $params = [
    'file'=> $argv[1],
    'valid'=> $argv[2],
    'format'=> $argv[3],
  ];
}
else {
  $params = [
    'file'=> '../data/communes2020.csv',
    'valid'=> '2020-01-01',
    'format'=> 'csv',
  ];
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>insee</title></head><body>\n",
       "<h3>lecture du fichier $params[file]</h3><pre>\n";
}

$coms = []; // [cinsee => record + children] 
$enfants = []; // [cinsee => record] 
if (!($file = @fopen($params['file'], 'r')))
  die("Erreur sur l'ouverture du fichier '$params[file]'\n");
$sep = $params['format'] == 'csv' ? ',' : "\t";
$headers = fgetcsv($file, 0, $sep);
// un des fichiers comporte des caractères parasites au début ce qui perturbe la détection des headers
foreach ($headers as $i => $header)
  if (preg_match('!"([^"]+)"!', $header, $matches))
    $headers[$i] = $matches[1];
//echo "<pre>headers="; print_r($headers); echo "</pre>\n";
while ($record = fgetcsv($file, 0, $sep)) {
  //echo "<pre>record="; print_r($record); echo "</pre>\n";
  $rec = [];
  foreach ($headers as $i => $header) {
    $rec[strtolower($header)] = 
      ($params['format'] == 'csv') ?
        $record[$i] :
          mb_convert_encoding ($record[$i], 'UTF-8', 'Windows-1252');
  }
  if ($params['format'] == 'txt') {
    $rec = conv2Csv($rec);
    if ($rec['typecom'] == 'X')
      continue;
  }
  //if ($rec['com'] == '45307') { echo "<pre>rec="; print_r($rec); echo "</pre>\n"; }
  //echo "$rec[nccenr] ($typecom $rec[com])<br>\n";
  if (!$rec['comparent']) {
    //$coms[$rec['com']] = ['name'=> $rec['nccenr']];
    $coms[$rec['com']] = ['statut'=> 'COM', 'name'=> $rec['libelle']];
  }
  else {
    $enfants[$rec['com']] = $rec;
  }
  //if ($nbrec >= 10) die("<b>die nbrec >= 10</b>");
}
foreach ($enfants as $c => $enfant) {
  $crat = $enfant['comparent'];
  // sauf s'ils commencent par 0, 2A ou 2B, les codes sont gérés comme un entier
  if ((substr($crat, 0, 1) <> '0') && !in_array(substr($crat, 0, 2), ['2A','2B']))
    $crat = intval($crat);
  switch ($enfant['typecom']) {
    case 'COMA': {
      $coms[$c] = ['statut'=> $enfant['typecom'], 'name'=> $enfant['libelle'], 'crat'=> $crat];
      break;
    }
    case 'COMD': {
      if (isset($coms[$c]))
        $coms[$c]['commeDéléguée'] = ['name'=> $enfant['libelle']];
      else
        $coms[$c] = ['statut'=> $enfant['typecom'], 'name'=> $enfant['libelle'], 'crat'=> $crat];
      break;
    }
    case 'ARM': {
      $coms[$c] = ['statut'=> $enfant['typecom'], 'name'=> $enfant['libelle'], 'crat'=> $crat];
      break;
    }
    default: {
      die("typecom $enfant[typecom] non traité");
    }
  }
}
ksort($coms);
echo Yaml::dump([
    'title'=> "Fichier des communes au $params[valid] avec entrée par code INSEE des communes associées ou déléguées et des ardt. mun.",
    'created'=> date(DATE_ATOM),
    'valid'=> $params['valid'],
    'source'=> "création par traduction du fichier $params[file] de l'INSEE en utilisant la commande 'insee.php ".implode(' ', $params)."'",
    '$schema'=> 'http://id.georef.eu/comhisto/insee2/exfcoms/$schema',
    'ydADscrBhv'=> [
      'jsonLdContext'=> 'http://schema.org',
      'firstLevelType'=> 'AdministrativeArea',
      'buildName'=> [ # définition de l'affichage réduit par type d'objet, code Php par type
        'AdministrativeArea'=> '  return "$item[name] ($skey)";',
      ],
      'writePserReally'=> true,
    ],
    'contents'=> $coms
  ], 99, 2);

