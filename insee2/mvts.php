<?php
/*PhpDoc:
name: mvts.php
title: mvts.php - structuration et visualisation des mouvements Insee
doc: |
  réécriture de l'interprétation des évts Insee sous la forme de Mvts plus compréhensibles
  J'essaie d'interpréter le fichier des Evts comme un graphe -> graph.php
journal: |
  20/10/2020:
    - création
includes:
screens:
classes:
functions:
*/
ini_set('memory_limit', '2G');

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/html.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

define('TYPESDEVENEMENT', [ // Les types d'évènements et leur libellé Insee
  10 => "Changement de nom",
  20 => "Création",
  21 => "Rétablissement",
  30 => "Suppression",
  31 => "Fusion simple",
  32 => "Création de commune nouvelle",
  33 => "Fusion association",
  34 => "Transformation de fusion association en fusion simple",
  41 => "Changement de code dû à un changement de département",
  50 => "Changement de code dû à un transfert de chef-lieu",
  70 => "Transformation de commune associé en commune déléguée",
]);

// Un objet correspond à un Mvt
// la fonction de création initialise un mvt à partir d'une liste d'évts et efface ceux consommés
abstract class Mvt {
  const SOUSCLASSES = [
    10 => 'ChgtDeNom',
    31 => 'FusionRattachement',
    32 => 'FusionRattachement',
    33 => 'FusionRattachement',
    34 => 'FusionRattachement',
  ];
  protected $date_eff;

  abstract function __construct(string $date_eff, string $mod, array &$evts);
  abstract function asArray(): array;
  
  static function create(string $date_eff, string $mod, array &$evts): Mvt {
    $sousclasse = self::SOUSCLASSES[$mod] ?? 'AutreMvt';
    return new $sousclasse($date_eff, $mod, $evts);
  }
  
  static function testPattern(string $date_eff, string $mod, array &$evts): void {
    echo "Mvt::testPattern($date_eff, $mod)\n";
    $sousclasse = self::SOUSCLASSES[$mod] ?? 'AutreMvt';
    $sousclasse::testPattern($date_eff, $mod, $evts);
  }
};

class AutreMvt extends Mvt {
  protected $mod;
  protected $evts; // liste des evts correspondants
  
  static function testPattern(string $date_eff, string $mod, array &$evts): void {
    echo "AutreMvt::testPattern($date_eff, $mod)\n";
    $evts = [];
  }

  function __construct(string $date_eff, string $mod, array &$evts) {
    $this->date_eff = $date_eff;
    $this->mod = $mod;
    $this->evts = $evts;
    $evts = [];
  }

  function asArray(): array {
    return [];
    return [
      $this->mod => [
        'date_eff'=> $this->date_eff,
        'evts'=> $this->evts,
      ],
    ];
  }
};

class ChgtDeNom extends Mvt { // 10 
  protected $com; // code de la commune
  protected $libelle_av; // libellé avant de la commune
  protected $libelle_ap; // libellé après de la commune

  static function testPattern(string $date_eff, string $mod, array &$evts): void {
    $evt = array_shift($evts);
    if (($evt['com_av']==$evt['com_ap']) && ($evt['typecom_av']==$evt['typecom_ap']) && ($evt['libelle_av']<>$evt['libelle_ap']))
      echo "ChgtDeNom::testPattern($date_eff, $mod) ok\n"; 
    else {
      echo "<b>ChgtDeNom::testPattern($date_eff, $mod) KO</b>\n";
      array_unshift($evts, $evt);
      showEvts($date_eff, $mod, $evts);
      $evt = array_shift($evts);
    }
  }

  function __construct(string $date_eff, string $mod, array &$evts) {
    $this->date_eff = $date_eff;
    $evt = array_shift($evts);
    $this->com = $evt['com_av'];
    $this->libelle_av = $evt['libelle_av'];
    $this->libelle_ap = $evt['libelle_ap'];
  }
  
  function asArray(): array {
    return [];
    return [
      'ChangementDeNom(10)' => [
        $this->com => [
          'libelle_av' => $this->libelle_av,
          'libelle_ap' => $this->libelle_ap,
        ]
      ],
    ];
  }
};

