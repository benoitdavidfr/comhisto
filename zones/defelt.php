<?php
/*PhpDoc:
name: defelt.php
title: defelt.php - définition de chaque version de commune ou d'entité associée comme ensemble d'élts stables dans le temps
screens:
doc: |
  L'objectif est d'identifier le territoire de chaque version de commune ou d'entité associée afin de détecter les versions ayant
  le même territoire.
  Pour cela, chaque version est décrite par un ensemble d'élts stables dans le temps.
  Ces éléments sont les territoires des V0 des codes Insee, à l'exception des changements de code qui par déf. sont redondants.
  Un élt peut être noté en - lors d'une scission sans fusion préalable, ex. '+17411-17485'
  Dans ce cas, grâce aux simplifications, le - est une partie du +.
  Un ensemble d'élts est représenté par une chaine concaténant les code Insee dans l'ordre, précédés par le signe + ou -.

  La topologie des versions est simplifiée selon les règles de simplif.inc.php

  S'utilise en non CLI en dév et en CLI en prod.
journal: |
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
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>defelt</title></head><body><pre>\n";
  if (!isset($_GET['action'])) {
    echo "</pre><a href='?action=showSimplif'>affiche la simplification</a><br>\n";
    echo "<a href='?action=affiche une version intermédiaire'>showF1</a><br>\n";
    echo "<a href='?action=showF2'>affiche la version finale</a><br>\n";
    die();
  }
}
else {
  $_GET = ['action'=> 'prod']; // production en sortie du fichier
}


// Ensemble d'éléments
class EltSet {
  protected $elts; // ens. d'élts en + ou - structuré [{codeInsee} => +1/-1] / {codeInsee} référence l'élt correspondant à ce code
  // et la valeur vaut +1 si le l'élt est à ajouter et -1 si il est à retirer 
  protected $vIds; // ens. de versions à ajouter/enlever structuré  [{cInsee}@{date} => +1/-1]
  
  // construction à partir d'un élt
  function __construct(string $elt) {
    $this->elts = [$elt => 1];
    $this->vIds = [];
  }
  
  function asArray(): array {
    $array = [];
    foreach ($this->elts as $elt => $val)
      $array[] = ($val==1 ? '+':'-').$elt;
    foreach ($this->vIds as $vId => $val)
      $array[] = ($val==1 ? '+':'-').$vId;
    return $array;
  }
  
  function __toString(): string {
    $string = '';
    foreach ($this->elts as $elt => $val)
      $string .= ($val==1 ? '+':'-').$elt;
    foreach ($this->vIds as $vId => $val)
      $string .= ($val==1 ? '+':'-').$vId;
    return $string;
  }
  
  function empty(): self { $this->elts = []; $this->vIds = []; return $this; }
  
  function elt(string $elt): int { return $this->elts[$elt] ?? 0; }
  
  // ajoute des élts
  function addElts(array $elts): self {
    foreach ($elts as $elt) {
      if ($this->elt($elt) == -1)
        unset($this->elts[$elt]);
      else
        $this->elts[$elt] = 1;
    }
    return $this;
  }
  
  // retire des élts
  function remElts(array $elts): self {
    foreach ($elts as $elt) {
      if ($this->elt($elt) == 1)
        unset($this->elts[$elt]);
      else
        $this->elts[$elt] = -1;
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
  
  // traduit l'objet en elts
  function resolve(): void {
    //echo "resolve()@",Yaml::dump($this->asArray(), 0),"\n";
    $count = 100;
    while ($this->vIds) {
      //echo "avant trait vAdd: "; print_r($this);
      foreach ($this->vIds as $vid => $leftVal) {
        unset($this->vIds[$vid]);
        $right = Histo::getVersion($vid)->elts();
        if ($leftVal == 1) { // $right est à ajouter
          foreach ($right->elts as $elt => $rightVal) {
            if ($rightVal == 1)
              $this->addElts([$elt]);
            else
              $this->remElts([$elt]);
          }
          foreach ($right->vIds as $vid => $rightVal) {
            if ($rightVal == 1)
              $this->addVId($vid);
            else
              $this->remVId($vid);
          }
        }
        else { // ($leftVal == -1) - $right est à enlever
          foreach ($right->elts as $elt => $rightVal) {
            if ($rightVal == -1)
              $this->addElts([$elt]);
            else
              $this->remElts([$elt]);
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
    ksort($this->elts);
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
  
  // définit les élts de chaque version par rapport l'élt de l'histo et des versions d'autres histos
  function defEltsF1(): void {
    //echo Yaml::dump(['debutDefEltsF1'=> [$this->cinsee => $this->asArray()]], 4, 2);
    $eltSet = new EltSet($this->cinsee);
    foreach ($this->versions as $version) {
      $eltSet = $version->deduitElts($eltSet);
    }
  }
  
  // traduit l'objet elts de chaque version en elts en supprimant les références vers des versions
  function defEltsF2(): void {
    foreach ($this->versions as $version) {
      $version->elts()->resolve();
    }
  }
};

// Version d'un code Insee
class Version {
  protected $cinsee; // code Insee
  protected $debut; // date de début de la version
  protected $evtsSrc; // evts source de début sous la forme d'un array lorsque $evts est modifié
  protected $evts; // evts de début sous la forme d'un array
  protected $etat; // etat de la version résultant des évènements
  protected $erat;
  protected $elts; // ensemble d'élts correspondant à la version définis sous la forme d'un objet EltSet
  
  function __construct(string $cinsee, string $debut, array $version) {
    $this->cinsee = $cinsee;
    $this->debut = $debut;
    $this->evtsSrc = [];
    $this->evts = $version['evts'] ?? [];
    $this->etat = $version['etat'] ?? [];
    $this->erat = $version['erat'] ?? [];
    $this->elts = null;
    //print_r($version);
  }
  
  function asArray(): array {
    $array = [];
    if ($this->evtsSrc)
      $array['evtsSrc'] = $this->evtsSrc;
    if ($this->evts)
      $array['evts'] = $this->evts;
    if ($this->etat)
      $array['etat'] = $this->etat;
    if ($this->erat)
      $array['erat'] = $this->erat;
    if ($this->elts)
      $array['elts'] = $this->elts->__toString();
    return $array;
  }
  
  function cinsee(): string { return $this->cinsee; }
  function debut(): string { return $this->debut; }
  function evts(): array { return $this->evts; }
  
  function elts(): EltSet {
    if (!$this->elts)
      throw new Exception("elts null pour $this->cinsee@$this->debut");
    return $this->elts;
  }
  
  function setElts(EltSet $elts): void { $this->elts = $elts; }
  
  function id(): string { return $this->cinsee.'@'.$this->debut; }
  
  // simplifie la version selon les règles de simplif.inc.php, renvoie true ssi une simplif est effectuée
  function simplif(): bool {
    // Les 6 créations de commune à partir d'autres communes (crééeAPartirDe/contribueA) sont assimiliées à des scissions 
    // (crééeCommeSimpleParScissionDe/seScindePourCréer) en définissant dans Simplif::CREATIONS pour chaque commune créée la commune
    // principale dont est issue la commune créée.
    if (array_keys($this->evts) == ['crééeAPartirDe']) {
      $this->evtsSrc = $this->evts;
      $this->evts = ['crééeCommeSimpleParScissionDe'=> Simplif::CREATIONS[$this->cinsee]];
      return true;
    }
    if (array_keys($this->evts) == ['contribueA']) {
      $this->evtsSrc = $this->evts;
      if ($this->cinsee == Simplif::CREATIONS[$this->evts['contribueA']])
        $this->evts = ['seScindePourCréer'=> [$this->evts['contribueA']]];
      else
        $this->evts = ['aucun'=> []];
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
        $this->evts = ['aucun'=> []];
      return true;
    }
    return false;
  }
  
  // déduit les élts de cette version à partir de ceux de la version précédente et l'affecte
  function deduitElts(EltSet $elts): EltSet {
    //echo "Appel de deduitElts()@$this->cinsee@$this->debut\n";
    foreach ($this->evts as $evtVerb => $evtObjects) {
      switch ($evtVerb) {
        case 'sortDuPérimètreDuRéférentiel': { $elts->empty(); break; }

        case 'estModifiéeIndirectementPar': break;
        case 'changeDeNomPour': break;
        case 'aucun': break;
        
        case 'changeDeCodePour': {
          $nv = Histo::get($evtObjects);
          foreach ($nv->versions() as $dv => $version) {
            if ($dv >= $this->debut)
              $elts = $version->deduitElts($elts);
          }
          break;
        }
        
        case 'avaitPourCode': break;
        
        /*case 'XXreçoitUnePartieDe': { // cas supprimé en raison de la simplification
          $cdel = $evtObjects; // commune dissoute
          if ($this->cinsee == Simplif::DISSOLUTIONS[$cdel]) // si c'est la commune principale alors elle est augmentée
            $elts->addVId(Histo::get($cdel)->versionParDateDeFin($this->debut)->id());
          break;
        }
        
        case 'XXseDissoutDans': { $elts->empty(); break; } // cas supprimé en raison de la simplification
        
        case 'XXcontribueA': { // cas supprimé en raison de la simplification
          //echo '<b>',Yaml::dump([$this->cinsee => [$this->debut => $this->asArray()]], 3, 2),"</b>";
          $ccree = $evtObjects; // commune créée
          if (!isset(Simplif::CREATIONS[$ccree]))
            throw new Exception("Manque CREATIONS de $ccree");
          if ($this->cinsee == Simplif::CREATIONS[$ccree])
            $elts->remVId(Histo::get($ccree)->version($this->debut)->id());
          break;
        }
        
        case 'XXcrééeAPartirDe': break; // cas supprimé en raison de la simplification
        */
        case 'fusionneDans': { $elts->empty(); break; }
        
        case 'absorbe': {
          foreach ($evtObjects as $erat) {
            $elts->addVId(Histo::get($erat)->versionParDateDeFin($this->debut)->id());
          }
          break;
        }
        
        case 'crééeCommeSimpleParScissionDe':
        case 'crééeCommeAssociéeParScissionDe':
        case 'crééCommeArrondissementMunicipalParScissionDe': {
          $elts = new EltSet($this->cinsee);
          break;
        }

        case 'seScindePourCréer': {
          foreach ($evtObjects as $erat) {
            $elts->remVId(Histo::get($erat)->version($this->debut)->id());
          }
          break;
        }
        
        case 'sAssocieA': break;
        case 'resteAssociéeA': break;
        case 'gardeCommeAssociées': break;
        case 'devientDéléguéeDe': break;
        case 'resteDéléguéeDe': break;
        case 'gardeCommeDéléguées': break;
        
        case 'prendPourAssociées': 
        case 'prendPourDéléguées': {
          foreach ($evtObjects as $erat) {
            if ($erat <> $this->cinsee) {
              if (!($version = Histo::get($erat)->versionParDateDeFin($this->debut)))
                throw new Exception("Erreur version non définie sur $erat pour dFin=$this->debut");
              $elts->addVId($version->id());
            }
          }
          break;
        }
        
        case 'seDétacheDe': break;
        case 'détacheCommeSimples': {
          foreach ($evtObjects as $erat) {
            $elts->remVId(Histo::get($erat)->versionParDateDeFin($this->debut)->id());
          }
          break;
        }
        
        
        default: {
          echo '<b>',Yaml::dump([$this->cinsee => [$this->debut => $this->asArray()]], 3, 2),"</b>";
          throw new Exception("evtVerb=$evtVerb");
        }
      }
    }
    $this->elts = clone $elts;
    return $elts;
  }
};

