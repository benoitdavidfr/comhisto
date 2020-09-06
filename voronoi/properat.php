<?php
/*PhpDoc:
name: properat.php
title: properat.php - propage les erat dans les changements de nom
doc: |
journal: |
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
    $this->eltsp = array_merge($version['eltsp'] ?? [], $version['eltsNonDélégués'] ?? []);
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
      $this->eltsp ? ['eltsp' => $this->eltsp] : []
    );
  }
};

$yaml = Yaml::parseFile('../zones/histeltp.yaml');
//print_r($yaml);
foreach ($yaml['contents'] as $cinsee => $histo) {
  $histo = new Histo($cinsee, $histo);
  $histo->corrigeErat();
  //echo Yaml::dump([$cinsee => $histo->asArray()], 3, 2);
  $yaml['contents'][$cinsee] = $histo->asArray();
}

// Correction manuelle

// L'absorption de 33338 (Prignac) s'effectue dans la commune nouvelle 33055 (Blaignan-Prignac) et non dans la c. déléguée 33055
$yaml['contents'][33055]['2019-01-01']['eltsp'] = [33055];
$yaml['contents'][33055]['2019-01-01']['eltsNonDélégués'] = [33338];


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
  foreach (Histo::$all as $cinsee => $histo) {
    $v0 = array_values($histo->versions())[0];
    if (isset($v0->evts()['avaitPourCode'])) {
      //echo "$cinsee avaitPourCode ",$v0->evts()['avaitPourCode'],"\n";
      continue;
    }
    if (!isset($allElts[$cinsee])) {
      if (!in_array($cinsee, ['97123','97127'])) {
        echo "Erreur, l'élément $cinsee n'appartient à aucune version valide\n";
        $verif = false;
      }
    }
  }
  if (!$verif) {
    die("La vérification a échoué\n");
  }
}

  
$yaml['title'] = "Historique des codes Insee augmenté des éléments positifs propres plus éléments non délégués";
$yaml['@id'] = 'http://id.georef.eu/comhisto/vronoi/histeltd';
$yaml['created'] = date(DATE_ATOM);
echo Yaml::dump($yaml, 4, 2);