class FusionRattachement extends Mvt { // 31 || 32 || 33 || 34 
  protected $mod;
  protected $cheflieu; // code du chef-lieu
  protected $cheflieu_av; // code du chef-lieu avant s'il est différent de après (exception)
  protected $libelle_av; // libellé avant du chef-lieu, null en cas de création (cas particulier)
  protected $libelle_ap; // libellé après du chef-lieu
  protected $fusionnees; // [{comi} => [typecom_av: {typecom_av}, libelle_av: {libelle_av}]]
  protected $rattachees; // [{comi} => [typecom_av: {typecom_av}, libelle_av: {libelle_av}, libelle_ap: {libelle_ap}]]
  protected $evts; // liste des evts correspondants
  
  static function testPattern(string $date_eff, string $mod, array &$evts): void {
    $cheflieu = null;
    // identification du chef-lieu, c'est une ligne / (com_av == com_ap) && (typecom_av == 'COM') && (typecom_ap == 'COM')
    foreach ($evts as $i => $evt) {
      if (($evt['com_av'] == $evt['com_ap']) && ($evt['typecom_av']=='COM') && ($evt['typecom_ap']=='COM')) {
        $cheflieu = $evt['com_av'];
        $libelle_av = $evt['libelle_av'];
        $libelle_ap = $evt['libelle_ap'];
        unset($evts[$i]);
        break;
      }
    }
    if (!$cheflieu) {
      //echo "</pre>";
      showEvts($date_eff, $mod, $evts);
      //print_r($evts);
      //echo "<pre>";
      throw new Exception("Erreur d'identification du chef-lieu");
    }
    // identification des autres entités concernées, lignes / (com_ap == {cheflieu}) && (typecom_ap == 'COM')
    $fusionnees = [];
    foreach ($evts as $i => $evt) {
      if (($evt['com_ap'] == $cheflieu) && ($evt['typecom_ap']=='COM')) {
        $fusionnees[$evt['com_av']] = 1;
        unset($evts[$i]);
      }
    }
    foreach ($evts as $i => $evt) {
      if (($evt['com_av'] == $evt['com_ap']) && (isset($fusionnees[$evt['com_av']]) || ($evt['com_ap'] == $cheflieu))) {
        $rattachees[$evt['com_av']] = 1;
        unset($fusionnees[$evt['com_av']]);
        unset($evts[$i]);
      }
    }

    echo "FusionRattachement::testPattern($date_eff, $mod) $cheflieu $libelle_av -> $libelle_ap\n";
    //$evts = [];
  }
  
