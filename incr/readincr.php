<?php
/*PhpDoc:
name: readincr.php
title: readincr.php - traitement incrémental - Extraction des mvts de l'année 2020
doc: |
  Transforme le fichier de mouvements en un flux Yaml plus facile à interpréter pour modifier le référentiel

  Cas particuliers:
    suppComDéléguée:
      '2017-12-25':
        cheflieu: { COM_AP: '22183', LIBELLE_AP: Plémet }
        comDéléguéeSupp: [22183]
        comDéléguéeCons: [22058]
    
journal: |
  19/3/2021:
    - création
*/

require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (($handle = fopen('../data2/mvtcommune2021.csv', 'r')) === false)
  die("Erreur d'ouverture du fichier des mvts\n");

if (!isset($_GET['a'])) {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>readincr</title></head><body>\n";
  echo "<h2>Menu</h2><ul>\n";
  echo "<li><a href='?a=printr'>affichage des enregistrements avec noms des champs</a></li>\n";
  echo "<li><a href='?a=table'>affichage des mvts de 2020 sous la forme d'une table</a></li>\n";
  echo "<li><a href='?a=process'>traitement des mvts</a></li>\n";
  die("</ul>\n");
}

elseif ($_GET['a'] == 'printr') { // Lecture du fichier et affichage des enregistrements avec noms des champs
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>readincr</title></head><body><pre>\n";
  $headers = null;
  while ($csv = fgetcsv($handle)) {
    if (!$headers) {
      $headers = $csv;
      continue;
    }
    $record = []; // enregistrement avec nom des champs
    foreach ($csv as $no => $val) {
      $record[$headers[$no]] = $val;
    }
    print_r($record);
  }
  die();
}

elseif ($_GET['a'] == 'table') { // affichage des mvts de 2020 sous la forme d'une table
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>readincr</title></head><body>\n";
  echo "<h2>Mvts jusqu'au 1/1/2021 et à partir du 2/1/2020</h2>\n";
  $headers = null;
  while ($csv = fgetcsv($handle)) {
    if (strcmp($csv[1], '2020-01-01') <= 0) break;
    unset($csv[4]); // TNCC_AV
    unset($csv[5]); // NCC_AV
    unset($csv[6]); // NCCENR_AV
    unset($csv[10]); // TNCC_AP
    unset($csv[11]); // NCC_AP
    unset($csv[12]); // NCCENR_AP
    if (!$headers) {
      $headers = $csv;
      echo "<table border=1><th>",implode('</th><th>', $headers),"</th>\n";
    }
    /*elseif ($csv[0] == '50') {
      echo "<tr><td><b>mod",implode('</b></td><td><b>', $csv),"</b></td></tr>\n";
    }*/
    else {
      echo "<tr><td>",implode('</td><td>', $csv),"</td></tr>\n";
    }
  }
  echo "</table>\n";
  die();
}

function chgtNom(array $connpart): array {
  $chgtNom = [];
  //showConnPart($connpart);
  foreach ($connpart as $elt) {
    if (($elt['TYPECOM_AV'] == $elt['TYPECOM_AP']) && ($elt['COM_AV'] == $elt['COM_AP'])
         && ($elt['LIBELLE_AV'] <> $elt['LIBELLE_AP'])) {
       $chgtNom['chgtNom'] = [
         "$elt[TYPECOM_AV]-$elt[COM_AV]"=> [
           'LIBELLE_AV'=> $elt['LIBELLE_AV'],
           'LIBELLE_AP'=> $elt['LIBELLE_AP'],
         ]
       ];
    }
  }
  //echo Yaml::dump($chgtNom),"\n";
  return $chgtNom;
}

