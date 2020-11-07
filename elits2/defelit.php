<?php
/*PhpDoc:
name: defelit.php
title: defeltp.php - définition de chaque version de commune ou d'entité associée comme ensemble d'élts intemporels (elits)
doc: |
  L'objectif est d'identifier le territoire de chaque version de commune ou d'entité associée afin de détecter les versions ayant
  le même territoire ou les versions incluses dans une autre.
  Pour cela, chaque version est décrite par un ensemble d'élts stables dans le temps.
  Ces éléments sont les territoires des V0 des codes Insee, à l'exception:
    - des changements de code qui par déf. sont redondants,
    - des cinsee qui se réduisent par scission avant une fusion ; dans ce cas l'élt est le territoire le plus petit après scission.
  Ainsi chaque version peut être représentée par un ensemble de ces éléments codé comme une liste.

  Reprend les elts produits par defelt.php et fabrique des élts positifs.
  La topologie des versions est simplifiée selon les règles de simplif.inc.php

  S'utilise en non CLI en dév et en CLI en prod.
journal: |
  7/11/2020:
    - passage en V2
  30/8/2020:
    - correction manuelle 27528/27701
  22/8/2020:
    - création
*/

//die(phpversion());

require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (php_sapi_name() <> 'cli') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>defeltp</title></head><body><pre>\n";
  if (!isset($_GET['action'])) {
    echo "</pre><a href='?action=showAjouts'>affiche les ajouts</a><br>\n";
    echo "<a href='?action=showResult'>affiche la version finale</a><br>\n";
    die();
  }
}
else {
  $_GET = ['action'=> 'prod']; // production en sortie du fichier
}

// Ensemble d'éléments
class EltSet {
  protected $elts; // stockage sous la forme [$elt => 1/-1] en rajoutant un 'e' dans la clé pour éviter les clés numériques

  function __construct(string $elts='') {
    $this->elts = [];
    while ($elts) {
      $first = substr($elts, 0, 6);
      if (substr($first, 0, 1) == '+')
        $this->elts['e'.substr($first, 1)] = +1;
      elseif (substr($first, 0, 1) == '-')
        $this->elts['e'.substr($first, 1)] = -1;
      else
        throw new Exception("Erreur sur elt $first");
      $elts = substr($elts, 6);
    }
    ksort($this->elts);
  }
  
  function elt(string $elt): int { return $this->elts['e'.$elt] ?? 0; }
  
  function __toString(): string {
    $string = '';
    foreach ($this->elts as $elt => $val)
      $string .= ($val==1 ? '+':'-').substr($elt, 1);
    return $string;
  }
  
  function asArray(): array {
    $array = [];
    foreach ($this->elts as $elt => $val) {
      $elt = substr($elt, 1);
      if (substr($elt, 0, 1) == '0')
        $array[] = ($val==1 ? '':'-').$elt; // elt encodé comme chaine
      else
        $array[] = $val * $elt; // encodé comme entier
    }
    return $array;
  }
  
  function contientEltNegatif(): bool {
    foreach ($this->elts as $elt => $v) {
      if ($v <> 1) return true;
    }
    return false;
  }
  
  // contient $elt comme seul élt positif et au moins 1 elt négatif, renvoit la liste des elts négatifs
  function est1PosNNeg(string $elt): array {
    $negElts = [];
    if (count($this->elts) < 2)
      return [];
    if ($this->elt($elt) <> 1)
      return [];
    foreach ($this->elts as $e => $v) {
      if ($v == -1)
        $negElts[] = substr($e, 1);
      elseif ($e <> 'e'.$elt)
        return [];
    }
    return $negElts;
  }

  // modifie $this en fonction de la liste des éléments  ajouter
  function ajouts(array $ajouts): self {
    foreach ($ajouts as $eltMod => $eltsAjoutes) {
      if ($this->elt($eltMod) == 1) {
        foreach ($eltsAjoutes as $eltAjoute) {
          $this->addElt($eltAjoute);
        }
      }
    }
    ksort($this->elts);
    return $this;
  }
  
  function addElt(string $elt): void {
    switch ($this->elt($elt)) {
      case  0: { $this->elts["e$elt"] = 1; break; }
      case -1: { unset($this->elts["e$elt"]); break; }
      case  1: throw new Exception("Erreur ajout $elt à $this");
    }
  }
};

// Historique d'un code Insee
class Histo {
  static $all; // [cinsee => Histo]
  protected $cinsee; // code Insee
  protected $versions; // [dv => Version]

  function __construct(string $cinsee, array $versions) {
    $this->cinsee = $cinsee;
    foreach ($versions as $dv => $version) {
      $this->versions[$dv] = new Version($cinsee, $dv, $version);
    }
  }

