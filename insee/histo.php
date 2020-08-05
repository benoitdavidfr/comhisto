<?php
/*PhpDoc:
name: histo.php
title: histo.php - génération du fichier histo
doc: |
  Production d'un document Yaml de l'historique de chaque code Insee à partir des données de mouvements Insee et de l'état au 1/1/2020.
  Correction des incohérences constatées et de quelques erreurs flagrantes.
  Fonctionne en 3 étapes:
    1) fabrication du Rpicom défini par date de fin avec pseudo-date now, un evt par date et date-bis
    2) ajout d'évènements détaillés et enregistrement du résultat dans rpicomd
    3) construction du fichier histo à partir du Rpicom détaillé
journal: |
  2-4/8/2020:
    - restructuration pour simplidier les corrections
    - on distingue clairement les données dérivées pour pouvoir les refabriquer après correction
    - reste 2 pbs
      - gestion de Lyon / pose not. pb. de déduction d'erat lorsque les ardm ne change pas en même temps que la crat
      - manque un evt explicitant la supp. d'une com. déléguée propre, iluustré par 22183
      
  12/7-1/8/2020:
    - construction de mirroirs
    - amélioration de la sémantique de histo.yaml, mise au point de exhisto.yaml
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
  'showRpicom'=> [
    'argNames'=> [],
    'actions'=> [
      "Affichage du Rpicom"=> [],
    ],
  ],
  'bhisto'=> [
    'argNames'=> [],
    'actions'=> [
      "Fabrication du histo"=> [],
    ],
  ],
  'mirroirs'=> [
    'argNames'=> [],
    'actions'=> [
      "génère les mirroirs"=> [],
    ],
  ],
  'verifCond'=> [
    'argNames'=> [],
    'actions'=> [
      "Vérifie pré/post-conditions"=> [],
    ],
  ],
  'beg'=> [
    'argNames'=> [],
    'actions'=> [
      "construit exemples"=> [],
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
  $array est une référence à un array qui peut ne pas exister. Par exemple si $a = [] on peut utiliser $a['key'] comme paramètre.
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
  Dans cette version, $scalar est un scalaire et $arrayOrScalar est une référence à un scalaire ou un array
  si $arrayOrScalar ne contient aucune valeur alors elle prend la valeur $scalar
  Sinon si $arrayOrScalar contient un scalaire alors il devient un array contenant l'ancienne valeur et la nouvelle
  sinon si $arrayOrScalar contient un array alors $scalar lui est ajouté
  sinon exception
  La référence $arrayOrScalar peut ne référencer aucune valeur.
  Ainsi par exemple si $a=[] alors la réf. $a['key'] peut être utilisée comme paramètre.
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
title: "function readfiles($dir, $recursive=false): array - Lecture des fichiers locaux du répertoire $dir"
doc: |
  Le système d'exploitation utilise ISO 8859-1, toutes les données sont gérées en UTF-8
  Si recursive est true alors renvoie l'arbre
*/}
function readfiles(string $dir, bool $recursive=false): array { // lecture du nom, du type et de la date de modif des fichiers du rép.
  if (!$dh = opendir(utf8_decode($dir)))
    throw new Exception("Erreur, Ouverture de $dir impossible");
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

{/*PhpDoc: functions
name: ypath
title: "function ypath(array $yaml, array $path) - descend dans la structure $yaml en utilisant itérativement les clés"
*/}
function ypath(array $yaml, array $path) {
  while (is_array($yaml) && count($yaml)) {
    $key0 = array_shift($path);
    if (!isset($yaml[$key0]))
      return null;
    else
      $yaml = $yaml[$key0];
  }
  return $yaml;
}
if (0) {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>test ypath</title></head><body><pre>\n";
  //echo Yaml::dump(ypath(['a'=>['b'=>'c']], ['a']));
  //echo Yaml::dump(ypath(['a'=>['b'=>'c']], ['a','b']));
  //echo Yaml::dump(ypath(['a'=>['b'=>'c']], ['b']));
  echo Yaml::dump(ypath([['b'=>'c']], [0,'b']));
  die("\n<b>Fin test ypath</b>\n");
}

function echoHtmlHeader(string $title, string $start='<pre>'): void {
  if (php_sapi_name() <> 'cli')
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>$title</title></head><body>$start\n";
}


if ($_GET['action'] == 'buildState') { // affichage Yaml de l'état des communes par traduction du fichier INSEE
  echoHtmlHeader($_GET['action'], "<h3>lecture du fichier $_GET[file]</h3><pre>\n");
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
    'description'=> "Voir la documentation sur https://github.com/benoitdavidfr/comhisto",
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
if ($_GET['action'] == 'brpicom') { // construction du Rpicom 
  echoHtmlHeader($_GET['action']);
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
  }
  // Post-traitement no 2 pour indiquer que Saint-Martin et Saint-Barthélemy ont été DOM jusqu'au 15 juillet 2007
  foreach (['97123'=> "Saint-Barthélemy", '97127'=> "Saint-Martin"] as $id => $name) {
    $rpicoms->$id = [
      '2007-07-15' => [
        'évènement' => ['sortDuPérimètreDuRéférentiel'=> null],
        'name'=> $name,
      ]
    ];
  }
  if (1) { // Post-trait. no 3 - supp. du rétablisst de 14114 Bures-sur-Dives ambigue sur site INSEE, contredit par IGN et wikipédia
    echo "Suppression du rétablisst de 14114 Bures-sur-Dives ambigue sur site INSEE, contredit par IGN et wikipédia\n";
    $id = '14114';
    $rpicom = $rpicoms->$id;
    unset($rpicom['now']);
    unset($rpicom['2019-12-31']);
    $rpicoms->$id = $rpicom;
  }
  if (1) { // Post-traitement no 4 pour corriger un Bug INSEE sur 89325 Ronchères
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
  if (1) { // Ajout des 2 Evts de scission des arrdts mun. de Lyon + mirroirs
    echo "Ajout des Evts de scission des arrdts mun. de Lyon + mirroirs\n";
    $idrat = '69123';
    $rpicomrat = $rpicoms->$idrat;
    foreach ([69387 => '1959-02-08', 69385 => '1964-08-12'] as $idr => $devt) {
      $rpicom = $rpicoms->$idr;
      $rpicom[$devt] = [
        'name'=> $rpicom['now']['name'],
        'estArrondissementMunicipalDe'=> $rpicom['now']['estArrondissementMunicipalDe'],
        'évènement'=> "Se scinde pour créer un nouvel arrondissement municipal",
      ];
      $rpicoms->$idr = $rpicom;
      // L'évènement est mentionné sur la c. de rattachement
      $rpicomrat[$devt] = [
        'évènement'=> ['créationDUneRattachéeParScissionDe'=> $idr],
        'name'=> $rpicomrat['now']['name'],
      ];
    }
    krsort($rpicomrat);
    $rpicoms->$idrat = $rpicomrat;
  }
  if (0)
    $rpicoms->showExtractAsYaml(5, 2);
  $rpicoms->ksort(); // tri du Yaml sur le code INSEE de commune
  $rpicoms->writeAsYaml('rpicom');
  die("Fin brpicom ok, rpicom sauvé dans rpicom.yaml\n");
}

{/*PhpDoc: screens
name: showRpicom
title: showRpicom - affichage du Rpicom
doc: |
*/}
if ($_GET['action'] == 'showRpicom') { // affichage du Rpicom 
  echoHtmlHeader($_GET['action']);
  $rpicoms = new Base('rpicom', new Criteria(['not'])); // Lecture de rpicom.yaml dans $rpicoms
  detailleEvt($rpicoms);
  foreach ($rpicoms->contents() as $cinsee => $rpicom) {
    echo Yaml::dump([$cinsee => $rpicom], 2, 2);
  }
  die("Fin srpicom ok\n");
}

{/*PhpDoc: functions
name: detailleEvt
title: "function detailleEvt(array &$rpicoms): void - détaille les évènements et en modifie certains"
doc: |
  Part de Rpicom produit par brpicom sous la forme d'un dictionnaire [cinsee => rpicom]
  remplace les clés de certains types d'évènement
  ajoute des évènementsDétaillés aux évènements mirroirs qui sont définis par une chaine de caractères
  modifie l'objet passé en paramètre et l'enregistre dans rpicomd
*/}
function detailleEvt(array &$rpicoms): void {
  // initialise pour les c. déléguées propres
  foreach ($rpicoms as $cinsee => &$rpicom) {
    foreach ($rpicom as $dv => &$version) {
      if (($version['évènement'] ?? null) == 'Se crée en commune nouvelle avec commune déléguée propre') {
        $version['évènementDétaillé']['devientDéléguéeDe'] = $cinsee;
        addValToArray($cinsee, $version['évènementDétaillé']['prendPourDéléguées']);
      }
    }
  }
  // Renommage des clés de certains types d'évènements
  $keyModifs = [
    'quitteLeDépartementEtPrendLeCode'=>'changeDeCodePour', // utilisation du chgt de code pour exprimer la fusion de 2 entités
    'arriveDansLeDépartementAvecLeCode'=>'avaitPourCode',
    'seFondDans'=>'fusionneDans', // j'abandonne la distinction entre ces 2 types d'évts
    //'seSépareDe'=>'seDétacheDe',
    'rétablieCommeSimpleDe'=>'crééeCommeSimpleParScissionDe', // je préfère scission à rétablissemnt parfois incorrect
    'rétablieCommeAssociéeDe'=>'crééeCommeAssociéeParScissionDe',
    'rétabliCommeArrondissementMunicipalDe'=>'crééCommeArrondissementMunicipalParScissionDe',
    'changedAssociéeEnDéléguéeDe'=>'devientDéléguéeDe', // j'utilise devientDéléguéeDe même qd l'e. d'origine est déjà une ER
  ];
  foreach ($rpicoms as $id => &$rpicom) {
    foreach ($rpicom as $dv => &$version) {
      if (is_array($version['évènement'] ?? null)) {
        $srck = array_keys($version['évènement'])[0];
        if ($dstk = ($keyModifs[$srck] ?? null)) {
          $version['évènement'][$dstk] = $version['évènement'][$srck];
          unset($version['évènement'][$srck]);
        }
        elseif ($srck == 'créationDUneRattachéeParScissionDe') {
          $version['évènement']['estModifiéeIndirectementPar'] = [$version['évènement'][$srck]];
          unset($version['évènement'][$srck]);
        }
      }
    }
  }
  // balaie les c. rattachées ou absorbées pour détailler l'évt de rattachement/absorption sur la c. de ratt./absorbante
  $mirroirs = [
    'sAssocieA'=>'prendPourAssociées',
    'resteAssociéeA'=>'gardeCommeAssociées',
    'devientDéléguéeDe'=>'prendPourDéléguées',
    'resteDéléguéeDe'=>'gardeCommeDéléguées',
    'crééeCommeSimpleParScissionDe'=>'seScindePourCréer',
    'crééeCommeAssociéeParScissionDe'=>'seScindePourCréer',
    'crééCommeArrondissementMunicipalParScissionDe'=>'seScindePourCréer',
    'changeDeRattachementPour'=>'prendLeRattachementDe',
    'perdRattachementPour'=>'prendLeRattachementDe',
  ];
  foreach ($rpicoms as $id => &$rpicom) {
    foreach ($rpicom as $dv => &$version) {
      $evt = $version['évènement'] ?? null;
      if (is_array($evt)) {
        $key0 = array_keys($evt)[0];
        $cratid = $evt[$key0];
        if ($mirroir = $mirroirs[$key0] ?? null) {
          //echo "mirroir $key0\n";
          addValToArray($id, $rpicoms[$cratid][$dv]['évènementDétaillé'][$mirroir]);
        }
        elseif ($cratid = $version['évènement']['fusionneDans'] ?? null) {
          $evt = $rpicoms[$cratid][$dv]['évènement'];
          // si l'évt mirroir est crééeParFusionSimpleDe alors pas de création de absorbe
          if (!(is_array($evt) && (array_keys($evt)==['crééeParFusionSimpleDe']))) {
            addValToArray($id, $rpicoms[$cratid][$dv]['évènementDétaillé']['absorbe']);
          }
        }
      }
      elseif (in_array($evt, ["Commune associée rétablie comme commune simple","Commune déléguée rétablie comme commune simple"])) {
        $cratid = $version['estAssociéeA'] ?? $version['estDéléguéeDe'];
        $version['évènement'] = ['seDétacheDe' => $cratid];
        $dv = substr($dv, 0, 10);
        addValToArray($id, $rpicoms[$cratid][$dv]['évènementDétaillé']['détacheCommeSimples']);
      }
    }
  }
  // corrige les evts détaillés affectés par erreur à une date alors qu'ils auraient du être affectés au bis
  $évènements = [
    'Se crée en commune nouvelle avec commune déléguée propre',
    'Prend des c. associées et/ou absorbe des c. fusionnées',
    'Absorbe certaines de ses c. rattachées ou certaines de ses c. associées deviennent déléguées',
    'Se crée en commune nouvelle',
    'Commune rétablissant des c. rattachées ou fusionnées',
  ];
  foreach ($rpicoms as $id => &$rpicom) {
    foreach ($rpicom as $dv => $version) {
      if (isset($rpicom["$dv-bis"])) {
        if (isset($version['évènementDétaillé']) && !in_array($version['évènement'], $évènements)) {
          $rpicom["$dv-bis"]['évènementDétaillé'] = $version['évènementDétaillé'];
          unset($rpicom[$dv]['évènementDétaillé']);
          //echo "Pour $id, transfert détails de $dv sur $dv-bis pour évènement='",json_encode($version['évènement']),"'\n";
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
  
  file_put_contents(
    __DIR__.'/rpicomd.yaml',
    Yaml::dump([
      'title'=> "Référentiel Rpicom modifié avec évts détaillés",
      '@id'=> 'http://id.georef.eu/comhisto/insee/rpicomd',
      'description'=> "Voir la documentation sur https://github.com/benoitdavidfr/yamldocs/tree/master/comhisto",
      'created'=> date(DATE_ATOM),
      'valid'=> '2020-01-01',
      /*'$schema'=> 'http://id.georef.eu/comhisto/insee/exhisto/$schema',
      'ydADscrBhv'=> [
        'jsonLdContext'=> 'http://schema.org',
        'firstLevelType'=> 'AdministrativeArea',
        'buildName'=> [ // définition de l'affichage réduit par type d'objet, code Php par type
          'AdministrativeArea'=> $buildNameAdministrativeArea,
        ],
        'writePserReally'=> true,
      ],*/
      'contents'=> $rpicoms,
    ], 4, 2));
}

{/*PhpDoc: functions
name: deduitEvt
title: "function deduitEvt(array &$rpicoms): void - recalcule les évènements déduits sur histo"
doc: |
  Part de Histo produit par bhisto sous la forme d'un dictionnaire [cinsee => histo]
  et recalcule les évts déduits
*/}
function deduitEvt(array &$histos): void {
  $evtsDeduits = [ // [déduit => [primaire]]
    'seDissoutDans'=>['reçoitUnePartieDe'],
    'crééeAPartirDe'=>['contribueA'],
    'absorbe'=>['fusionneDans'],
    'seScindePourCréer'=>[
      'crééeCommeSimpleParScissionDe',
      'crééeCommeAssociéeParScissionDe',
      'crééCommeArrondissementMunicipalParScissionDe'
    ],
    'prendPourAssociées'=>['sAssocieA'],
    'prendPourDéléguées'=>['devientDéléguéeDe'],
    'détacheCommeSimples'=>['seDétacheDe'],
    'gardeCommeAssociées'=>['resteAssociéeA'],
    'gardeCommeDéléguées'=>['resteDéléguéeDe'],
  ];
  // 1) efface tous les évts déduits
  foreach ($histos as $cinsee => &$histo) {
    foreach ($histo as $dv => &$version) {
      foreach (array_keys($evtsDeduits) as $evtsDeduit)
        if (isset($version['evts'][$evtsDeduit]))
          $version['evts'][$evtsDeduit] = [];
    }
  }
  //return;
  
  // 2) je les recrée
  $evtsPrimaires = []; // [primaire => deduit]
  foreach ($evtsDeduits as $evtDeduit => $primaires)
    foreach ($primaires as $primaire)
      $evtsPrimaires[$primaire] = $evtDeduit;
  foreach ($histos as $cinsee => &$histo) {
    foreach ($histo as $dv => &$version) {
      foreach ($version['evts'] ?? [] as $verbe => $objets) {
        if ($evtDeduit = $evtsPrimaires[$verbe] ?? null) {
          addValToArray($cinsee, $histos[$objets][$dv]['evts'][$evtDeduit]);
        }
      }
    }
  }
}

{/*PhpDoc: functions
name: deduitEtat
title: "function deduitEtat(array &$rpicoms): void - recalcule les propriétés d'état déduites sur histo"
doc: |
  Part de Histo produit par bhisto sous la forme d'un dictionnaire [cinsee => histo]
  et recalcule les entités rattachées
*/}
function deduitEtat(array &$histos): void {
  // 1) j'efface les états déduits
  foreach ($histos as $cinsee => &$histo) {
    foreach ($histo as $dv => &$version) {
      unset($version['erat']);
    }
  }
  //return;
  
  // 2) je les recrée
  foreach ($histos as $cinsee => &$histo) {
    foreach ($histo as $dv => &$version) {
      if (!isset($version['etat'])) continue;
      switch ($version['etat']['statut']) {
        case 'COMA': { addValToArray($cinsee, $histos[$version['etat']['crat']][$dv]['erat']['aPourAssociées']); break; }
        case 'COMD': { addValToArray($cinsee, $histos[$version['etat']['crat']][$dv]['erat']['aPourDéléguées']); break; }
        case 'ARDM': { addValToArray($cinsee, $histos[$version['etat']['crat']][$dv]['erat']['aPourArdm']); break; }
        case 'COMM': { addValToArray($cinsee, $version['erat']['aPourDéléguées']); break; }
      }
    }
  }
}

{/*PhpDoc: screens
name: bhisto
title: bhisto - construction du fichier histo
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
  echoHtmlHeader($_GET['action']);
  $rpicoms = new Base('rpicom', new Criteria(['not'])); // Lecture de rpicom.yaml dans $rpicoms
  $rpicoms = $rpicoms->contents(); // remplacement de l'objet Base par le dictionnaire des codes Insee
  detailleEvt($rpicoms);
  foreach ($rpicoms as $cinsee => $rpicom) {
    //if (!in_array($cinsee, ['01015','01079','01283',01217','78001','13201','14513','55273','55386','91001'])) continue;
    //echo Yaml::dump(['initial'=> [$cinsee => $rpicom]], 3);
    
    // transforme estAssociéeA/estDéléguéeDe/estArrondissementMunicipalDe en statut/crat
    // traite aussi le cas du statut mixte Simple + Déléguée
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

    // transforme évènement + évènementDétaillé -> evts
    foreach ($rpicom as $dfin => $val) {
      $evt = $val['évènement'] ?? [];
      if (isset($val['évènementDétaillé'])) {
        if (is_string($evt) || !$evt)
          $evt = $val['évènementDétaillé'];
        else
          $evt = array_merge($evt, $val['évènementDétaillé']);
      }
      if ($evt) {
        $val['evts'] = $evt;
        unset($val['évènement']);
        unset($val['évènementDétaillé']);
      }
      $rpicom[$dfin] = $val;
    }
    //echo Yaml::dump(['2'=> [$cinsee => $rpicom]], 3);
    // Transforme les date bis en liste d'évènements
    // l'état résultant est celui de la date bis
    $dfins = array_keys($rpicom);
    foreach ($dfins as $ikey => $dfin) {
      if (substr($dfin, 10)=='-bis') {
        $evt1 = $rpicom[$dfins[$ikey]]['evts'];
        $evt2 = $rpicom[$dfins[$ikey-1]]['evts'];
        if (!is_array($evt1))
          $evt1 = [ 'label'=> $evt1 ];
        if (!is_array($evt2))
          $evt2 = [ 'label'=> $evt2 ];
        foreach (['name','statut','crat'] as $key)
          if (isset($rpicom[$dfin][$key]))
            $rpicom[$dfins[$ikey-1]][$key] = $rpicom[$dfin][$key];
          else
            unset($rpicom[$dfins[$ikey-1]][$key]);
        $rpicom[$dfins[$ikey-1]]['evts'] = array_merge($evt1, $evt2);
        unset($rpicom[$dfins[$ikey]]);
      }
    }
    ksort($rpicom);
    
    // passe de date de fin en date de début
    $dfins = array_keys($rpicom);
    if (isset($rpicom[$dfins[0]]['name'])) {
      $etat = ['name'=> $rpicom[$dfins[0]]['name'], 'statut'=> $rpicom[$dfins[0]]['statut']];
      if (isset($rpicom[$dfins[0]]['crat']))
        $etat['crat'] = $rpicom[$dfins[0]]['crat'];
      if (substr($cinsee, 0, 3)<>'976')
        $rpicom['1943-01-01'] = ['etat' => $etat];
      else
        $rpicom['2011-03-31'] = ['etat' => $etat]; // date à laquelle Mayotte est devenu un département français
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
    //echo Yaml::dump(['fin'=> [$cinsee => $rpicom]], 4);
        
    $histos[$cinsee] = $rpicom;
  }
  

  // réécriture des perdRattachementPour de 14624/14697/14472, ...
  // dans cette association le chef-lieu est d'abord 14624 puis le 1/2/1990 14697 puis le 7/1/2014 14472
  $perdRattachementsPour = [
    '1990-02-01'=> ['old'=> 14624, 'new'=> 14697, 'rat'=> [14010, 14067, 14234, 14295, 14314, 14363, 14447, 14472]],
    '2014-01-07'=> ['old'=> 14697, 'new'=> 14472, 'rat'=> [14010, 14067, 14234, 14295, 14314, 14363, 14447, 14624]],
  ];
  foreach ($perdRattachementsPour as $dv => $perdRatt) {
    //echo Yaml::dump([$dv => ['$nouvRattachees'=> $nouvRattachees]]);
    foreach ($perdRatt['rat'] as $id) {
      $histos[$id][$dv]['evts'] = ['seDétacheDe'=> $perdRatt['old'], 'sAssocieA'=> $perdRatt['new']];
      $histos[$id][$dv]['etat']['statut'] = 'COMA';
      $histos[$id][$dv]['etat']['crat'] = $perdRatt['new'];
    }
    $histos[$perdRatt['new']][$dv]['evts'] = [
      'seDétacheDe'=> $perdRatt['old'],
      'prendPourAssociées'=> array_merge($perdRatt['rat'], [$perdRatt['old']])
    ];
    $histos[$perdRatt['new']][$dv]['etat']['statut'] = 'COMS';
    $histos[$perdRatt['old']][$dv]['evts'] = [
      'détacheCommeSimples'=> array_merge($perdRatt['rat'], [$perdRatt['new']]),
      'sAssocieA'=> $perdRatt['new']
    ];
    $histos[$perdRatt['old']][$dv]['etat']['statut'] = 'COMA';
    $histos[$perdRatt['old']][$dv]['etat']['crat'] = $perdRatt['new'];
  }
  
  // réécriture des perdRattachementPour de 49065/49080
  // c. nouvelle prend une nouvelle déléguée avec transfert du chef-lieu à cette nouvelle déléguée
  $perdRattachementsPour = [
    // le 1/1/2019 49065 qui avait pour déléguées elle-même et les rat est transférée à 49080
    '2019-01-01'=> ['old'=> 49065, 'new'=> 49080, 'rat'=> [49051, 49096, 49105, 49189, 49254, 49335]],
  ];
  foreach ($perdRattachementsPour as $dv => $perdRatt) {
    foreach ($perdRatt['rat'] as $id) {
      $histos[$id][$dv]['evts'] = ['seDétacheDe'=> $perdRatt['old'], 'devientDéléguéeDe'=> $perdRatt['new']];
      $histos[$id][$dv]['etat']['statut'] = 'COMD';
      $histos[$id][$dv]['etat']['crat'] = $perdRatt['new'];
    }
    $histos[$perdRatt['new']][$dv]['evts'] = [
      'prendPourDéléguées'=> array_merge([$perdRatt['new'], $perdRatt['old']], $perdRatt['rat'])
    ];
    $histos[$perdRatt['new']][$dv]['etat']['statut'] = 'COMS';
    $histos[$perdRatt['old']][$dv]['evts'] = [
      'détacheCommeSimples'=> $perdRatt['rat'],
      'devientDéléguéeDe'=> $perdRatt['new']
    ];
    $histos[$perdRatt['old']][$dv]['etat']['statut'] = 'COMD';
    $histos[$perdRatt['old']][$dv]['etat']['crat'] = $perdRatt['new'];
  }
  
  // réécriture de prendLeRattachementDe de 49018/49101
  unset($histos[49018]['2016-01-01']['evts']['prendLeRattachementDe']);
  $histos[49018]['2016-01-01']['evts']['prendPourDéléguées'][] = 49101;
  $histos[49101]['2016-01-01']['evts'] = [
    'détacheCommeSimples'=> [49380],
    'devientDéléguéeDe'=> 49018,
  ];
  $histos[49380]['2016-01-01']['evts'] = ['seDétacheDe'=> 49101, 'devientDéléguéeDe'=> 49018];
  
  // le 1/1/2018, 49149 qui avait pour déléguées elle-même et les rat est transférée à 49261
  unset($histos[49261]['2018-01-01']['evts']['prendLeRattachementDe']);
  $histos[49261]['2018-01-01']['evts']['prendPourDéléguées'][] = 49149;
  $histos[49149]['2018-01-01']['evts'] = [
    'détacheCommeSimples'=> [49094, 49154, 49279, 49346],
    'devientDéléguéeDe'=> 49261,
  ];
  
  // remplacement de crééeParFusionSimpleDe par absorbe+changeDeCodePour
  $histos[14612]['1947-08-27']['evts'] = ['fusionneDans'=> 14485];
  $histos[14485]['1947-08-27']['evts'] = ['absorbe'=> [14612], 'changeDeCodePour'=> 14764];
  $histos[14764]['1947-08-27']['evts'] = ['avaitPourCode'=> 14485];
  
  // correction d'erreurs
  // il manque un détachement avant devientDéléguéeDe
  // 49094: {'2018-01-01': {evts: { devientDéléguéeDe: 49261 }}}
  $histos[49094]['2018-01-01']['evts'] = ['seDétacheDe'=> 49149, 'devientDéléguéeDe'=> 49261];
  // 49154: {'2018-01-01': {evts: { devientDéléguéeDe: 49261 }}}
  $histos[49154]['2018-01-01']['evts'] = ['seDétacheDe'=> 49149, 'devientDéléguéeDe'=> 49261];
  // 49279: {'2018-01-01': {evts: { devientDéléguéeDe: 49261 }}}
  $histos[49279]['2018-01-01']['evts'] = ['seDétacheDe'=> 49149, 'devientDéléguéeDe'=> 49261];
  // 49346: {'2018-01-01': {evts: { devientDéléguéeDe: 49261 }}}
  $histos[49346]['2018-01-01']['evts'] = ['seDétacheDe'=> 49149, 'devientDéléguéeDe'=> 49261];


  deduitEvt($histos);
  deduitEtat($histos);
  
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
      '@id'=> 'http://id.georef.eu/comhisto/insee/histo',
      'description'=> "Voir la documentation sur https://github.com/benoitdavidfr/comhisto",
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
      'contents'=> $histos,
    ], 4, 2));
  die("Fin bhisto ok, résultat écrit dans histo.yaml\n");
}

if ($_GET['action'] == 'mirroirs') { // construction de la liste des évts mirroirs
  class Evts {
    protected $evts;
  
    function __construct($evts) { $this->evts = $evts; }
    
    function asVal() { return $this->evts; }
    
    function is_stringOrNumeric(): bool { return is_string($this->evts) || is_numeric($this->evts); }
    
    function buildMirroirs(string $cinsee, string $dcrea, Base $histos) {
      if (is_string($this->evts)) return;
      foreach ($this->evts as $key => $vals) {
        if ($key == 'changeDeNomPour') continue;
        //echo Yaml::dump(['$vals'=> $vals]);
        if (is_null($vals)) continue;
        if (is_string($vals) || is_numeric($vals)) {
          if ($key=='label') continue;
          if (!$histos->$vals) {
            echo "<b>Erreur sur $cinsee</b>\n";
            echo Yaml::dump([$cinsee=> [$dcrea => $this->asVal()]]);
            continue;
          }
          if (!isset($histos->$vals[$dcrea])) {
            echo "<b>Erreur sur $cinsee $dcrea</b>\n";
            echo Yaml::dump([$cinsee=> [$dcrea => $this->asVal()]]);
            continue;
          }
          $cible = $histos->$vals[$dcrea];
          //echo Yaml::dump(['$cible'=> $cible]);
          $mirroir = new Evts($cible['evts']);
          Mirroirs::add($cinsee, $key, $vals, $this, $mirroir);
        }
        else {
          foreach ($vals as $val) {
            if (!$histos->$val) {
              echo "<b>Erreur sur $cinsee</b>\n";
              continue;
            }
            if (!isset($histos->$val[$dcrea])) {
              echo "<b>Erreur sur $cinsee $dcrea</b>\n";
              echo Yaml::dump([$cinsee=> [$dcrea => $this->asVal()]]);
              continue;
            }
            $cible = $histos->$val[$dcrea];
            //echo Yaml::dump(['$cible'=> $cible]);
            $mirroir = new Evts($cible['evts']);
            Mirroirs::add($cinsee, $key, $val, $this, $mirroir);
          }
        }
      }
    }
    
    function searchKey(string $cinsee): string {
      foreach ($this->evts as $key => $vals) {
        if (($vals == $cinsee) || (is_array($vals) && in_array($cinsee, $vals)))
          return $key;
      }
      return '*'.implode('-',array_keys($this->evts));
    }
  };

  class Mirroirs {
    static $mirroirs;
    
    static function add(string $cinsee, string $key1, string $cinsee2, Evts $evts, Evts $mirroir) {
      if (0) {
        echo yaml::dump([
          'Mirroirs::add'=> [
            'evts'=> [$cinsee => [$key1 => $evts->asVal()]],
            'mirroir'=> [$cinsee2 => $mirroir->asVal()]
          ]
        ]);
      }
      if ($mirroir->is_stringOrNumeric()) {
        $key2 = $mirroir->asVal();
      }
      else {
        $key2 = $mirroir->searchKey($cinsee);
        //echo "key2=$key2\n";
      }
      if (!isset(self::$mirroirs[$key1][$key2]))
        self::$mirroirs[$key1][$key2] = 1;
      else
        self::$mirroirs[$key1][$key2]++;
      //echo '<b>',Yaml::dump(['$mirroirs'=> self::$mirroirs]),"</b>";
    }
  };
  
  echoHtmlHeader($_GET['action']);
  $histos = new Base('histo', new Criteria(['not'])); // Lecture de histo.yaml dans $histos
  foreach ($histos->contents() as $cinsee => $histo) {
    foreach ($histo as $dcrea => $version) {
      if (isset($version['evts'])) {
        //echo Yaml::dump(['histo'=> [$cinsee => [$dcrea => $version['evts']]]], 3, 2);
        $evts = new Evts($version['evts']);
        $evts->buildMirroirs($cinsee, $dcrea, $histos);
      }
    }
  }
  echo Yaml::dump(['$mirroirs' => Mirroirs::$mirroirs], 2, 2);
  die("Fin mirroirs ok\n");
}

{/*PhpDoc: classes
name: Verif
title: class Verif - utilisée pour la vérification des pré/post conditions
methods:
*/}
class Verif {
  protected $cinsee;
  protected $dcrea;
  protected $etatPrec;
  protected $eratPrec;
  protected $version;
  
  function __construct(string $cinsee, string $dcrea, array $etatPrec, array $eratPrec, array $version) {
    $this->cinsee = $cinsee;
    $this->dcrea = $dcrea;
    $this->etatPrec = $etatPrec;
    $this->eratPrec = $eratPrec;
    $this->version = $version;
  }
  
  function warning(string $message) {
    echo "$message pour ",
         Yaml::dump([$this->cinsee => [ $this->dcrea => [ 'evts'=> $this->version['evts'] ?? [] ] ]], 0),"\n";
  }
  
  function error(string $message) {
    echo "<b>$message</b> pour ",
         Yaml::dump([$this->cinsee => [
           $this->dcrea => [
             'prec'=> $this->etatPrec,
             'evts'=> $this->version['evts'] ?? [],
             'suiv'=> $this->version['etat'] ?? []
           ]
         ]], 3);
  }
  
  function prePostCond(): array {
    {/*PhpDoc: methods
    name: prePostCond
    title: "function prePostCond(): array - vérifie les pré- et post-conditions des évènements"
    doc: |
      en cas d'erreur mineure effectue des corrections de la version et affiche une alerte
      signale les erreurs majeures
    */}
    $evts = $this->version['evts'] ?? [];
    $etatSuiv = $this->version['etat'] ?? [];
    switch (array_keys($evts)) {
      // entrée dans le référentiel
      case [] : {
        // l'état préc. n'existe pas
        if ($this->etatPrec)
          $this->error("L'état précédent ne devrait pas exister");
        // l'état suivant correspond à une commune simple ou à un arrondissement
        if (!($etatSuiv && in_array($etatSuiv['statut'], ['COMS', 'ARDM'])))
          $this->error("L'état suivant devrait être une commune simple ou à un arrondissement pour ");
        return $this->version;
      }
      
      // changeDeNomPour
      case ['changeDeNomPour']: {
        if (!$this->etatPrec)
          $this->error("l'état précédent devrait exister");
        if (!($etatSuiv && ($etatSuiv['name']==$evts['changeDeNomPour'])))
          $this->error("l'état suivant devrait exister et son nom devrait être celui fourni");
        return $this->version;
      }

      // sortDuPérimètreDuRéférentiel
      // changeDeCodePour
      case ['changeDeCodePour']:
      case ['absorbe','changeDeCodePour']:
      case ['sortDuPérimètreDuRéférentiel']: {
        if (!$this->etatPrec)
          $this->error("l'état précédent devrait exister");
        if ($etatSuiv)
          $this->error("l'état suivant ne devrait pas exister");
        return $this->version;
      }
      
      // avaitPourCode
      case ['avaitPourCode']: {
        if ($this->etatPrec)
          $this->error("L'état précédent ne devrait pas exister");
        if (!$etatSuiv)
          $this->error("l'état suivant devrait exister");
        return $this->version;
      }
      
      case ['avaitPourCode','devientDéléguéeDe']: {
        if ($this->etatPrec)
          $this->error("L'état précédent ne devrait pas exister");
        if (!($etatSuiv && ($etatSuiv['statut']=='COMD')))
          $this->error("l'état suivant devrait être une commune déléguée");
        return $this->version;
      }
      
      // reçoitUnePartieDe - contribueA
      case ['reçoitUnePartieDe']:
      case ['changeDeNomPour','reçoitUnePartieDe']:
      case ['contribueA']: {
        if (!($this->etatPrec && ($this->etatPrec['statut']=='COMS')))
          $this->error("l'état précédent devrait exister et être une commune simple");
        if (!($etatSuiv && ($etatSuiv['statut']=='COMS')))
          $this->error("l'état suivant devrait exister et être une commune simple");
        return $this->version;
      }
      // seDissoutDans - crééeAPartirDe
      case ['seDissoutDans']: return $this->version; // evt déduit
      case ['crééeAPartirDe']: return $this->version; // evt déduit
      
      // fusionneDans
      case ['fusionneDans']: {
        if (!($this->etatPrec && in_array($this->etatPrec['statut'], ['COMS','COMA','COMD'])))
          $this->error("l'état précédent devrait être une commune simple, associée ou déléguée");
        if ($etatSuiv)
          $this->error("l'état suivant ne devrait pas exister");
        return $this->version;
      }
      // absorbe
      case ['absorbe']: return $this->version; // evt déduit
      case ['absorbe','gardeCommeAssociées']: return $this->version; // evts déduits
      case ['gardeCommeAssociées','absorbe']: return $this->version; // evts déduits
      case ['absorbe','prendPourAssociées']: return $this->version; // evts déduits
      
      // crééeCommeSimpleParScissionDe
      case ['crééeCommeSimpleParScissionDe']: {
        if ($this->etatPrec)
          $this->error("L'état précédent ne devrait pas exister");
        if (!($etatSuiv && ($etatSuiv['statut']=='COMS')))
          $this->error("l'état suivant devrait être une commune simple");
        return $this->version;
      }
      
      // crééeCommeAssociéeParScissionDe
      case ['crééeCommeAssociéeParScissionDe']: {
        if ($this->etatPrec)
          $this->error("L'état précédent ne devrait pas exister");
        if (!($etatSuiv && ($etatSuiv['statut']=='COMA')))
          $this->error("l'état suivant devrait être une commune associée");
        return $this->version;
      }

      // crééCommeArrondissementMunicipalParScissionDe
      case ['crééCommeArrondissementMunicipalParScissionDe']: {
        if ($this->etatPrec)
          $this->error("L'état précédent ne devrait pas exister");
        if (!($etatSuiv && ($etatSuiv['statut']=='ARDM')))
          $this->error("l'état suivant devrait être un arrondissement municipal");
        return $this->version;
      }
      
      // seScindePourCréer
      case ['seScindePourCréer']: return $this->version; // evts déduits
      
      // estModifiéeIndirectementPar
      case ['estModifiéeIndirectementPar']: return $this->version;
      
      // sAssocieA
      case ['sAssocieA']:
      case ['détacheCommeSimples','sAssocieA']: {
        if (!($this->etatPrec && ($this->etatPrec['statut']=='COMS'))) {
          if (($this->etatPrec['statut']=='COMA') && ($this->etatPrec['crat']==$evts['sAssocieA'])) {
            $this->warning("modif evt sAssocieA en resteAssociéeA");
            $this->version['evts'] = ['resteAssociéeA'=> $evts['sAssocieA']];
          }
          else
            $this->error("l'état précédent devrait être une commune simple");
        }
        if (!($etatSuiv && ($etatSuiv['statut']=='COMA'))) {
          if ($etatSuiv['statut']=='COMS') {
            $this->warning("modif statut en COMA et affect crat");
            $this->version['etat']['statut'] = 'COMA';
            $this->version['etat']['crat'] = $evts['sAssocieA'];
          }
          else
            $this->error("l'état suivant devrait être une commune associée");
        }
        return $this->version;
      }
      
      // prendPourAssociées
      case ['prendPourAssociées']: return $this->version; // evt déduit
      case ['prendPourAssociées','absorbe']: return $this->version; // evt déduit
        
      // resteAssociéeA
      case ['resteAssociéeA']: {
        if (!$this->etatPrec)
          $this->error("l'état précédent de resteAssociéeA devrait exister");
        if (!($this->etatPrec['statut']=='COMA'))
          $this->error("l'état précédent devrait être une commune associée");
        if (!($etatSuiv && ($etatSuiv['statut']=='COMA')))
          $this->error("l'état suivant devrait être une commune associée");
        return $this->version;
      }
      // gardeCommeAssociées
      case ['gardeCommeAssociées','détacheCommeSimples']: return $this->version; // evt déduit
      case ['détacheCommeSimples','gardeCommeAssociées']: return $this->version; // evt déduit
  
      // devientDéléguéeDe
      case ['devientDéléguéeDe']:
      case ['devientDéléguéeDe','prendPourDéléguées']:
      case ['devientDéléguéeDe','prendPourDéléguées','gardeCommeDéléguées']:
      case ['devientDéléguéeDe','prendPourDéléguées','absorbe']:
      case ['devientDéléguéeDe','prendPourDéléguées','absorbe','gardeCommeDéléguées']: {
        if (!$this->etatPrec)
          $this->error("l'état précédent de devientDéléguéeDe devrait exister");
        if (($this->etatPrec['statut']=='COMD') && ($this->etatPrec['crat']==$evts['devientDéléguéeDe'])) {
          $this->warning("modif evt devientDéléguéeDe en resteDéléguéeDe");
          $this->version['evts']['resteDéléguéeDe'] = $evts['devientDéléguéeDe'];
          unset($this->version['evts']['devientDéléguéeDe']);
        }
        elseif (!(($this->etatPrec['statut']=='COMS')
              || (($this->etatPrec['statut']=='COMA') && ($this->etatPrec['crat']==$evts['devientDéléguéeDe'])))) {
          $this->error("l'état précédent devrait être une commune simple ou une commune associée à la commune déléguante");
        }
        if (!$etatSuiv)
          $this->error("l'état suivant devrait exister");
        if ($etatSuiv['statut']=='COMS') {
          if ($evts['devientDéléguéeDe']==$this->cinsee) {
            $this->warning("modif statut en COMM");
            $etatSuiv['statut'] = 'COMM';
          }
          else {
            $this->warning("modif statut en COMD et affect crat");
            $etatSuiv['statut'] = 'COMD';
            $etatSuiv['crat'] = $evts['devientDéléguéeDe'];
          }
        }
        elseif (!in_array($etatSuiv['statut'], ['COMD','COMM'])) {
          $this->error("l'état suivant devrait être une commune déléguée ou mixte");
        }
        return $this->version;
      }
      
      case ['détacheCommeSimples','devientDéléguéeDe']: {
        if (!$this->etatPrec)
          $this->error("l'état précédent de devrait exister");
        $deleguees = []; // [ deleguee => 1]
        foreach ($this->eratPrec['aPourDéléguées'] as $deleguee)
          $deleguees[$deleguee] = 1;
        foreach ($evts['détacheCommeSimples'] as $deleguee)
          unset($deleguees[$deleguee]);
        if (!in_array($deleguees, [[], [$this->cinsee=>1]]))
          $this->error("toutes les déléguées devraient être détachées");
        if (!($etatSuiv['statut']=='COMD'))
          $this->error("l'état suivant devrait être une commune déléguée");
        return $this->version;
      }
      
      // prendPourDéléguées
      case ['prendPourDéléguées']: return $this->version; // evt déduit
      case ['prendPourDéléguées','absorbe']: return $this->version; // evt déduit
      case ['prendPourDéléguées','gardeCommeDéléguées']: return $this->version; // evt déduit
      case ['gardeCommeDéléguées','prendPourDéléguées']: return $this->version; // evt déduit
  
      // resteDéléguéeDe
      case ['resteDéléguéeDe']: {
        if (!$this->etatPrec)
          $this->error("l'état précédent de devrait exister");
        if (!($this->etatPrec['statut']=='COMD'))
          $this->error("l'état précédent devrait être une commune déléguée");
        if (!($etatSuiv && ($etatSuiv['statut']=='COMD')))
          $this->error("l'état suivant devrait être une commune déléguée");
        return $this->version;
      }
      // gardeCommeDéléguées
      case ['gardeCommeDéléguées']: return $this->version; // evt déduit

      // seDétacheDe
      case ['seDétacheDe']:
      case ['seDétacheDe','prendPourAssociées']: {
        if (!$this->etatPrec)
          $this->error("l'état précédent devrait exister");
        if (!(in_array($this->etatPrec['statut'], ['COMA','COMD'])))
          $this->error("l'état précédent devrait être une commune rattachée");
        if (!($etatSuiv && ($etatSuiv['statut']=='COMS')))
          $this->error("l'état suivant devrait être une commune simple");
        return $this->version;
      }
      
      case ['seDétacheDe','sAssocieA']: {
        if (!$this->etatPrec)
          $this->error("l'état précédent devrait exister");
        if (!(in_array($this->etatPrec['statut'], ['COMA','COMD'])))
          $this->error("l'état précédent devrait être une commune rattachée");
        if (!($etatSuiv && ($etatSuiv['statut']=='COMA')))
          $this->error("l'état suivant devrait être une commune associée");
        return $this->version;
      }
      
      case ['seDétacheDe','devientDéléguéeDe']: {
        if (!$this->etatPrec)
          $this->error("l'état précédent devrait exister");
        if (!(in_array($this->etatPrec['statut'], ['COMA','COMD'])))
          $this->error("l'état précédent devrait être une commune rattachée");
        if (!($etatSuiv && ($etatSuiv['statut']=='COMD')))
          $this->error("l'état suivant devrait être une commune déléguée");
        return $this->version;
      }

      // détacheCommeSimples
      case ['détacheCommeSimples']: return $this->version; // evt déduit
      case ['détacheCommeSimples','seScindePourCréer']: return $this->version; // evt déduit
      
      default: {
        echo "Evts ",Yaml::dump($evts, 0)," non  traité\n";
        return $this->version;
      }
    }
  }
};

if ($_GET['action'] == 'verifCond') { // test les pré-conditions et post-conditions
  echoHtmlHeader($_GET['action']);
  $histos = new Base('histo', new Criteria(['not'])); // Lecture de histo.yaml dans $histos
  $metadata = $histos->metadata();
  $histos = $histos->contents();
  foreach ($histos as $cinsee => &$histo) {
    $etatPrec = [];
    $eratPrec = [];
    foreach ($histo as $dcrea => &$version) {
      $verif = new Verif($cinsee, $dcrea, $etatPrec, $eratPrec, $version);
      $version = $verif->prePostCond();
      $etatPrec = $version['etat'] ?? [];
      $eratPrec = $version['erat'] ?? [];
    }
  }
  deduitEvt($histos);
  deduitEtat($histos);
  $metadata['created'] = date(DATE_ATOM);
  $histos = new Base(array_merge($metadata, ['contents'=> $histos]));
  $histos->writeAsYaml('histov', [], 3);
  die("Fin verifCond ok, fichier histov écrit\n");
}

if ($_GET['action'] == 'beg') { // met à jour les exemples
  echoHtmlHeader($_GET['action']);
  $rpicoms = new Base('rpicomd', new Criteria(['not'])); // Lecture de rpicomd.yaml dans $histos
  $histos = new Base('histov', new Criteria(['not'])); // Lecture de histov.yaml dans $histos
  foreach (readfiles('.') as $fname => $file) {
    if (!preg_match('!^eg[^.]+.yaml$!', $fname)) continue;
    $fileContents = Yaml::parseFile($fname);
    echo "$fname: $fileContents[title]\n";
    $fileContents['updated'] = date(DATE_ATOM);
    $fileContents['histov'] = [];
    if (!isset($fileContents['contents'])) {
      echo "<b>erreur champ contents absent/n";
      continue;
    }
    if (array_key_exists('rpicomd', $fileContents)) {
      foreach($fileContents['contents'] as $cinsee) {
        $fileContents['rpicomd'][$cinsee] = $rpicoms->$cinsee;
      }
    }
    if (array_key_exists('histov', $fileContents)) {
      foreach($fileContents['contents'] as $cinsee) {
        $fileContents['histov'][$cinsee] = $histos->$cinsee;
      }
    }
    file_put_contents($fname, Yaml::dump($fileContents, 4, 2));
  }
  die("Fin beg ok\n");
}

die("Aucune commande $_GET[action]\n");