  function __construct(string $date_eff, string $mod, array &$evts) {
    $this->date_eff = $date_eff;
    $this->mod = $mod;
    $this->cheflieu_av = null;
    
    // identification du chef-lieu, c'est une ligne / (com_av == com_ap) && (typecom_av == 'COM') && (typecom_ap == 'COM')
    $this->cheflieu = null;
    foreach ($evts as $i => $evt) {
      if (($evt['com_av'] == $evt['com_ap']) && ($evt['typecom_av']=='COM') && ($evt['typecom_ap']=='COM')) {
        $this->cheflieu = $evt['com_av'];
        $this->libelle_av = $evt['libelle_av'];
        $this->libelle_ap = $evt['libelle_ap'];
        $this->evts = [ $evts[$i] ];
        unset($evts[$i]);
        break;
      }
    }
    if (!$this->cheflieu) {
      if (($mod == 31) && (count($evts)==2) && ($evts[0]['com_ap']==14764)) { // cas particulier de la fusion de 14764 (Pont-d'Ouilly)
        $this->cheflieu = $evts[0]['com_ap'];
        $this->libelle_av = null;
        $this->libelle_ap = $evts[0]['libelle_ap'];
      }
      // cas particulier de l'intégration de 14507 dans 14513/50649 (Pont-Farcy)
      elseif (($mod == 34) && (count($evts) == 2) && ($evts[0]['com_ap'] == 50649)) {
        $this->cheflieu = $evts[0]['com_ap'];
        $this->libelle_ap = $evts[0]['libelle_ap'];
        foreach ($evts as $i => $evt) {
          if ($evt['com_av'] == 14513) {
            $this->cheflieu_av = $evt['com_av'];
            $this->libelle_av = $evt['libelle_av'];
            unset($evts[$i]);
          }
        }
      }
      else {
        echo "</pre>";
        showEvts($date_eff, $mod, $evts);
        //print_r($evts);
        echo "<pre>";
        throw new Exception("Erreur d'identification du chef-lieu");
      }
    }
    // identification des autres entités concernées, lignes / (com_ap == {cheflieu}) && (typecom_ap == 'COM')
    foreach ($evts as $i => $evt) {
      if (($evt['com_ap'] == $this->cheflieu) && ($evt['typecom_ap']=='COM')) {
        $this->fusionnees[$evt['com_av']] = [
          'typecom_av'=> $evt['typecom_av'],
          'libelle_av'=> $evt['libelle_av'],
        ];
        $this->evts[] = $evts[$i];
        unset($evts[$i]);
      }
    }
    // identification des communes déléguées et du libelle_ap // (com_av == com_ap) && (com_av in fusionnees || com_av == {cheflieu})
    foreach ($evts as $i => $evt) {
      if (($evt['com_av'] == $evt['com_ap']) && (isset($this->fusionnees[$evt['com_av']]) || ($evt['com_ap'] == $this->cheflieu))) {
        if ($evt['libelle_ap'] == $evt['libelle_av'])
          $this->rattachees[$evt['com_av']] = [
            'typecom_av' => $evt['typecom_av'],
            'libelle_av_ap' => $evt['libelle_av'],
          ];
        else
          $this->rattachees[$evt['com_av']] = [
            'typecom_av' => $evt['typecom_av'],
            'libelle_av' => $evt['libelle_av'],
            'libelle_ap' => $evt['libelle_ap'],
          ];
        unset($this->fusionnees[$evt['com_av']]);
        $this->evts[] = $evts[$i];
        unset($evts[$i]);
      }
    }
    //print_r($this);
  }
  
  function asArray(): array {
    if ($this->mod <> 34)
      return [];
    if ($this->libelle_ap == $this->libelle_av)
      $array = [
        'libelle_av_ap'=> $this->libelle_av,
      ];
    elseif (!$this->libelle_av)
      $array = [
        'libelle_ap'=> $this->libelle_ap,
      ];
    else
      $array = [
        'libelle_av'=> $this->libelle_av,
        'libelle_ap'=> $this->libelle_ap,
      ];
    if ($this->fusionnees)
      $array['fusionnées'] = $this->fusionnees;
    if ($this->rattachees)
      $array['rattachées'] = $this->rattachees;
    //$array['evts'] = $this->evts;
    $labels = [
      31 => 'Fusion(31)',
      32 => 'CréationComNouvelle(32)',
      33 => 'Association(33)',
      34 => 'Intégration(34)',
    ];
    return [
      $labels[$this->mod] => [
        $this->cheflieu => $array,
      ],
    ];
  }
};

/*class IntegrationRattachee extends Mvt { // 34 - libInsee: Transformation de fusion association en fusion simple
  protected $cheflieu; // code du chef-lieu
  protected $libelle_av; // libellé avant du chef-lieu, null en cas de création (cas particulier)
  protected $libelle_ap; // libellé après du chef-lieu
  protected $fusionnees; // [{comi} => [typecom_av: {typecom_av}, libelle_av: {libelle_av}]]
  protected $evts; // liste des evts correspondants

  function __construct(string $date_eff, string $mod, array &$evts) {
    $this->date_eff = $date_eff;
    // identification du chef-lieu, c'est une ligne / (com_av == com_ap) && (typecom_av == 'COM') && (typecom_ap == 'COM')
    $this->cheflieu = null;
    foreach ($evts as $i => $evt) {
      if (($evt['com_av'] == $evt['com_ap']) && ($evt['typecom_av']=='COM') && ($evt['typecom_ap']=='COM')) {
        $this->cheflieu = $evt['com_av'];
        $this->libelle_av = $evt['libelle_av'];
        $this->libelle_ap = $evt['libelle_ap'];
        $this->evts = [ $evts[$i] ];
        unset($evts[$i]);
        break;
      }
    }
    if (!$this->cheflieu) {
      echo "Erreur d'identification du chef-lieu sur ";
      print_r($evts);
      die();
    }
    print_r($this);
    // 
  }
  
  function asArray(): array {
  }
};*/

