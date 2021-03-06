<?php
/*PhpDoc:
name: defelit0.php
title: defelit0.php - définition de chaque version de commune ou d'entité associée comme ensemble d'élts stables dans le temps v0
doc: |
  L'objectif est d'identifier le territoire de chaque version de commune ou d'entité associée afin de détecter les versions ayant
  le même territoire.
  Pour cela, chaque version est décrite par un ensemble d'élts stables dans le temps.
  Ces éléments sont les territoires des V0 des codes Insee, à l'exception des changements de code qui par déf. sont redondants.
  Un élt peut être noté en - lors d'une scission sans fusion préalable, ex. '+17411-17485'
  Dans ce cas, grâce aux simplifications, le - est une partie du +.
  Un ensemble d'élts est représenté par une chaine concaténant les code Insee dans l'ordre, précédés par le signe + ou -.
  
  Pour les CRAT, les elts sont les éléments propres, cad hors ERAT,
  sauf pour les COMM pour les quelles les elts de la commune déléguée propre sont intégrés dans elits.

  La topologie des versions est simplifiée selon les règles de simplif.inc.php

  S'utilise en non CLI en dév et en CLI en prod.
journal: |
  7/11/2020:
    - passage en V2
  6/9/2020
    - Modif pour supprimer les erat des elts et passer aux elts propres, cad hors Erat
  30/8/2020:
    - gestion des erat de la V0, les ARDM
    - gestion des chgts de code, ex 50592
  23/8/2020:
    - correction de Version::deduitElts() pour l'évènement seScindePourCréer
      L'action dépend de l'evt mirroir si la scisssion génère une simple ou non.
  20/8/2020:
    - ajout d'un évt aucun pour traiter les cas de simplification
  16/8/2020:
    - ajout d'une phase de simplification
  15/8/2020:
    - chgt de déf. des élts pour simplifier le code source
  14/8/2020:
    - création
includes:
  - simplif.inc.php
*/
ini_set('memory_limit', '1G');

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/simplif.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (php_sapi_name() <> 'cli') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>defelit0</title></head><body><pre>\n";
  if (!isset($_GET['action'])) {
    echo "</pre><a href='?action=showSimplif'>affiche la simplification</a><br>\n";
    echo "<a href='?action=showF1'>affiche une version intermédiaire</a><br>\n";
    echo "<a href='?action=showF2'>affiche la version finale</a><br>\n";
    die();
  }
}
else {
  $_GET = ['action'=> 'prod']; // production en sortie du fichier
}


// Gestion d'un ensemble d'élits
class ElitSet {
  protected $elits; // ens. d'élits en + ou - structuré [{codeInsee} => +1/-1] / {codeInsee} référence l'élit correspondant à ce code
  // et la valeur vaut +1 si le l'élit est à ajouter et -1 si il est à retirer 
  protected $vIds; // ens. de versions à ajouter/enlever structuré  [{cInsee}@{date} => +1/-1]
  
  // construction à partir d'un élit
  function __construct(string $elit='') {
    $this->elits = [];
    if ($elit)
      $this->elits[$elit] = 1;
    $this->vIds = [];
  }
  
  function asArray(): array {
    $array = [];
    foreach ($this->elits as $elit => $val)
      $array[] = ($val==1 ? '+':'-').$elit;
    foreach ($this->vIds as $vId => $val)
      $array[] = ($val==1 ? '+':'-').$vId;
    return $array;
  }
  
  function __toString(): string {
    $string = '';
    foreach ($this->elits as $elit => $val)
      $string .= ($val==1 ? '+':'-').$elit;
    foreach ($this->vIds as $vId => $val)
      $string .= ($val==1 ? '+':'-').$vId;
    return $string;
  }
  
  function empty(): self { $this->elits = []; $this->vIds = []; return $this; }
  
  function elit(string $elit): int { return $this->elits[$elit] ?? 0; }
  
  // ajoute des élits
  function addElits(array $elits): self {
    foreach ($elits as $elit) {
      if ($this->elit($elit) == -1)
        unset($this->elits[$elit]);
      else
        $this->elits[$elit] = 1;
    }
    return $this;
  }
  
  // retire des élits
  function remElits(array $elits): self {
    foreach ($elits as $elit) {
      if ($this->elit($elit) == 1)
        unset($this->elits[$elit]);
      else
        $this->elits[$elit] = -1;
    }
    return $this;
  }
  
