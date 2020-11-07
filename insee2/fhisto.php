<?php
/*PhpDoc:
name: fhisto.php
title: fhisto.php - fabrication du fichier histo.yaml à partir de rpicom.yaml
doc: |
journal: |
  4/11/2020:
    - création
*/
ini_set('memory_limit', '2G');
//set_time_limit(2*60);

require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (php_sapi_name() <> 'cli') { // Menu en NON CLI
  if (!isset($_GET['action'])) {
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>fhisto</title></head><body>\n";
    echo "<a href='?action=histo'>Affichage de histo</a><br>\n";
    echo "<a href='?action=specs'>Affichage des specs</a><br>\n";
    die();
  }
  else {
    $action = $_GET['action'];
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>fhisto $action</title></head><body><pre>\n";
  }
}
else { // Menu en CLI
  if ($argc == 1) {
    echo "usage: php $argv[0] {option}\n";
    echo " où {option} peut prendre les valeurs suivantes:\n";
    echo "  - enregistreHisto : pour enregistrer Histo dans histo.yaml\n";
    echo "  - specs : pour générer le fichier Html des specs\n";
    die();
  }
  else {
    $action = $argv[1];
    if ($action == 'specs')
      echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>frpicom $action</title></head><body><pre>\n";
  }
}

if (is_file(__DIR__.'/rpicom.pser') && (filemtime(__DIR__.'/rpicom.pser') > filemtime(__DIR__.'/rpicom.yaml'))) {
  $rpicoms = unserialize(file_get_contents(__DIR__.'/rpicom.pser'))['contents'];
}
else {
  $rpicoms = Yaml::parseFile(__DIR__.'/rpicom.yaml');
  file_put_contents(__DIR__.'/rpicom.pser', serialize($rpicoms));
  $rpicoms = $rpicoms['contents'];
}
  
//$rpicoms = ['69123'=> $rpicoms['69123']];
//echo Yaml::dump($rpicoms, 3, 2);

$histos = []; // contenu du fichier histo en ordre chrono direct, [cinsee => [ddébut => ['évts' => évts]]]
foreach ($rpicoms as $cinsee => $rpicom) { // passage de rpicom à histo
  ksort($rpicom);
  $histo = [];
  // Premier état dans histo, généralement au 1/1/1943 sauf pour Mayotte devenu un département français le 31/03/2011
  // Si dans Rpicom l'état est vide alors je ne crèe pas cette entrée dans histo et le premier état sera plus tardif
  $dfin0 = array_keys($rpicom)[0];
  if (isset($rpicom[$dfin0]['état'])) {
    $histo[(substr($cinsee, 0, 3) == '976') ? '2011-03-31' : '1943-01-01'] = ['état'=> $rpicom[$dfin0]['état']];
  }
  foreach ($rpicom as $dfin => $val) {
    if ($dfin == 'now') break;
    $histo[$dfin]['évts'] = $val['évts'];
    unset($histo[$dfin]['évts']['type']);
    unset($histo[$dfin]['évts']['type2']);
    if ($val['après'])
      $histo[$dfin]['état'] = $val['après']; // le champ après du rpicom, plus fiable que le champ état
  }
  unset($rpicoms[$cinsee]);
  $histos[$cinsee] = $histo;
}

foreach ($histos as $cinsee => $histo) { // ajout du champ erat
  foreach ($histo as $ddebut => $version) {
    if ($crat = $version['état']['crat'] ?? null) // entité rattachée
      $histos[$crat][$ddebut]['erat'][] = $cinsee;
    elseif (isset($version['état']['nomCommeDéléguée'])) // commune déléguée propre
      $histos[$cinsee][$ddebut]['erat'][] = $cinsee;
  }
}
foreach ($histos as $cinsee => $histo) { // propagation du champ erat en cas de changement de nom
  $erat = [];
  foreach ($histo as $ddebut => $version) {
    if (!isset($version['état']) || ($version['état']['statut']<>'COM')) continue;
    if ($erat && (array_keys($version['évts'])==['changeDeNomPour'])) {
      //echo "Propagation d'erat pour $cinsee/$ddebut\n";
      $histos[$cinsee][$ddebut]['erat'] = $erat;
    }
    $erat = isset($histoD['erat']) ? $histoD['erat'] : [];
  }
}
$histos[69123]['1959-02-08']['erat'] = [69381, 69382, 69383, 69384, 69385, 69386, 69387, 69388];
$histos[69123]['1963-08-07']['erat'] = [69381, 69382, 69383, 69384, 69385, 69386, 69387, 69388];
$histos[69123]['1964-08-12']['erat'] = [69381, 69382, 69383, 69384, 69385, 69386, 69387, 69388, 69389];
  

if ($action == 'histo')
  echo Yaml::dump($histos, 3, 2);
elseif ($action == 'enregistreHisto') {
  // code Php intégré dans le document pour définir l'affichage résumé de la commune
  $buildNameAdministrativeArea = <<<'EOT'
krsort($item);
$first = true;
foreach($item as $ddebut => $version) {
  if (isset($item[$ddebut]['état'])) {
    if ($first)
      return $item[$ddebut]['état']['name']." ($skey)";
    else
      return '<s>'.$item[$ddebut]['état']['name']." ($skey)</s>";
  }
  $first = false;
}
return 'XXXX';
EOT;
  file_put_contents(
    __DIR__.'/histo.yaml',
    Yaml::dump(
      [
        'title'=> "Référentiel historique des communes",
        'description'=> "Voir la documentation sur https://github.com/benoitdavidfr/comhisto",
        'created'=> date(DATE_ATOM),
        'valid'=> '2020-01-01',
        '$schema'=> 'http://id.georef.eu/comhisto/insee2/exhisto/$schema',
        'ydADscrBhv'=> [
          'jsonLdContext'=> 'http://schema.org',
          'firstLevelType'=> 'AdministrativeArea',
          'buildName'=> [ // définition de l'affichage réduit par type d'objet, code Php par type
            'AdministrativeArea'=> $buildNameAdministrativeArea,
          ],
          'writePserReally'=> true,
        ],
        'contents'=> $histos,
      ], 4, 2)
  );
}