if (php_sapi_name() <> 'cli') {
  if (!isset($_GET['action'])) {
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>mvts</title></head><body>\n";
    echo "<a href='?action=doublons'>Affichage des evts Insee en doublon</a><br>\n";
    echo "<a href='?action=evts'>Affichage des evts Insee</a><br>\n";
    echo "<a href='?action=testPattern'>testPattern</a><br>\n";
    echo "<a href='?action=mvts'>Affichage des mvts</a><br>\n";
    die();
  }
  else {
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>mvts $_GET[action]</title></head><body><pre>\n";
  }
}
else {
  $_GET['action'] = 'cli';
}

if (!($fevts = fopen(__DIR__.'/../data/mvtcommune2020.csv', 'r')))
  die("Erreur d'ouverture du fichier CSV des mouvements\n");

$evts = []; // [date_eff => [ mod => [ record ]]]
$prevRecord = []; // pour tester les doublons

$headers = fgetcsv($fevts, 0, ',');
foreach ($headers as $i => $header)
  $headers[$i] = strtolower($header);
while ($record = fgetcsv($fevts, 0, ',')) {
  if ($record == $prevRecord) {
    if ($_GET['action']=='doublons')
      echo implode('|', $record)," en doublon\n";
    continue;
  }
  $rec = [];
  foreach ($headers as $i => $header)
    $rec[$header] = $record[$i];
  $evts[$rec['date_eff']][$rec['mod']][] = $rec;
  $prevRecord = $record;
}

if ($_GET['action']=='doublons') {
  die("Fin doublons\n");
}

function showEvts($date_eff, $mod, $evts): void {
  $rows = [[
    Html::bold('typecom_av'), Html::bold('com_av'), Html::bold('libelle_av'),
    Html::bold('typecom_ap'), Html::bold('com_ap'), Html::bold('libelle_ap')
  ]];
  foreach ($evts as $evt) {
    //echo Yaml::dump($evt);
    $rows[] = [
      $evt['typecom_av'], $evt['com_av'], $evt['libelle_av'],
      $evt['typecom_ap'], $evt['com_ap'], $evt['libelle_ap'],
    ];
  }
  echo Html::table([], [
    [Html::bold('date_eff'), $date_eff],
    [Html::bold('mod'), $mod.' - '.TYPESDEVENEMENT[$mod]],
    [
      [ 'colspan'=> 2,
        'value'=> Html::table([], $rows),
      ]
    ]
  ]);
  //die();
}

if ($_GET['action'] == 'evts') {
  echo '</pre>';
  foreach ($evts as $date_eff => $evts1) {
    foreach ($evts1 as $mod => $evts2) {
      //if ($mod == 10)
      showEvts($date_eff, $mod, $evts2);
    }
  }
  die();
}
  
foreach ($evts as $date_eff => $evts1) {
  $mvts = []; // liste d'objets Mvt par $date_eff
  try {
    foreach ($evts1 as $mod => $evts2) {
      while ($evts2) {
        if ($_GET['action']=='testPattern')
          Mvt::testPattern($date_eff, $mod, $evts2);
        else
          $mvts[] = Mvt::create($date_eff, $mod, $evts2);
      }
    }
  }
  catch (Exception $e) {
    echo Html::bold($e->getMessage()),"\n\n";
  }
  $mvtsAsArray = [];
  foreach ($mvts as $mvt)
    if ($array = $mvt->asArray())
      $mvtsAsArray[] = $array;
  if ($mvtsAsArray)
    echo Yaml::dump([$date_eff => $mvtsAsArray], 7, 2),"\n";
}
echo "fin ok\n";