  function vid(string $vid): int { return $this->vIds[$vid] ?? 0; }
  
  function addVId(string $vid): self {
    if ($this->vid($vid) == -1)
      unset($this->vIds[$vid]);
    else
      $this->vIds[$vid] = 1;
    return $this;
  }
  
  function remVId(string $vid): self {
    if ($this->vid($vid) == 1)
      unset($this->vIds[$vid]);
    else
      $this->vIds[$vid] = -1;
    return $this;
  }
  
  // traduit l'objet en elits
  function resolve(): void {
    //echo "resolve()@",Yaml::dump($this->asArray(), 0),"\n";
    $count = 100;
    while ($this->vIds) {
      //echo "avant trait vAdd: "; print_r($this);
      foreach ($this->vIds as $vid => $leftVal) {
        unset($this->vIds[$vid]);
        $right = Histo::getVersion($vid)->elits();
        if ($leftVal == 1) { // $right est à ajouter
          foreach ($right->elits as $elit => $rightVal) {
            if ($rightVal == 1)
              $this->addElits([$elit]);
            else
              $this->remElits([$elit]);
          }
          foreach ($right->vIds as $vid => $rightVal) {
            if ($rightVal == 1)
              $this->addVId($vid);
            else
              $this->remVId($vid);
          }
        }
        else { // ($leftVal == -1) - $right est à enlever
          foreach ($right->elits as $elit => $rightVal) {
            if ($rightVal == -1)
              $this->addElits([$elit]);
            else
              $this->remElits([$elit]);
          }
          foreach ($right->vIds as $vid => $rightVal) {
            if ($rightVal == -1)
              $this->addVId($vid);
            else
              $this->remVId($vid);
          }
        }
      }
      if ($count-- <= 0)
        throw new Exception("boucle détectée dans resolve()");
    }
    ksort($this->elits);
    ksort($this->vIds);
  }
};

// Historique d'un code Insee
class Histo {
  static $all; // [cinsee => Histo]
  protected $cinsee; // code Insee
  protected $versions; // [dv => Version]
  
  static function get(string $cinsee): Histo { return self::$all[$cinsee]; }
  
  static function getVersion(string $vid): Version {
    if (!($pos = strpos($vid, '@')))
      throw new Exception("Erreur getVersion($vid)");
    return self::get(substr($vid, 0, $pos))->version(substr($vid, $pos+1));
  }
  
