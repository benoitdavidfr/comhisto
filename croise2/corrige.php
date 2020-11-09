<?php
/*PhpDoc:
name: corrige.php
title: corrige.php - corrections manuelles
doc: |
journal: |
  7-8/11/2020:
    - v2
  16-17/9/2020:
    - ajout corrections manuelles pour traiter les élits de surface nulle
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
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>corrige</title></head><body><pre>\n";

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
    $this->evtsSrc = $version['évtsSrc'] ?? [];
    $this->evts = $version['évts'] ?? [];
    $this->etat = $version['état'] ?? [];
    $this->erat = $version['erat'] ?? [];
    $this->eltsp = array_merge($version['élits'] ?? [], $version['élitsNonDélégués'] ?? []);
  }
  
  function debut(): string { return $this->debut; }
  function type(): string { return in_array($this->etat['statut'], ['COMA', 'COMD', 'ARM']) ? 'r' : 's'; }
  function cinsee(): string { return $this->cinsee; }
  function statut(): string { return $this->etat['statut']; }
  function etat(): array { return $this->etat; }
  function evts(): array { return $this->evts; }
  function erat(): array { return $this->erat; }
  function setErat(array $erat): void { $this->erat = $erat; }
  function eltsp(): array { return $this->eltsp; }
    
  function asArray(): array {
    return array_merge(
      $this->evtsSrc ? ['évtsSrc' => $this->evtsSrc] : [],
      $this->evts ? ['évts' => $this->evts] : [],
      $this->etat ? ['etat' => $this->etat] : [],
      $this->erat ? ['erat' => $this->erat] : [],
      $this->eltsp ? ['élits' => $this->eltsp] : []
    );
  }
};


$yaml = Yaml::parseFile(__DIR__.'/../elits2/histelit.yaml');

// Corrections manuelles
// L'absorption de 33338 (Prignac) s'effectue dans la commune nouvelle 33055 (Blaignan-Prignac) et non dans la c. déléguée 33055
$yaml['contents'][33055]['2019-01-01']['élits'] = [33055];
$yaml['contents'][33055]['2019-01-01']['élitsNonDélégués'] = [33338];

// Je ne comprends pas pourquoi l'enregistrement 97617 est faux
$yaml['contents'][97617] = [
  '2011-03-31'=> [
    'état'=> ['statut'=> 'COM', 'name'=> 'Tsingoni'],
    'élits'=> [97617],
  ]
];


function error(string $message): void { // affiche un message d'erreur
  if (php_sapi_name() == 'cli')
    fprintf(STDERR, $message);
  else
    echo $message;
}

if (1) { // Vérification
  // Dans les versions valides, chaque élt ne doit appartenir qu'à un et un seul eltsp propre
  $verif = true;
  $allElts = []; // ensemble de tous les éléments sous la forme [$cinsee d'élit => {cinsee}@2020]
  foreach ($yaml['contents'] as $cinsee => $histo) {
    Histo::$all[$cinsee] = new Histo($cinsee, $histo);
  }
  foreach (Histo::$all as $cinsee => $histo) {
    if (!($vvalide = $histo->versionValide())) // entité périmée
      continue;
    foreach ($vvalide->eltsp() as $elt) {
      if (isset($allElts[$elt])) {
        error( "Erreur $elt présent dans ".$allElts[$elt]." et $cinsee@2020\n");
        $verif = false;
      }
      $allElts[$elt] = "$cinsee@2020";
    }
  }
  // vérification que tt code Insee sauf exceptions correspond à un élit
  foreach (Histo::$all as $cinsee => $histo) {
    if (in_array($cinsee, [13055,69123,75056])) // les codes de PLM ne sont pas des élits
      continue;
    if (in_array($cinsee, [97123,97127])) // Il est normal que StBarth et StMartin ne soient plus valides
      continue;
    
    $v0 = array_values($histo->versions())[0];
    if (isset($v0->evts()['avaitPourCode'])) {
      //echo "$cinsee avaitPourCode ",$v0->évts()['avaitPourCode'],"\n";
      continue;
    }
    if (!isset($allElts[$cinsee])) {
      error("Erreur, l'élément $cinsee n'appartient à aucune version valide\n");
      $verif = false;
    }
  }
  if (!$verif) {
    error("La vérification a échoué\n");
    exit(1);
  }
}

  
$yaml['title'] = "Historique des codes Insee augmenté des éléments intemporels corrigés";
$yaml['@id'] = 'http://id.georef.eu/comhisto/croise2/histelitp';
$yaml['created'] = date(DATE_ATOM);
echo Yaml::dump($yaml, 4, 2);
exit(0);
