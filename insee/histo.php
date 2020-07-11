<?php
/*PhpDoc:
name: histo.php
title: histo.php - génération du fichier histo
doc: |
  Production d'un document Yaml de l'historique de chaque code Insee à partir des données de mouvements Insee et de l'état au 1/1/2020.
  Correction des incohérences constatées et de quelques erreurs flagrantes.
journal: |
  9-11/7/2020:
    - reconstruction à partir de rpicom
includes:
  - menu.inc.php
  - base.inc.php
  - grpmvts.inc.php
  - mgrpmvts.inc.php
screens:
classes:
functions:
*/
ini_set('memory_limit', '2G');

require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

require_once __DIR__.'/menu.inc.php';

$menu = new Menu([
  // [{action} => [ 'argNames' => [{argName}], 'actions'=> [{label}=> [{argValue}]] ]]
  'buildState'=> [
    // affichage Yaml de l'état des communes par traduction du fichier INSEE
    'argNames'=> ['state', 'file', 'format'], // liste des noms des arguments en plus de action
     // actions prédéfinies
    'actions'=> [
      "affichage de l'état au 1/1/2020"=> [ '2020-01-01', '../data/communes2020.csv', 'csv'],
      /*"affichage de l'état au 1/1/2019"=> [ '2019-01-01', 'communes-01012019.csv', 'csv'],
      "affichage de l'état au 1/1/2018"=> [ '2018-01-01', 'France2018.txt', 'txt'],
      "affichage de l'état au 1/1/2017"=> [ '2017-01-01', 'France2017.txt', 'txt'],
      "affichage de l'état au 1/1/2010"=> [ '2010-01-01', 'France2010.txt', 'txt'],
      "affichage de l'état au 1/1/2000"=> [ '2000-01-01', 'France2000.txt', 'txt']*/
    ],
  ],
  'check'=> [
    // vérifie la conformité du fichier à son schéma
    'argNames'=> ['file'],
     // actions prédéfinies
    'actions'=> [
      "conformité com20200101.yaml à son schema"=> [ 'com20200101.yaml'],
    ],
  ],
  'brpicom'=> [
    'argNames'=> [],
    'actions'=> [
      "Fabrication du Rpicom"=> [],
    ],
  ],
  'détailleEvt'=> [
    'argNames'=> [],
    'actions'=> [
      "détailleEvt"=> [],
    ],
  ],
  'bhisto'=> [
    'argNames'=> [],
    'actions'=> [
      "Fabrication du histo"=> [],
    ],
  ],
], $argc ?? 0, $argv ?? []);

{/*PhpDoc: screens
name: Menu
title: Menu - permet d'exécuter différentes actions définies
*/}
if (!isset($_GET['action'])) { // Menu
  $menu->show();
  die();
}

// convertit un enregistrement txt en csv, cad de l'ancien format INSEE dans le nouveau
function conv2Csv(array $rec): array {
  switch($rec['actual']) {
    case '1': // commune simple
      $rec['typecom'] = 'COM'; break;
    case '2': // commune « associée »
      $rec['typecom'] = 'COMA'; break;
    case '5': // arrondissement municipal
      $rec['typecom'] = 'ARM'; break;
    case '6': // Commune déléguée
      $rec['typecom'] = 'COMD'; break;
    default:
      $rec['typecom'] = 'X'; break;
  }
  $rec['com'] = "$rec[dep]$rec[com]";
  $artmin = '';
  if ($rec['artmin']) {
    $artmin = substr($rec['artmin'], 1, strlen($rec['artmin'])-2); // supp ()
    if (!in_array($artmin, ["L'"]))
      $artmin .= ' ';
  }
  $rec['libelle'] = $artmin.$rec['nccenr'];
  
  $rec['comparent'] = $rec['pole'];
  return $rec;
}

{/*PhpDoc: functions
name: addValToArray
title: "function addValToArray($val, &$array): void - ajoute $val à $array, si $array existe alors $val est ajouté, sinon $array est créé à [ $val ]"
doc: |
  $array n'existe pas ou contient un array
  Le paramètre $array n'existe pas forcément. Par exemple si $a = [] on peut utiliser $a['key'] comme paramètre.
*/}
function addValToArray($val, &$array): void {
  if (!isset($array))
    $array = [ $val ];
  else
    $array[] = $val;
}