function creationComNouvelle(array $connpart): array {
  //showConnPart($connpart);
  $date_eff = $connpart[0]['DATE_EFF'];
  // ident. des caractéristiques de la com. nouvelle
  $cheflieu = []; // ['COM_AP'=> cinsee, 'LIBELLE_AP'=> libelle_ap] - le code insee et le nv nom de la commune nouvelle
  $fusionnees = []; // communes fusionnees dans la commune nouvelle ss la forme [COM_AV => ['LIBELLE_AV'=> libelle]]
  $deleguees = []; // com. dél. de la com. nouv. ss la forme [COM_AV => ['LIBELLE_AV'=> libelle, 'LIBELLE_AP'=> libelle]]
  foreach ($connpart as $i => $elt) {
    if ($elt['TYPECOM_AP'] == 'COM') {
      if (!$cheflieu) {
        $cheflieu = ['COM_AP'=> $elt['COM_AP'], 'LIBELLE_AP'=> $elt['LIBELLE_AP']];
        $fusionnees[$elt['COM_AV']] = ['LIBELLE_AV'=> $elt['LIBELLE_AV']];
        unset($connpart[$i]);
      }
      elseif (($elt['COM_AP'] == $cheflieu['COM_AP']) && ($elt['LIBELLE_AP'] == $cheflieu['LIBELLE_AP'])) {
        $fusionnees[$elt['COM_AV']] = ['LIBELLE_AV'=> $elt['LIBELLE_AV']];
        unset($connpart[$i]);
      }
      else {
        echo "Erreur creationComNouvelle ($cheflieu[COM_AP]<>$elt[COM_AP]) || ($cheflieu[LIBELLE_AP]<>$elt[LIBELLE_AP])\n";
        return [];
      }
    }
  }
  foreach ($connpart as $i => $elt) {
    if (($elt['TYPECOM_AP'] == 'COMD') && isset($fusionnees[$elt['COM_AV']])) {
      if ($elt['LIBELLE_AV'] == $elt['LIBELLE_AP']) {
        $deleguees[$elt['COM_AV']] = ['LIBELLE'=> $elt['LIBELLE_AV']];
      }
      else {
        $deleguees[$elt['COM_AV']] = ['LIBELLE_AV'=> $elt['LIBELLE_AV'], 'LIBELLE_AP'=> $elt['LIBELLE_AP']];
      }
      unset($connpart[$i]);
      unset($fusionnees[$elt['COM_AV']]);
    }
  }
  //echo Yaml::dump(['cnouvap'=> $cnouvap]);
  //echo Yaml::dump(['cnouvavs'=> $cnouvavs]);
  if ($connpart)
    showConnPart($connpart);
  $creationComNouvelle = [
    'creationComNouvelle'=> [
      'cheflieu'=> $cheflieu,
    ]
    + ($fusionnees ? ['comFusionnées'=> $fusionnees] : [])
    + ($deleguees ? ['comDéléguéeCréées'=> $deleguees] : [])
  ];
  //echo Yaml::dump($creationComNouvelle, 4);
  return $creationComNouvelle;
}

function absorptionComRattachee(array $connpart): array {
  // Suppression de commune associée/déléguée (34/35)
  // 34 correspond aussi à des transformations de communes associées en communes déléguées
  // ex Dole (39198) 1/4/2014
  $mod = $connpart[0]['MOD'];
  //showConnPart($connpart);
  $date_eff = $connpart[0]['DATE_EFF'];
  $rattav = []; // liste des rattachées avant
  $rattap = []; // liste des rattachées après
  foreach ($connpart as $elt) {
    if ($elt['TYPECOM_AV'] <> 'COM') {
      $rattav[$elt['COM_AV']] = $elt['TYPECOM_AV'];
    }
    if ($elt['TYPECOM_AP'] <> 'COM')
      $rattap[$elt['COM_AP']] = $elt['TYPECOM_AP'];
    if (($elt['TYPECOM_AV'] == 'COM') && ($elt['TYPECOM_AP'] == 'COM')) {
      if (($elt['COM_AV'] == $elt['COM_AP']) && ($elt['LIBELLE_AV'] == $elt['LIBELLE_AP'])) {
        $cheflieu = [
          'COM'=> $elt['COM_AV'],
          'LIBELLE'=> $elt['LIBELLE_AV'],
        ];
      }
      else {
        $cheflieu = [
          'COM_AV'=> $elt['COM_AV'],
          'LIBELLE_AV'=> $elt['LIBELLE_AV'],
          'COM_AP'=> $elt['COM_AP'],
          'LIBELLE_AP'=> $elt['LIBELLE_AP'],
        ];
      }
    }
  }
  $ratsupp = []; // liste des rattachées absorbées
  $transfAssoEnDel = []; // liste des transf. d'associée en déléguée
  foreach ($rattav as $r => $typ) {
    if (!isset($rattap[$r]))
      $ratsupp[$r] = 1;
    elseif ($typ <> $rattap[$r]) { // chgt de type avant/après
      $transfAssoEnDel[$r] = 1;
      unset($rattap[$r]);
    }
  }
  /*echo Yaml::dump(['absorptionComRattachee'=> [$date_eff=> [
    'cheflieu'=> $cheflieu,
    '$rattav'=> $rattav,
    '$rattap'=> $rattap,
    '$transfAssoEnDel'=> $transfAssoEnDel,
  ]]], 4);*/
  $suppComRattachee = [
    ($mod == 34 ? 'absorptionComAssociées' : 'absorptionComDéléguées') => [
      'cheflieu'=> $cheflieu,
    ]
    + ($ratsupp ? [($mod == 34 ? 'comAssociéeAbsorbées' : 'comDéléguéeAbsorbées') => array_keys($ratsupp)] : [])
    + ($rattap ? [($mod == 34 ? 'comAssociéeCons' : 'comDéléguéeCons') => array_keys($rattap)] : [])
    + ($transfAssoEnDel ? ['comAssociéeTransforméeEnDéléguées' => array_keys($transfAssoEnDel)] : [])
  ];
  //echo Yaml::dump($suppComRattachee, 3);
  return $suppComRattachee;
}