  function contientEltNegatif(): bool {
    foreach ($this->versions as $dv => $version) {
      if ($version->contientEltNegatif())
        return true;
    }
    return false;
  }

  // correspond à un seul élt positif du cinsee et à 1 à n élts négatifs, renvoit celui de la version ayant le plus de Neg ou []
  function est1PosNNeg(): array {
    $negElts = [];
    foreach ($this->versions as $dv => $version) {
      if ($neg = $version->est1PosNNeg($this->cinsee))
        $negElts = $neg;
    }
    return $negElts;
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
  protected $elts; // ensemble d'élts correspondant à la version, défini sous la forme d'un objet EltSet

  function __construct(string $cinsee, string $debut, array $version) {
    $this->cinsee = $cinsee;
    $this->debut = $debut;
    $this->evtsSrc = $version['évtsSrc'] ?? [];
    $this->evts = $version['évts'] ?? [];
    $this->etat = $version['état'] ?? [];
    $this->erat = $version['erat'] ?? [];
    $this->elts = new EltSet($version['élits0'] ?? '');
    //print_r($version);
  }

  // contient un elt négatif
  function contientEltNegatif(): bool { return $this->elts->contientEltNegatif(); }
  
  // correspond à l'élt positif en param. et 1 à n élts négatifs, renvoit alors la liste des elts négatifs sinon []
  function est1PosNNeg(string $elt): array { return $this->elts->est1PosNNeg($elt); }
};

$histelts = Yaml::parsefile(__DIR__.'/histelit0.yaml');

// construction de la liste des remplacements
$ajouts = []; // pour chaque elt qui prend au moins un négatif, la liste de ces négatifs pris
foreach ($histelts['contents'] as $cinsee => $histov) {
  $histo = new Histo($cinsee, $histov);
  //if ($histo->contientEltNegatif())
    //echo Yaml::dump([$cinsee => $histov], 3, 2);
  if ($negElts = $histo->est1PosNNeg()) {
    //echo "$cinsee -> ",implode(',', $negElts),"\n",Yaml::dump([$cinsee => $histov], 3, 2);
    $ajouts[$cinsee] = $negElts;
  }
}
//print_r($ajouts);
if ($_GET['action']=='showAjouts') {
  echo Yaml::dump($ajouts, 1);
  die("Fin showAjouts\n");
}

// Mise en oeuvre des remplacements
foreach ($histelts['contents'] as $cinsee => &$histo) {
  foreach ($histo as $dv => &$version) {
    if (isset($version['élits0'])) {
      $eltSet = new EltSet($version['élits0']);
      unset($version['élits0']);
      $version['élits'] = $eltSet->ajouts($ajouts)->asArray();
    }
  }
}


// Modifications ponctuelles
// Le Vaudreuil (27528) contribue à 27701 après avoir absorbé 27443
$histelts['contents'][27528]['1943-01-01']['élits'] = [27528, 27701];
$histelts['contents'][27528]['1969-04-15']['élits'] = [27443, 27528, 27701];
$histelts['contents'][27528]['1981-09-28']['élits'] = [27443, 27528];

// 97306/97361
$histelts['contents'][97306]['1943-01-01']['élits'] = [97306, 97361];
$histelts['contents'][97306]['1969-03-27']['élits'] = [97306, 97355, 97361];
$histelts['contents'][97306]['1989-01-01']['élits'] = [97306, 97355];

// Lyon
$histelts['contents'][69385]['1943-01-01']['élits'] = [69385, 69389];
$histelts['contents'][69385]['1963-08-07']['élits'] = [69232, 69385, 69389];
$histelts['contents'][69385]['1964-08-12']['élits'] = [69385];
$histelts['contents'][69389]['1964-08-12']['élits'] = [69232, 69389];


// Vérif
foreach ($histelts['contents'] as $cinsee => $histo) {
  foreach ($histo as $dv => $version) {
    if (isset($version['eltsp'])) {
      foreach ($version['eltsp'] as $eltp) {
        if (substr($eltp, 0, 1) == '-')
          echo "Erreur dans $cinsee eltp $eltp négatif\n";
      }
    }
  }
}

$histelts['title'] = "Historique des codes Insee augmenté de la définition de chaque version comme ensemble d'élts intemporels positifs";
$histelts['@id'] = "http://id.georef.eu/comhisto/elits2/histelit";
$histelts['description'] = "Voir la documentation sur https://github.com/benoitdavidfr/comhisto";
$histelts['created'] = date(DATE_ATOM);
$histelts['valid'] = '2020-01-01';
$histelts['$schema'] = 'http://id.georef.eu/comhisto/insee2/exhisto/$schema';

echo Yaml::dump($histelts, 4, 2);