{/*PhpDoc: functions
name: addScalarToArrayOrScalar
title: "function addScalarToArrayOrScalar($scalar, &$arrayOrScalar): void - ajoute $scalar à $arrayOrScalar"
doc: |
  Dans cette version, $scalar est un scalaire et $arrayOrScalar n'existe pas ou contient un scalaire ou un array
  si $arrayOrScalar n'existe pas alors il prend la valeur $scalar
  Sinon si $arrayOrScalar est un scalaire alors il devient un array contenant l'ancienne valeur et la nouvelle
  sinon si $arrayOrScalar est un array alors $scalar lui est ajouté
  sinon exception
  Le paramètre $arrayOrScalar n'existe pas forcément. Par exemple si $a = [] on peut utiliser $a['key'] comme paramètre.
*/}
function addScalarToArrayOrScalar($scalar, &$arrayOrScalar): void {
  if (!is_scalar($scalar))
    throw new Exception("Erreur dans addScalarToArrayOrScalar(), le 1er paramètre doit être un scalaire");
  if (!isset($arrayOrScalar))
    $arrayOrScalar = $scalar;
  elseif (is_scalar($arrayOrScalar))
    $arrayOrScalar = [$arrayOrScalar, $scalar];
  elseif (is_array($arrayOrScalar))
    $arrayOrScalar[] = $scalar;
  else
    throw new Exception("Erreur dans addScalarToArrayOrScalar(), le 2e paramètre doit être indéfini, un scalaire ou un array");
}
if (0) { // Test addScalarToArrayOrScalar()
  echo "<pre>\n";
  addScalarToArrayOrScalar('val1', $array['key']); echo Yaml::dump(['addScalarToArrayOrScalar'=> $array]),"<br>\n";
  addScalarToArrayOrScalar('val2', $array['key']); echo Yaml::dump(['addScalarToArrayOrScalar'=> $array]),"<br>\n";
  die("Fin test addValToArrayOrScalar");
}

{/*PhpDoc: functions
name: union_keys
title: "function union_keys(array $a, array $b): array - renvoie l'union des clés de $a et $b, en gardant d'abord l'ordre du + long et en ajoutant à la fin celles du + court"
*/}
function union_keys(array $a, array $b): array {
  // $a est considéré comme le + long, si non on inverse
  if (count($b) > count($a))
    return union_keys($b, $a);
  // j'ajoute à la fin de $a les clés de $b absentes de $a
  foreach (array_keys($b) as $kb) {
    if (!array_key_exists($kb, $a))
      $a[$kb] = 1;
  }
  return array_keys($a);
}
if (0) { // Test de union_keys()
  echo '<pre>';
  echo "arrays identiques\n";
  print_r(union_keys(
    ['c'=>1,'b'=>1,'a'=>1,'g'=>1,'u'=>1,'d'=>1],
    ['c'=>1,'b'=>1,'a'=>1,'g'=>1,'u'=>1,'d'=>1]
  ));
  echo "zyx identiques\n";
  print_r(union_keys(
    ['z'=>1,'y'=>1,'x'=>1,'g'=>1,'u'=>1,'d'=>1],
    ['z'=>1,'y'=>1,'x'=>1,'d'=>1,'u'=>1]
  ));
  print_r(union_keys(
    ['a'=>1,'b'=>1,'c'=>1,'g'=>1,'u'=>1,'d'=>1],
    ['x'=>1,'b'=>1,'c'=>1,'d'=>1,'u'=>1]
  ));
  die("Fin Test de union_keys");
}

{/*PhpDoc: functions
name: readfiles
title: function readfiles($dir, $recursive=false) - Lecture des fichiers locaux du répertoire $dir
doc: |
  Le système d'exploitation utilise ISO 8859-1, toutes les données sont gérées en UTF-8
  Si recursive est true alors renvoie l'arbre
*/}
function readfiles($dir, $recursive=false) { // lecture du nom, du type et de la date de modif des fichiers d'un rép.
  if (!$dh = opendir(utf8_decode($dir)))
    die("Ouverture de $dir impossible");
  $files = [];
  while (($filename = readdir($dh)) !== false) {
    if (in_array($filename, ['.','..']))
      continue;
    $filetype = filetype(utf8_decode($dir).'/'.$filename);
    $file = [
      'name'=>utf8_encode($filename),
      'type'=>$filetype, 
      'mdate'=>date ("Y-m-d H:i:s", filemtime(utf8_decode($dir).'/'.$filename))
    ];
    if (($filetype=='dir') && $recursive)
      $file['content'] = readfiles($dir.'/'.utf8_encode($filename), $recursive);
    $files[$file['name']] = $file;
  }
  closedir($dh);
  return $files;
}

function ypath(array $yaml, array $path) {
  if (!$path)
    return $yaml;
  else {
    $key0 = array_shift($path);
    return ypath($yaml[$key0], $path);
  }
}


