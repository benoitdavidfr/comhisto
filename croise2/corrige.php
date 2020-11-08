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


// L'INSEE indique que 14617 (Sainte-Marie-aux-Anglais) a été absorbée au 1/1/2017 par 14431 (Mézidon Vallée d'Auge).
// La carte montre que ce chef-lieu est dans r14422 (Le Mesnil-Mauger), une des COMD de 14431
// Je considère donc que 14617 fusionne dans 14422 et non dans 14431
// Cela a pour conséquence que l'elit 14617 se retrouve dans 14422
// Il faudrait faire cette correction avant la construction des élits
// Idem pour 14233 / 14431 -> 14422 
// Idem pour 14567 / 14431 -> 14422 
//corrigeFusion([14617,14233,14567], '2017-01-01', 14431, 14422);
$yaml['contents'][14617]['2017-01-01']['évts']['fusionneDans'] = 14422; // et non 14431
$yaml['contents'][14233]['2017-01-01']['évts']['fusionneDans'] = 14422; // et non 14431
$yaml['contents'][14567]['2017-01-01']['évts']['fusionneDans'] = 14422; // et non 14431
unset($yaml['contents'][14431]['2017-01-01']['évts']['absorbe']); // et non [14233, 14567, 14617]
$yaml['contents'][14431]['2017-01-01']['évts'] = [14133,14431]; // et non [14133, 14233, 14431, 14567, 14617]
$yaml['contents'][14422]['2017-01-01']['évts']['absorbe'] = [14233,14567,14617]; // en plus de { devientDéléguéeDe: 14431 }
$yaml['contents'][14422]['2017-01-01']['évts'] = [14233,14422,14567,14617]; // et non [14422]


// L'INSEE indique que 14697 (Tôtes) fusionne dans 14654 (Saint-Pierre-en-Auge)
// La carte et l'AE2020 montrent que ce chef-lieu est dans r14472, une COMD de 14654
// Je considère donc que 14697 fusionne dans 14472
// De même pour 14624 (Saint-Martin-de-Fresnay) qui fusionne dans 14472 et non 14654
// De même pour 14295 (Garnetot) qui fusionne dans 14472 et non 14654
// De même pour 14447 (Montpinçon) qui fusionne dans 14472 et non 14654
// De même pour 14314 (Grandmesnil) qui fusionne dans 14472 et non 14654
// De même pour 14010 (Ammeville) qui fusionne dans 14472 et non 14654
// De même pour 14234 (Écots) qui fusionne dans 14472 et non 14654
// De même pour 14363 (Lieury) qui fusionne dans 14472 et non 14654
// De même pour 14067 (Berville) qui fusionne dans 14472 et non 14654
$yaml['contents'][14697]['2017-01-01']['évts']['fusionneDans'] = 14472; // et non 14654
$yaml['contents'][14624]['2017-01-01']['évts']['fusionneDans'] = 14472; // et non 14654
$yaml['contents'][14295]['2017-01-01']['évts']['fusionneDans'] = 14472; // et non 14654
$yaml['contents'][14447]['2017-01-01']['évts']['fusionneDans'] = 14472; // et non 14654
$yaml['contents'][14314]['2017-01-01']['évts']['fusionneDans'] = 14472; // et non 14654
$yaml['contents'][14010]['2017-01-01']['évts']['fusionneDans'] = 14472; // et non 14654
$yaml['contents'][14234]['2017-01-01']['évts']['fusionneDans'] = 14472; // et non 14654
$yaml['contents'][14363]['2017-01-01']['évts']['fusionneDans'] = 14472; // et non 14654
$yaml['contents'][14067]['2017-01-01']['évts']['fusionneDans'] = 14472; // et non 14654
unset($yaml['contents'][14654]['2017-01-01']['évts']['absorbe']);
// et non [14010, 14067, 14234, 14295, 14314, 14363, 14447, 14624, 14697]
$yaml['contents'][14654]['2017-01-01']['élits'] = [14654];
// et non [14010, 14067, 14234, 14295, 14314, 14363, 14447, 14624, 14654, 14697]
$yaml['contents'][14472]['2017-01-01']['évts']['absorbe'] = [14010,14067,14234,14295,14314,14363,14447,14624,14697];
// en plus de devientDéléguéeDe
$yaml['contents'][14472]['2017-01-01']['élits'] = [14010,14067,14234,14295,14314,14363,14447,14472,14624,14697]; // et non [14472]