foreach (Yaml::parseFile('../insee/histov.yaml')['contents'] as $cinsee => $versions) {
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
  $histo->defEltsF1();
  if ($_GET['action']=='showF1')
    echo Yaml::dump([$cinsee => $histo->asArray()], 3, 2);
}
if ($_GET['action']=='showF1')
  die("Fin showF1\n");
foreach (Histo::$all as $cinsee => $histo) {
  //$avant = $histo->asArray();
  //echo Yaml::dump([$cinsee => ['avant'=> $avant]], 4, 2);
  $histo->defEltsF2();
  if ($_GET['action']=='showF2')
    echo Yaml::dump([$cinsee => $histo->asArray()], 3, 2);
}
if ($_GET['action']=='prod') {
  // code Php intégré dans le document pour définir l'affichage résumé de la commune
  $buildNameAdministrativeArea = <<<'EOT'
    $lastd = array_keys($item)[count($item)-1]; // dernière date
    if (isset($item[$lastd]['etat'])) // si l'état existe alors version valide
      return $item[$lastd]['etat']['name']." ($skey)";
    else // sinon utilisation du nom de la version précédente
      return '<s>'.$item[array_keys($item)[count($item)-2]]['etat']['name']." ($skey)</s>";
EOT;
  echo Yaml::dump([
    'title'=> "Historique des codes Insee augmenté de la définition de chaque version comme ensemble d'éléments",
    '@id'=> 'http://id.georef.eu/comhisto/zones/histelt',
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
    'contents'=> Histo::allAsArray(),
  ], 4, 2);
}
die("eof:\n");