if ($_GET['action'] == 'buildState') { // affichage Yaml de l'état des communes par traduction du fichier INSEE
  if (php_sapi_name() <> 'cli')
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>buildState $_GET[file]</title></head><body>\n",
         "<h3>lecture du fichier $_GET[file]</h3><pre>\n";
  //die("Fin ligne ".__LINE__);
  $coms = []; // [cinsee => record + children] 
  $enfants = []; // [cinsee => record] 
  if (!($file = @fopen($_GET['file'], 'r')))
    die("Erreur sur l'ouverture du fichier '$_GET[file]'\n");
  $sep = $_GET['format'] == 'csv' ? ',' : "\t";
  $headers = fgetcsv($file, 0, $sep);
  // un des fichiers comporte des caractères parasites au début ce qui perturbe la détection des headers
  foreach ($headers as $i => $header)
    if (preg_match('!"([^"]+)"!', $header, $matches))
      $headers[$i] = $matches[1];
  //echo "<pre>headers="; print_r($headers); echo "</pre>\n";
  while($record = fgetcsv($file, 0, $sep)) {
    //echo "<pre>record="; print_r($record); echo "</pre>\n";
    $rec = [];
    foreach ($headers as $i => $header) {
      $rec[strtolower($header)] = $_GET['format'] == 'csv' ?
          $record[$i] :
            mb_convert_encoding ($record[$i], 'UTF-8', 'Windows-1252');
    }
    if ($_GET['format'] == 'txt') {
      $rec = conv2Csv($rec);
      if ($rec['typecom'] == 'X')
        continue;
    }
    //if ($rec['com'] == '45307') { echo "<pre>rec="; print_r($rec); echo "</pre>\n"; }
    //echo "$rec[nccenr] ($typecom $rec[com])<br>\n";
    if (!$rec['comparent']) {
      //$coms[$rec['com']] = ['name'=> $rec['nccenr']];
      $coms[$rec['com']] = ['name'=> $rec['libelle']];
    }
    else {
      $enfants[$rec['com']] = $rec;
    }
    //if ($nbrec >= 10) die("<b>die nbrec >= 10</b>");
  }
  foreach ($enfants as $c => $enfant) {
    $comparent = $enfant['comparent'];
    if ($enfant['typecom'] == 'COMA')
      $childrenTag = ['aPourAssociées','estAssociéeA']; 
    elseif ($enfant['typecom'] == 'COMD')
      $childrenTag = ['aPourDéléguées', 'estDéléguéeDe']; 
    elseif ($enfant['typecom'] == 'ARM')
      $childrenTag = ['aPourArrondissementsMunicipaux', 'estArrondissementMunicipalDe']; 
    $coms[$comparent][$childrenTag[0]][$c] = ['name'=> $enfant['libelle']];
    if ($c <> $comparent)
      $coms[$c] = [$childrenTag[1] => $comparent];
  }
  ksort($coms);
  // Post-traitement - suppr. de 2 rétablisements ambigus c^té INSEE et contredits par IGN et wikipédia
  if ($_GET['state'] == '2020-01-01') {
    // suppr. du rétablisement de 14114 Bures-sur-Dives comme c. assciée de 14712
    unset($coms['14114']);
    unset($coms['14712']['aPourAssociées']);
    // suppr. du rétablisement de Gonaincourt comme c. déléguée de 52064
    unset($coms['52224']);
    unset($coms['52064']['aPourDéléguées']['52224']);
  }
  if (0) { // post-traitement - suppression des communes simples ayant uniquement un nom
    foreach ($coms as $c => $com) {
      if (isset($com['name']) && (count(array_keys($com))==1))
        unset($coms[$c]);
    }
  }
  $buildNameAdministrativeArea = <<<'EOT'
    if (isset($item['name']))
      return "$item[name] ($skey)";
    elseif (isset($item['estAssociéeA']))
      return "$skey estAssociéeA $item[estAssociéeA]";
    elseif (isset($item['estDéléguéeDe']))
      return "$skey estDéléguéeDe $item[estDéléguéeDe]";
    elseif (isset($item['estArrondissementMunicipalDe']))
      return "$skey estArrondissementMunicipalDe $item[estArrondissementMunicipalDe]";
    else
      return "none";
EOT;
  echo Yaml::dump([
      'title'=> "Fichier des communes au $_GET[state] avec entrée par code INSEE des communes associées ou déléguées et des ardt. mun.",
      'created'=> date(DATE_ATOM),
      'source'=> "création par traduction du fichier $_GET[file] de l'INSEE  \n"
."en utilisant la commande 'index.php ".implode(' ', $_GET)."'\n",
      '$schema'=> 'http://id.georef.eu/rpicom/exfcoms/$schema',
      'ydADscrBhv'=> [
        'jsonLdContext'=> 'http://schema.org',
        'firstLevelType'=> 'AdministrativeArea',
        'buildName'=> [ # définition de l'affichage réduit par type d'objet, code Php par type
          'AdministrativeArea'=> $buildNameAdministrativeArea,
        ],
        'writePserReally'=> true,
      ],
      'contents'=> $coms
    ], 99, 2);
  die();
}

/*if ($_GET['action'] == 'check') { // vérifie la conformité du fichier à son schéma
  require_once __DIR__.'/../../inc.php';
  $docid = 'rpicom/'.substr($_GET['file'], 0, strrpos($_GET['file'], '.'));
  //echo "docid=$docid\n";
  $doc = new_doc($docid, 'pub');
  $doc->checkSchemaConformity('/');
  die();
}*/

// Attention base.inc.php et YamDoc sont incompatibles
require_once __DIR__.'/base.inc.php';

