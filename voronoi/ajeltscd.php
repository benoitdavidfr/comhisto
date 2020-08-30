<?php
/*PhpDoc:
name: ajeltscd.php
title: ajeltscd.php - ajout elts comme déléguée
*/
ini_set('memory_limit', '1G');

require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (php_sapi_name() <> 'cli')
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>ajeltscd</title></head><body><pre>\n";

// Historique des codes Insee
class Histo {
  protected $cinsee;
  protected $versions;
  
  function __construct(string $cinsee, array $histo) {
    $this->cinsee = $cinsee;
    foreach ($histo as $dv => $version) {
      $this->versions[$dv] = new Version($cinsee, $dv, $version);
    }
  }
  
  function asArray(): array {
    $array = [];
    foreach ($this->versions as $dv => $version)
      $array[$dv] = $version->asArray();
    return $array;
  }
  
  function ajEltsCD(): void {
    $prec = null;
    foreach ($this->versions as $dv => $version) {
      if (in_array('devientDéléguéeDe', array_keys($version->evts())) && in_array('prendPourDéléguées', array_keys($version->evts()))) {
        $version->setEltsCommeDel($prec->eltsp());
        //echo Yaml::dump([$this->cinsee => $this->asArray()], 3, 2);
      }
      $prec = $version;
    }
  }
};

// Version d'un Historique
class Version {
  protected $cinsee;
  protected $debut;
  protected $evts;
  protected $etat;
  protected $erat;
  protected $eltsp;
  protected $eltsCommeDeleguee;
  
  function __construct(string $cinsee, string $debut, array $version) {
    $this->cinsee = $cinsee;
    $this->debut = $debut;
    $this->evts = $version['evts'] ?? [];
    $this->etat = $version['etat'] ?? [];
    $this->erat = $version['erat'] ?? [];
    $this->eltsp = $version['eltsp'] ?? [];
    $this->eltsCommeDeleguee = [];
  }
  
  function setEltsCommeDel(array $elts): void { $this->eltsCommeDeleguee = $elts; }
  function type(): string { return in_array($this->etat['statut'], ['COMA', 'COMD', 'ARDM']) ? 'r' : 's'; }
  function cinsee(): string { return $this->cinsee; }
  function statut(): string { return $this->etat['statut']; }
  function etat(): array { return $this->etat; }
  function evts(): array { return $this->evts; }
  function eltsp(): array { return $this->eltsp; }
  function debut(): string { return $this->debut; }
  
  function asArray(): array {
    $array = [];
    if ($this->evts)
      $array['evts'] = $this->evts;
    if ($this->etat)
      $array['etat'] = $this->etat;
    if ($this->erat)
      $array['erat'] = $this->erat;
    if ($this->eltsp)
      $array['eltsp'] = $this->eltsp;
    if ($this->eltsCommeDeleguee)
      $array['eltsCommeDéléguée'] = $this->eltsCommeDeleguee;
    return $array;
  }
};

$yaml = Yaml::parseFile('../zones/histeltp.yaml');
//print_r($yaml);
foreach ($yaml['contents'] as $cinsee => $histo) {
  $histo = new Histo($cinsee, $histo);
  $histo->ajEltsCD();
  //echo Yaml::dump([$cinsee => $histo->asArray()], 3, 2);
  $yaml['contents'][$cinsee] = $histo->asArray();
}
$yaml['title'] = "Historique des codes Insee augmenté des éléments positifs y.c. spécifiques aux déléguées propres";
$yaml['@id'] = 'http://id.georef.eu/comhisto/vronoi/histeltd';
$yaml['created'] = date(DATE_ATOM);
echo Yaml::dump($yaml, 4, 2);

