<?php
/*PhpDoc:
name: comp.php
title: comp.php - comparaison des 2 versions de mvt
doc: |
  La seule modif est à la ligne 1525
  >  34	2018-01-01	COM	14513	Pont-Farcy	COM	50649	Pont-Farcy
  <  41	2018-01-01	COM	14513	Pont-Farcy	COM	50649	Pont-Farcy
  Cette modif invalide le mvt 34 qui ne comporte plus de cheflieu

journal: |
  27/10/2020:
    - création
*/

if (php_sapi_name() <> 'cli') {
  if (!isset($_GET['action'])) {
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>mvts</title></head><body>\n";
    echo "<a href='?action=showPlainEvts'>Affichage des evts Insee simplement</a><br>\n";
    echo "<a href='?action=doublons'>Affichage des evts Insee en doublon</a><br>\n";
    echo "<a href='?action=showEvts'>Affichage des evts Insee</a><br>\n";
    echo "<a href='?action=mvts'>Affichage des mvts</a><br>\n";
    echo "<a href='?action=mvtserreurs'>Affichage des mvts non conformes aux specs</a><br>\n";
    echo "<a href='?action=rpicom'>Génération du Rpicom</a><br>\n";
    die();
  }
  else {
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>graph $_GET[action]</title></head><body><pre>\n";
  }
}
else {
  $_GET['action'] = 'cli';
}

if (!($fevts = fopen(__DIR__.'/../data/mvtcommune2020.csv', 'r')))
  die("Erreur d'ouverture du fichier CSV des mouvements\n");
if (!($fevts2 = fopen(__DIR__.'/../data2/mvtcommune2020.csv', 'r')))
  die("Erreur d'ouverture du fichier2 CSV des mouvements\n");

$nolcsv=0; // num. de ligne dans le fichier CSV, 0 est la ligne des en-têtes
$headers = fgetcsv($fevts, 0, ',');
$headers2 = fgetcsv($fevts2, 0, ',');
foreach ($headers as $i => $header)
  $headers[$i] = strtolower($header);
if ($_GET['action']=='showPlainEvts') {
  foreach ([4,5,6,10,11,12] as $i) unset($headers[$i]);
  foreach ([4,5,6,10,11,12] as $i) unset($headers2[$i]);
  echo "<table border=1><th>no</th><th>",implode('</th><th>', $headers),"</th><th></th><th>",implode('</th><th>', $headers2),"</th>\n";
}

while ($record = fgetcsv($fevts, 0, ',')) {
  $record2 = fgetcsv($fevts2, 0, ',');
  $nolcsv++;
  foreach ([4,5,6,10,11,12] as $i) unset($record[$i]);
  foreach ([4,5,6,10,11,12] as $i) unset($record2[$i]);
  if ($record2 <> $record) {
    echo "<tr><td>$nolcsv</td><td>",implode('</td><td>', $record),"</td>\n";
    echo "<td></td><td>",implode('</td><td>', $record2),"</td>\n";
  }
  echo "</tr>\n";
}

while ($record2 = fgetcsv($fevts2, 0, ',')) {
  $nolcsv++;
  echo "<tr><td>$nolcsv</td><td colspan=7></td><td>",implode('</td><td>', $record2),"</td></tr>\n";
}

if (in_array($_GET['action'], ['showPlainEvts','doublons'])) {
  die("</table>\nFin $_GET[action]\n");
}