// détaille les évts de rattachement et d'absorption vus des c. de rattachement
if ($_GET['action'] == 'détailleEvt') {
  /*
  évènement/sAssocieA => évènementDétaillé/prendPourAssociées
  évènement/fusionneDans => évènementDétaillé/absorbe
  évènement/devientDéléguéeDe => évènementDétaillé/délègueA
  évènement/seFondDans => évènementDétaillé/absorbe
  évènement/rétablieCommeSimpleDe => ['évènementDétaillé/rétablitCommeSimple
  */
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>détailleEvt</title></head><body><pre>\n";
  $rpicomBase = new Base(__DIR__.'/rpicom', new Criteria(['not']));
  //$rpicomBase = new Base(__DIR__.'/rpicomtest', new Criteria(['not']));
  $rpicoms = $rpicomBase->contents();
  // supprime des évènementDétaillés et les réinitialise pour les c. déléguées propres
  foreach ($rpicoms as $id => $rpicom) {
    foreach ($rpicom as $dv => $version) {
      unset($rpicoms[$id][$dv]['évènementDétaillé']);
      if (($version['évènement'] ?? null) == 'Se crée en commune nouvelle avec commune déléguée propre') {
        addValToArray($id, $rpicoms[$id][$dv]['évènementDétaillé']['délègueA']);
        $rpicomBase->$id = $rpicoms[$id];
      }
    }
    $rpicomBase->$id = $rpicoms[$id];
  }
  // balaie les c. rattachées ou absorbées pour détailler l'évt de rattachement/absorption sur la c. de ratt./absorbante
  foreach ($rpicoms as $id => $rpicom) {
    foreach ($rpicom as $dv => $version) {
      if ($cratid = $version['évènement']['sAssocieA'] ?? null) {
        addValToArray($id, $rpicoms[$cratid][$dv]['évènementDétaillé']['prendPourAssociées']);
        $rpicomBase->$cratid = $rpicoms[$cratid];
      }
      if ($cratid = $version['évènement']['fusionneDans'] ?? null) {
        addValToArray($id, $rpicoms[$cratid][$dv]['évènementDétaillé']['absorbe']);
        $rpicomBase->$cratid = $rpicoms[$cratid];
      }
      if ($cratid = $version['évènement']['devientDéléguéeDe'] ?? null) {
        addValToArray($id, $rpicoms[$cratid][$dv]['évènementDétaillé']['délègueA']);
        $rpicomBase->$cratid = $rpicoms[$cratid];
      }
      if ($cratid = $version['évènement']['seFondDans'] ?? null) {
        addValToArray($id, $rpicoms[$cratid][$dv]['évènementDétaillé']['absorbe']);
        $rpicomBase->$cratid = $rpicoms[$cratid];
      }
      if ($cratid = $version['évènement']['rétablieCommeSimpleDe'] ?? null) {
        addValToArray($id, $rpicoms[$cratid][$dv]['évènementDétaillé']['rétablitCommeSimple']);
        $rpicomBase->$cratid = $rpicoms[$cratid];
      }
    }
  }
  // corrige les evts détaillés affectés par erreur à une date alors qu'ils auraient du être affecté au bis
  $évènements = [
    'Se crée en commune nouvelle avec commune déléguée propre',
    'Prend des c. associées et/ou absorbe des c. fusionnées',
    'Absorbe certaines de ses c. rattachées ou certaines de ses c. associées deviennent déléguées',
    'Se crée en commune nouvelle',
    'Commune rétablissant des c. rattachées ou fusionnées',
  ];
  foreach ($rpicoms as $id => $rpicom) {
    foreach ($rpicom as $dv => $version) {
      if (isset($rpicom["$dv-bis"])) {
        if (isset($version['évènementDétaillé']) && !in_array($version['évènement'], $évènements)) {
          $rpicom["$dv-bis"]['évènementDétaillé'] = $version['évènementDétaillé'];
          unset($rpicom[$dv]['évènementDétaillé']);
          //echo "Pour $id, transfert détails de $dv sur $dv-bis pour évènement='",json_encode($version['évènement']),"'\n";
          $rpicomBase->$id = $rpicom;
        }
      }
    }
  }
  
  if (0)
  foreach ($rpicoms as $id => $rpicom) { // affiche les évènementsDétaillés
    foreach ($rpicom as $dv => $version) {
      if (isset($rpicoms[$id][$dv]['évènementDétaillé'])) {
        echo Yaml::dump([$id => [$dv => ['évènementDétaillé'=> $rpicoms[$id][$dv]['évènementDétaillé']]]]);
      }
    }
  }
  $rpicomBase->storeMetadata(array_merge($rpicomBase->metadata(), ['évènementsDétaillésAjoutés' => date(DATE_ATOM)]));
  $rpicomBase->writeAsYaml();
  $rpicomBase->save();
  die("Fin détailleEvt dans '".$rpicomBase->metadata()['title']."'\n");
}

require_once __DIR__.'/grpmvts.inc.php';
require_once __DIR__.'/mgrpmvts.inc.php';