// L'INSEE indique que 14262 (La Ferrière-au-Doyen) fusionne dans 14061
// La carte et l'AE2020 montrent que ce chef-lieu est dans r14629, une COMD de 14061
// Je considère donc que 14262 fusionne dans 14629
$yaml['contents'][14262]['2016-01-01']['évts']['fusionneDans'] = 14629; // et non 14061
unset($yaml['contents'][14061]['2016-01-01']['évts']['absorbe']); // au lieu de {absorbe: [14262]}
$yaml['contents'][14061]['2016-01-01']['élits'] = [14061]; // au lieu de [14061, 14262]
$yaml['contents'][14629]['2016-01-01']['évts']['absorbe'] = [14262]; // en plus de { devientDéléguéeDe: 14061 }
$yaml['contents'][14629]['2016-01-01']['élits'] = [14262,14629]; // et non [14629]

// L'INSEE indique que 14490 (Parfouru-l'Éclin) fusionne dans 14143
// La carte et l'AE2020 montrent que ce chef-lieu est dans r14372, une COMD de 14143
// Je considère donc que 14490 fusionne dans 14372
$yaml['contents'][14490]['2017-01-01']['évts']['fusionneDans'] = 14372; // et non 14143
unset($yaml['contents'][14143]['2017-01-01']['évts']['absorbe']); // au lieu de {absorbe: [14490]}
$yaml['contents'][14143]['2017-01-01']['élits'] = [14143]; // au lieu de [14143, 14490]
$yaml['contents'][14372]['2017-01-01']['évts']['absorbe'] = [14490]; // en plus de { devientDéléguéeDe: 14143 }
$yaml['contents'][14372]['2017-01-01']['élits'] = [14372,14490]; // et non [14372]

// L'INSEE indique que 24049 (Born-de-Champs) fusionne dans 24028
// La carte et l'AE2020 montrent que ce chef-lieu est dans 24497, ce que je transcris
$yaml['contents'][24049]['2016-01-01']['évts']['fusionneDans'] = 24497; // et non 24028
unset($yaml['contents'][24028]['2016-01-01']['évts']['absorbe']); // au lieu de {absorbe: [24049]}
$yaml['contents'][24028]['2016-01-01']['élits'] = [24028]; // au lieu de [24028, 24049]
$yaml['contents'][24497]['2016-01-01']['évts']['absorbe'] = [24049]; // en plus de { devientDéléguéeDe: 24028 }
$yaml['contents'][24497]['2016-01-01']['élits'] = [24049,24497]; // et non [14372]

// L'INSEE indique que 28244 (Mervilliers) fusionne dans 28199
// La carte et l'AE2020 montrent que ce chef-lieu est dans 28002, ce que je transcris
$yaml['contents'][28244]['2019-01-01']['évts']['fusionneDans'] = 28002; // et non 28199
unset($yaml['contents'][28199]['2019-01-01']['évts']['absorbe']); // au lieu de {absorbe: [28244]}
$yaml['contents'][28199]['2019-01-01']['élits'] = [28199]; // au lieu de [28199, 28244]
$yaml['contents'][28002]['2019-01-01']['évts']['absorbe'] = [28244]; // en plus de { devientDéléguéeDe: 28199 }
$yaml['contents'][28002]['2019-01-01']['élits'] = [28002,28244]; // et non [28002]

