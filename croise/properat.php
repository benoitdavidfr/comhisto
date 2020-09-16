<?php
/*PhpDoc:
name: properat.php
title: properat.php - propage les erat dans les changements de nom + corrections manuelles
doc: |
journal: |
  15/9/2020:
    - ajout de corrections manuelles pour
      - préciser que Marseille et Paris n'ont aucun elit en propre qui sont dans les ardm
      - restructurer Lyon et son 5ème ardm pour préciser que 69232 fusionne dans le 5ème et non dans Lyon
  6/9/2020:
    - création, la vérif fonctionne
*/
ini_set('memory_limit', '1G');

require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (php_sapi_name() <> 'cli')
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>properat</title></head><body><pre>\n";

// Historique des codes Insee
class Histo {
  static $all;
  protected $cinsee;
  protected $versions;
  
  function __construct(string $cinsee, array $histo) {
    $this->cinsee = $cinsee;
    foreach ($histo as $dv => $version) {
      $this->versions[$dv] = new Version($cinsee, $dv, $version);
    }
  }
  
  function versions(): array { return $this->versions; }
  
  function asArray(): array {
    $array = [];
    foreach ($this->versions as $dv => $version)
      $array[$dv] = $version->asArray();
    return $array;
  }
  
  function corrigeErat(): void { // Un changement de nom conserve les erat
    //echo "corrigeErat() sur $this->cinsee\n";
    $vprec = null;
    foreach ($this->versions as $dv => $version) {
      if (array_keys($version->evts()) == ['changeDeNomPour']) {
        //echo "corrigeErat() changeDeNomPour sur $this->cinsee/$dv\n";
        if (!$version->erat() && $vprec && $vprec->erat()) {
          $version->setErat($vprec->erat());
        }
      }
      $vprec = $version; // version précédente
    }
  }
  
  function versionValide(): ?Version { // version valide de l'histo ou null
    $vvalide = array_values($this->versions)[count($this->versions)-1];
    return $vvalide->etat() ? $vvalide : null;
  }
};

// Version d'un Historique
class Version {
  protected $cinsee;
  protected $debut;
  protected $evtsSrc;
  protected $evts;
  protected $etat;
  protected $erat;
  protected $eltsp;
  
  function __construct(string $cinsee, string $debut, array $version) {
    $this->cinsee = $cinsee;
    $this->debut = $debut;
    $this->evtsSrc = $version['evtsSrc'] ?? [];
    $this->evts = $version['evts'] ?? [];
    $this->etat = $version['etat'] ?? [];
    $this->erat = $version['erat'] ?? [];
    $this->eltsp = array_merge($version['elits'] ?? [], $version['elitsNonDélégués'] ?? []);
  }
  
  function debut(): string { return $this->debut; }
  function type(): string { return in_array($this->etat['statut'], ['COMA', 'COMD', 'ARDM']) ? 'r' : 's'; }
  function cinsee(): string { return $this->cinsee; }
  function statut(): string { return $this->etat['statut']; }
  function etat(): array { return $this->etat; }
  function evts(): array { return $this->evts; }
  function erat(): array { return $this->erat; }
  function setErat(array $erat): void { $this->erat = $erat; }
  function eltsp(): array { return $this->eltsp; }
    
  function asArray(): array {
    return array_merge(
      $this->evtsSrc ? ['evtsSrc' => $this->evtsSrc] : [],
      $this->evts ? ['evts' => $this->evts] : [],
      $this->etat ? ['etat' => $this->etat] : [],
      $this->erat ? ['erat' => $this->erat] : [],
      $this->eltsp ? ['elits' => $this->eltsp] : []
    );
  }
};

$yaml = Yaml::parseFile('../elits/histelit.yaml');
//print_r($yaml);
foreach ($yaml['contents'] as $cinsee => $histo) {
  $histo = new Histo($cinsee, $histo);
  $histo->corrigeErat();
  //echo Yaml::dump([$cinsee => $histo->asArray()], 3, 2);
  $yaml['contents'][$cinsee] = $histo->asArray();
}

// Corrections manuelles

// L'absorption de 33338 (Prignac) s'effectue dans la commune nouvelle 33055 (Blaignan-Prignac) et non dans la c. déléguée 33055
$yaml['contents'][33055]['2019-01-01']['elits'] = [33055];
$yaml['contents'][33055]['2019-01-01']['elitsNonDélégués'] = [33338];


// Marseille n'a aucun elit en propre. Elle est uniquement composée de ses ardm
$yaml['contents'][13055]['1943-01-01']['elits'] = [];


// Paris n'a aucun elit en propre. Il est uniquement composée de ses ardm
$yaml['contents'][75056]['1943-01-01']['elits'] = [];