// Initialisation du RPICOM avec les communes du 1/1/2020 comme 'now'
function initRpicomFrom(string $compath, Criteria $trace): Base {
  // code Php intégré dans le document pour définir l'affichage résumé de la commune
  $buildNameAdministrativeArea = <<<'EOT'
if (isset($item['now']['name']))
  return $item['now']['name']." ($skey)";
else
  return '<s>'.array_values($item)[0]['name']." ($skey)</s>";
EOT;
  $rpicom = [
    'title'=> "Référentiel rpicom",
    'description'=> "Voir la documentation sur https://github.com/benoitdavidfr/yamldocs/tree/master/rpicom",
    'created'=> date(DATE_ATOM),
    'valid'=> '2020-01-01',
    '$schema'=> 'http://id.georef.eu/rpicom/exrpicom/$schema',
    'ydADscrBhv'=> [
      'jsonLdContext'=> 'http://schema.org',
      'firstLevelType'=> 'AdministrativeArea',
      'buildName'=> [ // définition de l'affichage réduit par type d'objet, code Php par type
        'AdministrativeArea'=> $buildNameAdministrativeArea,
      ],
      'writePserReally'=> true,
    ],
    'contents'=> [],
  ];
  $rpicom = new Base($rpicom, $trace);
  if (!is_file("$compath.yaml")) {
    die("Erreur $compath.yaml n'existe pas\n");
  }
  $coms = new Base($compath, new Criteria(['not'])); // Lecture de com20200101.yaml dans $coms
  foreach ($coms->contents() as $idS => $comS) {
    //echo Yaml::dump([$id => $com]);
    if (!isset($comS['name'])) continue;
    foreach ($comS['aPourAssociées'] ?? [] as $id => $com) {
      $rpicom->$id = ['now'=> [
        'name'=> $com['name'],
        'estAssociéeA'=> $idS,
      ]];
    }
    unset($comS['aPourAssociées']);
    foreach ($comS['aPourDéléguées'] ?? [] as $id => $com) {
      if ($id <> $idS)
        $rpicom->$id = ['now'=> [
          'name'=> $com['name'],
          'estDéléguéeDe'=> $idS,
        ]];
      else
        $comS['commeDéléguée'] = ['name'=> $com['name']];
    }
    unset($comS['aPourDéléguées']);
    foreach ($comS['aPourArrondissementsMunicipaux'] ?? [] as $id => $com) {
      $rpicom->$id = ['now'=> [
        'name'=> $com['name'],
        'estArrondissementMunicipalDe'=> $idS,
      ]];
    }
    unset($comS['aPourArrondissementsMunicipaux']);
    $rpicom->$idS = ['now'=> $comS];
    unset($coms->$idS);
  }
  unset($coms);
  return $rpicom;
}