function retablissementRattachee(array $connpart): array {
  // mod=70 libellé par Insee "Transformation de commune associé en commune déléguée"
  // En fait il semble s'agir de communes qui ont été absorbées et qui sont rétablies comme commune rattachée
  // PAS CLAIR
  //showConnPart($connpart);
  return [];
}
  
  
function showConnPart(array $connpart) {
  echo "</pre>ConnPart:<br><table border=1>\n";
  foreach ($connpart as $record) {
    echo "<tr><td>",implode('</td><td>', $record),"</td></tr>\n";
  }
  echo "</table><pre>\n";
}

// traitement d'une partie connexe, retourne soit un mvt structuré comme array, soit une chaine d'erreur
function processConnPart(array $connpart) {
  //showConnPart($connpart);
  // calcul de $mods
  $kmods = []; // liste des mod comme clé
  foreach ($connpart as $record)
    $kmods[$record['MOD']] = 1;
  ksort($kmods);
  $mods = implode(',', array_keys($kmods));
  // traitement en fonction de mods
  return match ($mods) {
    '10' => chgtNom($connpart),
    '32' => creationComNouvelle($connpart),
    '34' => absorptionComRattachee($connpart),
    '35' => absorptionComRattachee($connpart),
    '35,50' => absorptionComRattachee($connpart),
    '70' => retablissementRattachee($connpart),
    default => "mods=$mods non prévu",
  };
}

// appelée avec un ensemble de lignes à la même date
// décompose ces lignes en chacune des parties connexes et appelle processConnPart() sur chacune
function decompConnPart(array $set): array {
  $results = [];
  while ($set) { // tant qu'il reste des lignes non affectées à une partie connexe
    $connpart = []; // liste des lignes appartenant à une même partie connexe
    $ids = []; // liste des ids de départ et d'arrivée de la partie connexe courante
    while (true) { // tt qu'il est possible d'affecter une des lignes à la partie connexe courante
      $someAdded = false;
      foreach ($set as $i => $record) {
        if (!$connpart || in_array($record['COM_AV'], $ids) || in_array($record['COM_AP'], $ids)) {
          $connpart[] = $record;
          unset($set[$i]);
          $ids[] = $record['COM_AV'];
          $ids[] = $record['COM_AP'];
          $someAdded = true;
        }
      }
      if (!$someAdded) // si dans le foreach() aucun record n'a été ajouté alors sortie du while(true)
        break;
    }
    $result = processConnPart($connpart);
    if (is_string($result))
      echo "$result\n";
    elseif ($result)
      $results[] = $result;
  }
  return $results;
}

// Traitement des du fichier Insee des mvts pour générer un fichier de flux Yaml
if ($_GET['a'] == 'process') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>readincr</title></head><body><pre>\n";
  $results = [];
  $modc = 0;
  $datec = '';
  $headers = null;
  $set = []; // ensemble de lignes correspondant à une même date
  while ($csv = fgetcsv($handle)) {
    if (strcmp($csv[1], '2020-01-01') <= 0) break;
    if (!$headers) {
      $headers = $csv;
      continue;
    }
    $record = []; // enregistrement avec nom des champs
    foreach ($csv as $no => $val) {
      if (!in_array($headers[$no], ['TNCC_AV','NCC_AV','NCCENR_AV','TNCC_AP','NCC_AP','NCCENR_AP']))
        $record[$headers[$no]] = $val;
    }
    //print_r($record);
    if ($record['DATE_EFF'] <> $datec) {
      if ($set && ($result = decompConnPart($set)))
        $results[$datec] = $result;
      $set = [];
      $datec = $record['DATE_EFF'];
    }
    $set[] = $record;
  }
  if ($set && ($result = decompConnPart($set)))
    $results[$datec] = $result;
  ksort($results);
  echo Yaml::dump($results, 5, 2);
  file_put_contents(
    'incr2021.yaml',
    Yaml::dump(
      [
        'title'=> "Mouvements ComHisto restructurés pour la période du 2/1/2020 au 1/1/2021",
        'abstract'=> "Les mvts mod=70 ne sont pas traités.",
        '$schema'=> 'incr',
        'mvts'=> $results,
      ],
      6, 2));
}
