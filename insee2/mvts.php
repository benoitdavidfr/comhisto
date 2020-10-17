<?php
// structuration et visualisation des mouvements
ini_set('memory_limit', '2G');

require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

// Un objet correspond à un Mvt
// la fonction de création initialise un mvt à partir d'une liste d'évts et efface les évts consommés
class Mvt {
  protected $date_eff;
  protected $mod;

  function __construct(string $date_eff, string $mod, array &$evts) {
    $this->date_eff = $date_eff;
    $this->mod = $mod;
  }

  function asArray(): array {
    return [
      $this->mod => [
        'date_eff'=> $this->date_eff,
      ],
    ];
  }
};

class CreationComNouvelle extends Mvt {
  protected $comNouv; // code de la commune nouvelle
  protected $libelle_av; // libellé avant de la commune nouvelle
  protected $libelle_ap; // libellé après de la commune nouvelle
  protected $deleguees; // [{comi} => [libelle_av: {libelle_av}, libelle_ap: {libelle_ap}]]
  protected $evts; // liste des evts correspondants
  
  function __construct(string $date_eff, string $mod, array &$evts) {
    $this->date_eff = $date_eff;
    // identification d'une commune nouvelle, c'est une ligne / (com_av == com_ap) && (typecom_av == 'COM') && (typecom_ap == 'COM')
    $this->comNouv = null;
    foreach ($evts as $i => $evt) {
      if (($evt['com_av'] == $evt['com_ap']) && ($evt['typecom_av']=='COM') && ($evt['typecom_ap']=='COM')) {
        $this->comNouv = $evt['com_av'];
        $this->libelle_av = $evt['libelle_av'];
        $this->libelle_ap = $evt['libelle_ap'];
        $this->evts = [ $evts[$i] ];
        unset($evts[$i]);
        break;
      }
    }
    if (!$this->comNouv) {
      echo "Erreur d'identification d'une commune nouvelle sur ";
      print_r($evts);
      die();
    }
    // identification des communes déléguées, lignes / (com_ap == {ComNouv})
    foreach ($evts as $i => $evt) {
      if (($evt['com_ap'] == $this->comNouv) && ($evt['typecom_ap']=='COM')) {
        $this->deleguees[$evt['com_av']] = [
          'libelle_av' => $evt['libelle_av'],
        ];
        $this->evts[] = $evts[$i];
        unset($evts[$i]);
      }
    }
    // identification des libelle_ap // (com_av == com_ap) && (com_av in deleguees || com_av == {ComNouv})
    foreach ($evts as $i => $evt) {
      if (($evt['com_av'] == $evt['com_ap']) && (isset($this->deleguees[$evt['com_av']]) || ($evt['com_ap'] == $this->comNouv))) {
        $this->deleguees[$evt['com_av']] = [
          'libelle_av' => $evt['libelle_av'],
          'libelle_ap' => $evt['libelle_ap'],
        ];
        $this->evts[] = $evts[$i];
        unset($evts[$i]);
      }
    }
    //print_r($this);
  }
  
  function asArray(): array {
    return [
      'CréationComNouvelle(32)'=> [
        $this->comNouv => [
          'libelle_av'=> $this->libelle_av,
          'libelle_ap'=> $this->libelle_ap,
          'déléguées'=> $this->deleguees,
          //'evts'=> $this->evts,
        ],
      ]
    ];
  }
};

if (php_sapi_name() <> 'cli') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>mvts</title></head><body>\n";
  if (!isset($_GET['action'])) {
    echo "<a href='?action=evts'>Affichage des evts Insee</a><br>\n";
    echo "<a href='?action=mvts'>Affichage des mvts</a><br>\n";
    die();
  }
  else
    echo "<pre>\n";
}
else {
  $_GET['action'] = 'cli';
}

if (!($fevts = fopen(__DIR__.'/../data/mvtcommune2020.csv', 'r')))
  die("Erreur d'ouverture du fichier CSV des mouvements\n");

$evts = []; // [date_eff => [ mod => [ record ]]]

$headers = fgetcsv($fevts, 0, ',');
while ($record = fgetcsv($fevts, 0, ',')) {
  foreach ($headers as $i => $header)
    $rec[strtolower($header)] = $record[$i];
  //print_r($rec);
  $evts[$rec['date_eff']][$rec['mod']][] = $rec;
}

if ($_GET['action'] == 'evts') {
  die(Yaml::dump($evts, 4));
}
  
foreach ($evts as $date_eff => $evts1) {
  $mvts = []; // liste d'objets Mvt par $date_eff
  foreach ($evts1 as $mod => $evts2) {
    switch ($mod) {
      case '32': {
        while ($evts2) {
          $mvts[] = new CreationComNouvelle($date_eff, $mod, $evts2);
        }
        break;
      }
      default: {
        $mvts[] = new Mvt($date_eff, $mod, $evts2);
      }
    }
  }
  foreach ($mvts as $i => $mvt)
    $mvts[$i] = $mvt->asArray();
  echo Yaml::dump([$date_eff => $mvts], 7),"\n";
}