{/*PhpDoc: screens
name: brpicom
title: brpicom - construction du Rpicom
doc: |
*/}
if ($_GET['action'] == 'brpicom') { // construction du Rpicom v2
  if (php_sapi_name() <> 'cli')
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>brpicom</title></head><body><pre>\n";
  //$trace = new Criteria([]); // aucun critère, tout est affiché
  $trace = new Criteria(['not']); // rien n'est affiché
  //$trace = new Criteria(['mod'=> ['32']]);
  //$trace = new Criteria(['mod'=> ['not'=> ['10','21','31','20','30','41','33','34','50','32']]]); 

  // fabrication de la version initiale du RPICOM avec les communes du 1/1/2020 comme 'now'
  $rpicoms = initRpicomFrom(__DIR__.'/com20200101', new Criteria(['not']));
  
  $mvtcoms = GroupMvts::readMvtsInseePerDate(__DIR__.'/../data/mvtcommune2020.csv'); // lecture csv ds $mvtcoms
  krsort($mvtcoms); // tri par ordre chrono inverse
  foreach($mvtcoms as $date_eff => $mvtcomsD) {
    foreach (GroupMvts::buildGroups($mvtcomsD) as $group) {
      $group = $group->factAvDefact();
      if ($trace->is(['mod'=> $group->mod()]))
        echo Yaml::dump(['$group'=> $group->asArray()], 3, 2);
      $group->addToRpicom($rpicoms, $trace);
    }
  }
  // Post-traitements
  if (0)
    $rpicoms->startExtractAsYaml();
  foreach ($rpicoms->contents() as $id => $rpicom) { // Post-traitements no 1 - remplacer les unknown et 2 - Mayotte
    // Post-traitement no 1 pour remplacer les associées unknown à partir des associations effectuées précédemment
    // + idem pour les déléguées
    $unknownAssosVdate = null;
    $unknownDelegVdate = null;
    foreach ($rpicom as $datev => $vcom) {
      if ($unknownAssosVdate && isset($vcom['évènement']['sAssocieA'])) {
        $rpicom[$unknownAssosVdate]['estAssociéeA'] = $vcom['évènement']['sAssocieA'];
        $rpicoms->$id = $rpicom;
        $unknownAssosVdate = null;
      }
      if ($unknownAssosVdate && isset($vcom['évènement']['changeDeRattachementPour'])) {
        $rpicom[$unknownAssosVdate]['estAssociéeA'] = $vcom['évènement']['changeDeRattachementPour'];
        $rpicoms->$id = $rpicom;
        $unknownAssosVdate = null;
      }
      if (isset($vcom['estAssociéeA']) && ($vcom['estAssociéeA'] == 'unknown')) {
        $unknownAssosVdate = $datev;
      }
      if ($unknownDelegVdate && isset($vcom['évènement']['devientDéléguéeDe'])) {
        $rpicom[$unknownDelegVdate]['estDéléguéeDe'] = $vcom['évènement']['devientDéléguéeDe'];
        $rpicoms->$id = $rpicom;
        $unknownDelegVdate = null;
      }
      if ($unknownDelegVdate && isset($vcom['évènement']['resteDéléguéeDe'])) {
        $rpicom[$unknownDelegVdate]['estDéléguéeDe'] = $vcom['évènement']['resteDéléguéeDe'];
        $rpicoms->$id = $rpicom;
        $unknownDelegVdate = null;
      }
      if (isset($vcom['estDéléguéeDe']) && ($vcom['estDéléguéeDe'] == 'unknown')) {
        $unknownDelegVdate = $datev;
      }
    }
    // Post-traitement no 2 pour indiquer que Mayote est devenu DOM le 31 mars 2011
    if (substr($id, 0, 3) == '976') {
      $rpicom['2011-03-31'] = ['évènement' => "Entre dans le périmètre du référentiel"];
      $rpicoms->$id = $rpicom;
    }
  }
  // Post-traitement no 3 pour indiquer que Saint-Martin et Saint-Barthélemy ont été DOM jusqu'au 15 juillet 2007
  foreach (['97123'=> "Saint-Barthélemy", '97127'=> "Saint-Martin"] as $id => $name) {
    $rpicoms->$id = [
      '2007-07-15' => [
        'évènement' => "Sort du périmètre du référentiel",
        'name'=> $name,
      ]
    ];
  }
  if (1) { // Post-trait. no 4 - supp. du rétablisst de 14114 Bures-sur-Dives ambigue sur site INSEE, contredit par IGN et wikipédia
    echo "Suppression du rétablisst de 14114 Bures-sur-Dives ambigue sur site INSEE, contredit par IGN et wikipédia\n";
    $id = '14114';
    $rpicom = $rpicoms->$id;
    unset($rpicom['now']);
    unset($rpicom['2019-12-31']);
    $rpicoms->$id = $rpicom;
  }
  if (1) { // Post-traitement no 5 pour corriger un Bug INSEE sur 89325 Ronchères
    // L'évènement d'association du 1972-12-01 et suivi d'un rétablieCommeAssociéeDe de 1977-01-01, c'est impossible !
    // Le site INSEE confirme ces évènements dont l'enchainement est interdit.
    // Je transforme donc l'association (sAssocieA) du 1972-12-01 en fusion (fusionneDans)
    echo "Sur Ronchères (89325), sAssocieA@1972-12-01 incompatible avec rétablieCommeAssociéeDe@1977-01-01 est changée en fusionneDans\n";
    $id = '89325';
    $rpicom = $rpicoms->$id;
    $rpicom['1972-12-01']['évènement'] = ['fusionneDans' => $rpicom['1972-12-01']['évènement']['sAssocieA']];
    $rpicoms->$id = $rpicom;
  }
  if (1) { // Idem pour Septfonds (89389)
    echo "Sur Septfonds (89389), sAssocieA@1972-12-01 incompatible avec rétablieCommeAssociéeDe@1977-01-01 est changée en fusionneDans\n";
    $id = '89389';
    $rpicom = $rpicoms->$id;
    $rpicom['1972-12-01']['évènement'] = ['fusionneDans' => $rpicom['1972-12-01']['évènement']['sAssocieA']];
    $rpicoms->$id = $rpicom;
  }
  if (0)
    $rpicoms->showExtractAsYaml(5, 2);
  $rpicoms->ksort(); // tri du Yaml sur le code INSEE de commune
  $rpicoms->writeAsYaml('rpicom');
  die("Fin brpicom ok, rpicom sauvé dans rpicom.yaml\n");
}