// Redéfinition de plusieurs histo pour préciser que 69232 fusionne dans 69385 (Lyon 5ème) et non dans 69123 (Lyon)
// restructuration complète de Lyon
$yaml['contents'][69123] = [
  "1943-01-01" => [
    "etat" => ["name" => "Lyon", "statut" => "COMS"],
    "erat" => ["aPourArdm" => [69381,69382,69383,69384,69385,69386,69387]],
    "elits" => [],
  ],
  "1959-02-08" => [
    "evts" => ["estModifiéeIndirectementPar" => [69387]], // Le 7ème se scinde pour créer le 8ème
    "etat" => ["name" => "Lyon", "statut" => "COMS"],
    "erat" => ["aPourArdm" => [69381,69382,69383,69384,69385,69386,69387,69388]],
    "elits" => [],
  ],
  "1963-08-07" => [
    "evts" => ["estModifiéeIndirectementPar" => [69385]], // Le 5ème absorbe 69232
    "etat" => ["name" => "Lyon", "statut" => "COMS"],
    "erat" => ["aPourArdm" => [69381,69382,69383,69384,69385,69386,69387,69388]],
    "elits" => [],
  ],
  "1964-08-12" => [
    "evts" => ["estModifiéeIndirectementPar" => [69385]], // Le 5ème se scinde pour créer le 9ème
    "etat" => ["name" => "Lyon", "statut" => "COMS"],
    "erat" => ["aPourArdm" => [69381,69382,69383,69384,69385,69386,69387,69388,69389]],
    "elits" => [],
  ],
];

// 69232 (Saint-Rambert-l'Île-Barbe) fusionne dans 69385 (le 5ème ardm) et non dans 69123 (Lyon)
$yaml['contents'][69232]['1963-08-07']['evts']['fusionneDans'] = 69385;

// restructuration complète du 5ème ardm de Lyon
// Note l'elit 69385 ne correspond à aucune version, c'est une exception
$yaml['contents'][69385] = [
  "1943-01-01" => [
    "etat" => ["name" => "Lyon 5e Arrondissement", "statut" => "ARDM", "crat" => 69123],
    "elits" => [69385,69389],
  ],
  "1963-08-07" => [
    "evts" => ["absorbe" => [69232]], // Le 5ème absorbe 69232
    "etat" => ["name" => "Lyon 5e Arrondissement", "statut" => "ARDM", "crat" => 69123],
    "elits" => [69232,69385,69389],
  ],
  "1964-08-12" => [
    "evts" => ["seScindePourCréer" => [69389]], // Le 5ème se scinde pour créer le 9ème qui contient 69232
    "etat" => ["name" => "Lyon 5e Arrondissement", "statut" => "ARDM", "crat" => 69123],
    "elits" => [69385],
  ],
];

// Le 9ème contient 69232
// Note l'elit 69389 ne correspond à aucune version, c'est une exception
$yaml['contents'][69389]['1964-08-12']['elits'] = [69232,69389];


// L'INSEE indique que 14617 (Sainte-Marie-aux-Anglais) a été absorbée au 1/1/2017 par 14431 (Mézidon Vallée d'Auge).
// La carte montre que ce chef-lieu est dans r14422 (Le Mesnil-Mauger), une des COMD de 14431
// Je considère donc que 14617 fusionne dans 14422 et non dans 14431
// Cela a pour conséquence que l'elit 14617 se retrouve dans 14422
// Il faudrait faire cette correction avant la construction des elits
$yaml['contents'][14617]['2017-01-01']['evts']['fusionneDans'] = 14422; // et non 14431
$yaml['contents'][14431]['2017-01-01']['evts']['absorbe'] = [14233, 14567]; // et non [14233, 14567, 14617]
$yaml['contents'][14431]['2017-01-01']['elits'] = [14133, 14233, 14431, 14567]; // et non [14133, 14233, 14431, 14567, 14617]
$yaml['contents'][14422]['2017-01-01']['evts']['absorbe'] = [14617]; // en plus de { devientDéléguéeDe: 14431 }
$yaml['contents'][14422]['2017-01-01']['elits'] = [14422, 14617]; // et non [14422]




if (1) { // Vérification
  // Dans les versions valides, chaque élt ne doit appartenir qu'à un et un seul eltsp propre
  $verif = true;
  $allElts = []; // ensemble de tous les éléments sous la forme [$cinsee d'élt => {cinsee}@2020]
  foreach ($yaml['contents'] as $cinsee => $histo) {
    Histo::$all[$cinsee] = new Histo($cinsee, $histo);
  }
  foreach (Histo::$all as $cinsee => $histo) {
    if (!($vvalide = $histo->versionValide())) // entité périmée
      continue;
    foreach ($vvalide->eltsp() as $elt) {
      if (isset($allElts[$elt])) {
        echo "Erreur $elt présent dans ",$allElts[$elt]," et $cinsee@2020\n";
        $verif = false;
      }
      $allElts[$elt] = "$cinsee@2020";
    }
  }
  // vérification que tt code Insee sauf exceptions correspond à un élit
  foreach (Histo::$all as $cinsee => $histo) {
    if (in_array($cinsee, [13055,69123,75056])) // les codes de PLM ne sont pas des elits
      continue;
    if (in_array($cinsee, [97123,97127])) // Il est normal que StBarth et StMartin ne soient plus valides
      continue;
    
    $v0 = array_values($histo->versions())[0];
    if (isset($v0->evts()['avaitPourCode'])) {
      //echo "$cinsee avaitPourCode ",$v0->evts()['avaitPourCode'],"\n";
      continue;
    }
    if (!isset($allElts[$cinsee])) {
      echo "Erreur, l'élément $cinsee n'appartient à aucune version valide\n";
      $verif = false;
    }
  }
  if (!$verif) {
    die("La vérification a échoué\n");
  }
}

  
$yaml['title'] = "Historique des codes Insee augmenté des éléments intemporels et propagation des erat";
$yaml['@id'] = 'http://id.georef.eu/comhisto/voronoi/histelitp';
$yaml['created'] = date(DATE_ATOM);
echo Yaml::dump($yaml, 4, 2);