// Erreur sur elit=38506 (Thuellin) / r38022 -> r38541
$yaml['contents'][38506]['2016-01-01']['évts']['fusionneDans'] = 38541; // et non 38022 
unset($yaml['contents'][38022]['2016-01-01']['évts']['absorbe']); // au lieu de {absorbe: [38506]}
$yaml['contents'][38022]['2016-01-01']['élits'] = [38022]; // au lieu de [38022, 38506]
$yaml['contents'][38541]['2016-01-01']['évts']['absorbe'] = [38506]; // en plus de { devientDéléguéeDe: 38022 }
$yaml['contents'][38541]['2016-01-01']['élits'] = [38506,38541]; // et non [38541]

// Erreur sur elit=49146 (Les Gardes) / r49092 -> r49281
$yaml['contents'][49146]['2015-12-15']['évts']['fusionneDans'] = 49281; // et non 49092 
unset($yaml['contents'][49092]['2015-12-15']['évts']['absorbe']); // au lieu de {absorbe: [49146]}
$yaml['contents'][49092]['2015-12-15']['élits'] = [49092]; // au lieu de [49092, 49146]
$yaml['contents'][49281]['2015-12-15']['évts']['absorbe'] = [49146]; // en plus de { devientDéléguéeDe: 49092 }
$yaml['contents'][49281]['2015-12-15']['élits'] = [49146,49281]; // et non [49281]

// Erreur sur elit=49357 (Trèves-Cunault) / r49149 -> 49094
$yaml['contents'][49357]['2016-01-01']['évts']['fusionneDans'] = 49094; // et non 49149 
unset($yaml['contents'][49149]['2016-01-01']['évts']['absorbe']); // au lieu de {absorbe: [49357]}
$yaml['contents'][49149]['2016-01-01']['élits'] = [49149]; // au lieu de [49149, 49357]
$yaml['contents'][49149]['2018-01-01']['élits'] = [49149]; // au lieu de [49149, 49357]
$yaml['contents'][49094]['2016-01-01']['évts']['absorbe'] = [49357]; // en plus de { devientDéléguéeDe: 49149 }
$yaml['contents'][49094]['2016-01-01']['élits'] = [49094,49357]; // et non [49094]
$yaml['contents'][49094]['2018-01-01']['élits'] = [49094,49357]; // et non [49094]

// Erreur sur elit=73172 (Montpascal) / r73135 -> 73203
$yaml['contents'][73172]['2019-01-01']['évts']['fusionneDans'] = 73203; // et non 73135 
unset($yaml['contents'][73135]['2019-01-01']['évts']['absorbe']); // au lieu de {absorbe: [73172]}
$yaml['contents'][73135]['2019-01-01']['élits'] = [73135]; // au lieu de [73135, 73172]
$yaml['contents'][73203]['2019-01-01']['évts']['absorbe'] = [73172]; // en plus de { devientDéléguéeDe: 73135 }
$yaml['contents'][73203]['2019-01-01']['élits'] = [73172,73203]; // et non [73203]

// Erreur sur elit=79228 (Rigné) / c79329 -> 79171
$yaml['contents'][79228]['2019-01-01']['évts']['fusionneDans'] = 79171; // et non 79329 
unset($yaml['contents'][79329]['2019-01-01']['évts']['absorbe']); // au lieu de {absorbe: [79228]}
$yaml['contents'][79329]['2019-01-01']['élits'] = [79329]; // au lieu de [79228, 79329]
$yaml['contents'][79171]['2019-01-01']['évts']['absorbe'] = [79228]; // en plus de { devientDéléguéeDe: 79329 }
$yaml['contents'][79171]['2019-01-01']['élits'] = [79171,79228]; // et non [79171]



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
        echo "Erreur $elt présent dans ",$allElts[$elt]," et $cinsee@2020\n";
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
      echo "Erreur, l'élément $cinsee n'appartient à aucune version valide\n";
      $verif = false;
    }
  }
  if (!$verif) {
    die("La vérification a échoué\n");
  }
}

  
$yaml['title'] = "Historique des codes Insee augmenté des éléments intemporels corrigés";
$yaml['@id'] = 'http://id.georef.eu/comhisto/croise2/histelitp';
$yaml['created'] = date(DATE_ATOM);
echo Yaml::dump($yaml, 4, 2);