// détaille les évts de rattachement et d'absorption vus des c. de rattachement
if ($_GET['action'] == 'détailleEvt') {
  /*
  évènement/sAssocieA => évènementDétaillé/prendPourAssociées
  évènement/fusionneDans => évènementDétaillé/absorbe
  évènement/devientDéléguéeDe => évènementDétaillé/délègueA
  évènement/seFondDans => évènementDétaillé/absorbe
  évènement/rétablieCommeSimpleDe => ['évènementDétaillé/rétablitCommeSimple
  */
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>détailleEvt</title></head><body><pre>\n";
  $rpicomBase = new Base(__DIR__.'/rpicom', new Criteria(['not']));
  //$rpicomBase = new Base(__DIR__.'/rpicomtest', new Criteria(['not']));
  $rpicoms = $rpicomBase->contents();
  // supprime des évènementDétaillés et les réinitialise pour les c. déléguées propres
  foreach ($rpicoms as $id => $rpicom) {
    foreach ($rpicom as $dv => $version) {
      unset($rpicoms[$id][$dv]['évènementDétaillé']);
      if (($version['évènement'] ?? null) == 'Se crée en commune nouvelle avec commune déléguée propre') {
        addValToArray($id, $rpicoms[$id][$dv]['évènementDétaillé']['délègueA']);
        $rpicomBase->$id = $rpicoms[$id];
      }
    }
    $rpicomBase->$id = $rpicoms[$id];
  }
  // balaie les c. rattachées ou absorbées pour détailler l'évt de rattachement/absorption sur la c. de ratt./absorbante
  foreach ($rpicoms as $id => $rpicom) {
    foreach ($rpicom as $dv => $version) {
      if ($cratid = $version['évènement']['sAssocieA'] ?? null) {
        addValToArray($id, $rpicoms[$cratid][$dv]['évènementDétaillé']['prendPourAssociées']);
        $rpicomBase->$cratid = $rpicoms[$cratid];
      }
      if ($cratid = $version['évènement']['fusionneDans'] ?? null) {
        addValToArray($id, $rpicoms[$cratid][$dv]['évènementDétaillé']['absorbe']);
        $rpicomBase->$cratid = $rpicoms[$cratid];
      }
      if ($cratid = $version['évènement']['devientDéléguéeDe'] ?? null) {
        addValToArray($id, $rpicoms[$cratid][$dv]['évènementDétaillé']['délègueA']);
        $rpicomBase->$cratid = $rpicoms[$cratid];
      }
      if ($cratid = $version['évènement']['seFondDans'] ?? null) {
        addValToArray($id, $rpicoms[$cratid][$dv]['évènementDétaillé']['absorbe']);
        $rpicomBase->$cratid = $rpicoms[$cratid];
      }
      if ($cratid = $version['évènement']['rétablieCommeSimpleDe'] ?? null) {
        addValToArray($id, $rpicoms[$cratid][$dv]['évènementDétaillé']['rétablitCommeSimple']);
        $rpicomBase->$cratid = $rpicoms[$cratid];
      }
    }
  }
  // corrige les evts détaillés affectés par erreur à une date alors qu'ils auraient du être affecté au bis
  $évènements = [
    'Se crée en commune nouvelle avec commune déléguée propre',
    'Prend des c. associées et/ou absorbe des c. fusionnées',
    'Absorbe certaines de ses c. rattachées ou certaines de ses c. associées deviennent déléguées',
    'Se crée en commune nouvelle',
    'Commune rétablissant des c. rattachées ou fusionnées',
  ];
  foreach ($rpicoms as $id => $rpicom) {
    foreach ($rpicom as $dv => $version) {
      if (isset($rpicom["$dv-bis"])) {
        if (isset($version['évènementDétaillé']) && !in_array($version['évènement'], $évènements)) {
          $rpicom["$dv-bis"]['évènementDétaillé'] = $version['évènementDétaillé'];
          unset($rpicom[$dv]['évènementDétaillé']);
          //echo "Pour $id, transfert détails de $dv sur $dv-bis pour évènement='",json_encode($version['évènement']),"'\n";
          $rpicomBase->$id = $rpicom;
        }
      }
    }
  }
  
  if (0)
  foreach ($rpicoms as $id => $rpicom) { // affiche les évènementsDétaillés
    foreach ($rpicom as $dv => $version) {
      if (isset($rpicoms[$id][$dv]['évènementDétaillé'])) {
        echo Yaml::dump([$id => [$dv => ['évènementDétaillé'=> $rpicoms[$id][$dv]['évènementDétaillé']]]]);
      }
    }
  }
  $rpicomBase->storeMetadata(array_merge($rpicomBase->metadata(), ['évènementsDétaillésAjoutés' => date(DATE_ATOM)]));
  $rpicomBase->writeAsYaml();
  $rpicomBase->save();
  die("Fin détailleEvt dans '".$rpicomBase->metadata()['title']."'\n");
}