  function __construct(string $cinsee, array $versions) {
    $this->cinsee = $cinsee;
    foreach ($versions as $dv => $version) {
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
  
  static function allAsArray(): array {
    $array = [];
    foreach (self::$all as $cinsee => $histo)
      $array[$cinsee] = $histo->asArray();
    return $array;
  }
  
  // version par date de début
  function version(string $dDebut): ?Version { return $this->versions[$dDebut] ?? null; }
  
  // retorouve la version définie par sa date de fin, y compris si elle est dans l'ancien code
  function versionParDateDeFin(string $dFin): ?Version {
    //echo "Histo::versionParDateDeFin($dFin)@$this->cinsee\n";
    if ($dFin == array_keys($this->versions)[0]) { // La version peut être dans l'ancien code
      $v0 = array_values($this->versions)[0];
      if (isset($v0->evts()['avaitPourCode'])) {
        return Histo::get($v0->evts()['avaitPourCode'])->versionParDateDeFin($dFin);
      }
      return null;
    }
    $vprec = null;
    foreach ($this->versions as $dv => $version) {
      if ($dv == $dFin)
        return $vprec;
      $vprec = $version;
    }
    return null;
  }
  
  // simplifie histo selon les règles de simplif.inc.php, renvoie true ssi une simplif est effectuée
  function simplif(): bool {
    $simplif = false;
    foreach ($this->versions as $version)
      if ($version->simplif())
        $simplif = true;
    return $simplif;
  }
  
  // définit les élits de chaque version par rapport l'élit de l'histo et des versions d'autres histos
  function definitElits(): void {
    //echo Yaml::dump(['debutDefEltsF1'=> [$this->cinsee => $this->asArray()]], 4, 2);
    $v0 = array_values($this->versions)[0];
    if (isset($v0->evts()['avaitPourCode'])) { // si c'est un changement de code
      //echo Yaml::dump($this->asArray());
      return; // la définition sera effectuée par l'ancien code
    }
    $elitSet = null;
    if ($v0->elits()) // si elits est déjà initialisé je l'utilise (cas d'un changement de code déjà fait à ne pas écraser)
      $elitSet = $v0->elits();
    else
      $elitSet = new ElitSet($this->cinsee);
    foreach ($this->versions as $version) {
      $elitSet = $version->deduitElits($elitSet);
    }
  }
  
  // traduit l'objet elits de chaque version en elts en supprimant les références vers des versions
  function resolveElits(): void {
    foreach ($this->versions as $version) {
      $version->elits()->resolve();
    }
  }
};

// Version d'un code Insee
class Version {
  protected $cinsee; // code Insee
  protected $debut; // date de début de la version
  protected $evtsSrc; // évts source de début sous la forme d'un array lorsque $evts est modifié
  protected $evts; // évts de début sous la forme d'un array
  protected $etat; // état de la version résultant des évènements
  protected $erat;  // liste des entités rattachées
  protected $elits; // ensemble d'élits correspondant à la version définis sous la forme d'un objet ElitSet
  
  function __construct(string $cinsee, string $debut, array $version) {
    $this->cinsee = $cinsee;
    $this->debut = $debut;
    $this->evtsSrc = [];
    $this->evts = $version['évts'] ?? [];
    $this->etat = $version['état'] ?? [];
    $this->erat = $version['erat'] ?? [];
    $this->elits = null;
    //print_r($version);
  }
  
  function asArray(): array {
    $array = [];
    if ($this->evtsSrc)
      $array['évtsSrc'] = $this->evtsSrc;
    if ($this->evts)
      $array['évts'] = $this->evts;
    if ($this->etat)
      $array['état'] = $this->etat;
    if ($this->erat)
      $array['erat'] = $this->erat;
    if ($this->elits)
      $array['élits0'] = $this->elits->__toString();
    return $array;
  }
  
  function cinsee(): string { return $this->cinsee; }
  function debut(): string { return $this->debut; }
  function evts(): array { return $this->evts; }
  function erat(): array { return $this->erat; }
  
  function elits(): ?ElitSet { return $this->elits; }
  
  function setElits(ElitSet $elits): void { $this->elits = $elits; }
  
  function id(): string { return $this->cinsee.'@'.$this->debut; }
  
  // simplifie la version selon les règles de simplif.inc.php, renvoie true ssi une simplif est effectuée
  function simplif(): bool {
    // Les 6 créations de commune à partir d'autres communes (crééeAPartirDe/contribueA) sont assimiliées à des scissions 
    // (crééeCOMParScissionDe/seScindePourCréer) en définissant dans Simplif::CREATIONS pour chaque commune créée la commune
    // principale dont est issue la commune créée.
    if (array_keys($this->evts) == ['crééeAPartirDe']) {
      $this->evtsSrc = $this->evts;
      $this->evts = ['crééeCOMParScissionDe'=> Simplif::CREATIONS[$this->cinsee]];
      return true;
    }
    if (array_keys($this->evts) == ['contribueA']) {
      $this->evtsSrc = $this->evts;
      if ($this->cinsee == Simplif::CREATIONS[$this->evts['contribueA']])
        $this->evts = ['seScindePourCréer'=> [$this->evts['contribueA']]];
      else
        $this->evts = ['aucun'=> null];
      return true;
    }
    // Les 6 dissolutions (seDissoutDans/reçoitUnePartieDe) sont assimilées à des fusions (fusionneDans/absorbe) en définissant dans
    // Simplif::DISSOLUTIONS pour chaque commune dissoute la commune principale d'absorption.
    if (array_keys($this->evts) == ['seDissoutDans']) {
      $this->evtsSrc = $this->evts;
      $this->evts = ['fusionneDans'=> Simplif::DISSOLUTIONS[$this->cinsee]];
      return true;
    }
    if (isset($this->evts['reçoitUnePartieDe'])) {
      $this->evtsSrc = $this->evts;
      if ($this->cinsee == Simplif::DISSOLUTIONS[$this->evts['reçoitUnePartieDe']])
        $this->evts = ['absorbe'=> [$this->evts['reçoitUnePartieDe']]];
      else
        $this->evts = ['aucun'=> null];
      return true;
    }
    return false;
  }
  
  // déduit les élits de cette version à partir de ceux de la version précédente et l'affecte
  function deduitElits(ElitSet $elits): ElitSet {
    //echo "Appel de deduitElts()@$this->cinsee@$this->debut\n";
    foreach ($this->evts as $evtVerb => $evtObjects) {
      switch ($evtVerb) {
        case 'sortDuPérimètreDuRéférentiel': { $elits->empty(); break; }

        case 'estModifiéeIndirectementPar': break;
        case 'changeDeNomPour': break;
        case 'aucun': break;
        
        case 'changeDeCodePour': {
          $nv = Histo::get($evtObjects);
          foreach ($nv->versions() as $dv => $version) {
            if ($dv >= $this->debut)
              $elits = $version->deduitElits($elits);
          }
          break;
        }
        
        case 'avaitPourCode': break;
        
        case 'fusionneDans': { $elits->empty(); break; }
        
        case 'absorbe': {
          foreach ($evtObjects as $erat) {
            $elits->addVId(Histo::get($erat)->versionParDateDeFin($this->debut)->id());
          }
          break;
        }
        
        case 'crééeCOMParScissionDe':
        case 'crééeCOMAParScissionDe':
        case 'crééARMParScissionDe': {
          $elits = new ElitSet($this->cinsee);
          break;
        }

        case 'seScindePourCréer': {
          /* Modif 6/9/2020 pour supprimer les erat des elits et passer aux elits propres, cas 89344/89325 */
          foreach ($evtObjects as $erat) {
            $elits->remVId(Histo::get($erat)->version($this->debut)->id());
          }
          break;
        }
        
        case 'sAssocieA': break;
        case 'resteRattachéeA': break;
        case 'gardeCommeRattachées': break;
        case 'devientDéléguéeDe': break;
        
        case 'associe': break;
        case 'prendPourDéléguées': break;
        
        case 'seDétacheDe': break;
        case 'détacheCommeSimples': break;
        
        
        default: {
          echo '<b>',Yaml::dump([$this->cinsee => [$this->debut => $this->asArray()]], 3, 2),"</b>";
          throw new Exception("evtVerb=$evtVerb");
        }
      }
    }
    $this->elits = clone $elits;
    return $elits;
  }
};

$histoFileName = __DIR__.'/../insee2/histo.yaml';
foreach (Yaml::parseFile($histoFileName)['contents'] as $cinsee => $versions) {
  Histo::$all[$cinsee] = new Histo($cinsee, $versions);
}
foreach (Histo::$all as $cinsee => $histo) {
  if ($histo->simplif()) {
    if ($_GET['action']=='showSimplif')
      echo Yaml::dump([$cinsee => $histo->asArray()], 3, 2);
  }
}
if ($_GET['action']=='showSimplif')
  die("Fin showSimplif\n");
foreach (Histo::$all as $cinsee => $histo) {
  $histo->definitElits();
  if ($_GET['action']=='showF1')
    echo Yaml::dump([$cinsee => $histo->asArray()], 3, 2);
}
if ($_GET['action']=='showF1')
  die("Fin showF1\n");

foreach (Histo::$all as $cinsee => $histo) {
  //$avant = $histo->asArray();
  //echo Yaml::dump([$cinsee => ['avant'=> $avant]], 4, 2);
  $histo->resolveElits();
  if ($_GET['action']=='showF2')
    echo Yaml::dump([$cinsee => $histo->asArray()], 3, 2);
}
if ($_GET['action']=='prod') {
  // code Php intégré dans le document pour définir l'affichage résumé de la commune
  $buildNameAdministrativeArea = <<<'EOT'
    $lastd = array_keys($item)[count($item)-1]; // dernière date
    if (isset($item[$lastd]['état'])) // si l'état existe alors version valide
      return $item[$lastd]['état']['name']." ($skey)";
    else // sinon utilisation du nom de la version précédente
      return '<s>'.$item[array_keys($item)[count($item)-2]]['état']['name']." ($skey)</s>";
EOT;
  echo Yaml::dump([
    'title'=> "Historique des codes Insee augmenté de la définition de chaque version comme ensemble d'éléments intemporels v0",
    '@id'=> 'http://id.georef.eu/comhisto/elits2/histelit0',
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
    'contents'=> Histo::allAsArray(),
  ], 4, 2);
}
die("eof:\n");