{/*PhpDoc: screens
name: bhisto
title: bhisto - construction du fichier insee
doc: |
  {cinsee}: [
    {date}: [
      'evts'=> [{evt}], -- évts modificatifs ou de création ou absence en cas d'état initial
      'etat'=> [ -- état après l'évènement ou état initial sans évènement ou absence d'état en cas d'évènement de disparition
        'name'=> name,
        'statut'=> (COMS|COMA|COMD|COMM|ARDM),
        'crat'=> crat,
      ]
    ]
  ]
*/}
if ($_GET['action'] == 'bhisto') { // construction du fichier histo.yaml
  if (php_sapi_name() <> 'cli')
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>binsee</title></head><body><pre>\n";
  $rpicoms = new Base('rpicom', new Criteria(['not'])); // Lecture de rpicom.yaml dans $$rpicoms
  $histo = [];
  //print_r($rpicoms);
  foreach ($rpicoms->contents() as $cinsee => $rpicom) {
    //if (!in_array($cinsee, ['01015','01079','01283',01217','78001','13201','14513','55273','55386','91001'])) continue;
    echo Yaml::dump(['initial'=> [$cinsee => $rpicom]], 3);
    // transforme estAssociéeA/estDéléguéeDe/estArrondissementMunicipalDe en statut/crat
    // traite aussi le cas di statut mixte Simple + Déléguée
    foreach ($rpicom as $dfin => $val) {
      foreach (['estAssociéeA'=>'COMA', 'estDéléguéeDe'=>'COMD', 'estArrondissementMunicipalDe'=>'ARDM'] as $key => $statut) {
        if (isset($val[$key])) {
          $val['statut'] = $statut;
          $val['crat'] = $val[$key];
          unset($val[$key]);
        }
      }
      if (isset($val['commeDéléguée'])) // statut mixte Simple + Déléguée
        $val['statut'] = 'COMM';
      elseif (isset($val['name']) && !isset($val['statut']))
        $val['statut'] = 'COMS';
      unset($val['après']);
      $rpicom[$dfin] = $val;
    }

    // transforme évènement | évènementDétaillé -> evts
    foreach ($rpicom as $dfin => $val) {
      $evt = $val['évènementDétaillé'] ?? $val['évènement'] ?? [];
      if ($evt) {
        $val['evts'] = $evt;
        unset($val['évènement']);
        unset($val['évènementDétaillé']);
      }
      $rpicom[$dfin] = $val;
    }
    //echo Yaml::dump(['2'=> [$cinsee => $rpicom]], 3);
    // Transforme les date bis en liste d'évènements
    $dfins = array_keys($rpicom);
    foreach ($dfins as $ikey => $dfin) {
      if (substr($dfin, 10)=='-bis') {
        $evt1 = $rpicom[$dfins[$ikey]]['evts'];
        $evt2 = $rpicom[$dfins[$ikey-1]]['evts'];
        if (!is_array($evt1))
          $evt1 = [ 'label'=> $evt1 ];
        if (!is_array($evt2))
          $evt2 = [ 'label'=> $evt2 ];
        $rpicom[$dfins[$ikey-1]]['evts'] = array_merge($evt1, $evt2);
        unset($rpicom[$dfins[$ikey]]);
      }
    }
    ksort($rpicom);
    
    echo Yaml::dump(['2'=> [$cinsee => $rpicom]], 3);
    
    // passe de date de fin en date de début
    $dfins = array_keys($rpicom);
    if (isset($rpicom[$dfins[0]]['name'])) {
      $etat = ['name'=> $rpicom[$dfins[0]]['name'], 'statut'=> $rpicom[$dfins[0]]['statut']];
      if (isset($rpicom[$dfins[0]]['crat']))
        $etat['crat'] = $rpicom[$dfins[0]]['crat'];
      $rpicom['1943-01-01'] = ['etat' => $etat];
      unset($rpicom[$dfins[0]]['name']);
      unset($rpicom[$dfins[0]]['statut']);
      unset($rpicom[$dfins[0]]['crat']);
      ksort($rpicom);
      $dfins = array_keys($rpicom);
    }
    foreach ($dfins as $ikey => $dfin) {
      if ($dfin == '1943-01-01') continue;
      if (isset($dfins[$ikey+1]) && isset($rpicom[$dfins[$ikey+1]]['name'])) {
        $etat = ['name' => $rpicom[$dfins[$ikey+1]]['name'], 'statut' => $rpicom[$dfins[$ikey+1]]['statut']];
        if (isset($rpicom[$dfins[$ikey+1]]['crat']))
          $etat['crat'] = $rpicom[$dfins[$ikey+1]]['crat'];
        if (isset($rpicom[$dfins[$ikey+1]]['commeDéléguée']))
          $etat['nomCommeDéléguée'] = $rpicom[$dfins[$ikey+1]]['commeDéléguée']['name'];
        $rpicom[$dfin]['etat'] = $etat;
        unset($rpicom[$dfins[$ikey+1]]['name']);
        unset($rpicom[$dfins[$ikey+1]]['statut']);
        unset($rpicom[$dfins[$ikey+1]]['crat']);
        unset($rpicom[$dfins[$ikey+1]]['commeDéléguée']);
      }
    }
    unset($rpicom['now']);
    echo Yaml::dump(['fin'=> [$cinsee => $rpicom]], 4);
    $histo[$cinsee] = $rpicom;
  }
  
  // code Php intégré dans le document pour définir l'affichage résumé de la commune
  $buildNameAdministrativeArea = <<<'EOT'
    $ckey = array_keys($item)[0];
    if (isset($item[$ckey]['etat']))
      return $item[$ckey]['etat']['name']." ($skey)";
    else
      return '<s>'.$item[array_keys($item)[1]]['etat']['name']." ($skey)</s>";
EOT;
  file_put_contents(
    __DIR__.'/histo.yaml',
    Yaml::dump([
      'title'=> "Référentiel historique des communes",
      '@id'=> 'http://id.georef.eu/comhisto/insee/histo/',
      'description'=> "Voir la documentation sur https://github.com/benoitdavidfr/yamldocs/tree/master/comhisto",
      'created'=> date(DATE_ATOM),
      'valid'=> '2020-01-01',
      '$schema'=> 'http://id.georef.eu/comhisto/insee/exhisto/$schema',
      'ydADscrBhv'=> [
        'jsonLdContext'=> 'http://schema.org',
        'firstLevelType'=> 'AdministrativeArea',
        'buildName'=> [ // définition de l'affichage réduit par type d'objet, code Php par type
          'AdministrativeArea'=> $buildNameAdministrativeArea,
        ],
        'writePserReally'=> true,
      ],
      'contents'=> $histo,
    ], 4, 2));
  //die("Fin binsee ok\n");
  die("Fin bhisto ok, résultat écrit dans histo.yaml\n");
}

die("Aucune commande $_GET[action]\n");
