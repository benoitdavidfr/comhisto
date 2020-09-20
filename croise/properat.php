<?php
/*PhpDoc:
name: properat.php
title: properat.php - propage les erat dans les changements de nom + corrections manuelles
doc: |
journal: |
  16-17/9/2020:
    - ajout corrections manuelles pour traiter les elits de surface nulle
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

/*// supprime des d'éléments d'un ensemble, retourne le résultat sans modifier les paramètres en entrée
function supprimeEltsDelEnsemble(array $elts, array $ensemble): array {
  $eltsRestants = []; // éléments restants sous la forme [$elt => 1] 
  foreach ($ensemble as $elt)
    if (!in_array($elt, $elts))
      $eltsRestants[$elt] = 1;
  ksort($eltsRestants);
  return array_keys($eltsRestants);
}

// Correction de fusion imprécises définies par l'Insee
// L'Insee indique souvent une fusion dans la commune nouvelle alors qu'elle est effectuée dans une des communes déléguées
// La fusion des $absorbées indiquées par l'Insee à la date $devt dans $ancienne est corrigée en $nouvelle
function corrigeFusion(array $absorbées, string $devt, string $ancienne, string $nouvelle) {
  foreach ($absorbées as $absorbée) {
    $yaml['contents'][$absorbée][$devt]['evts']['fusionneDans'] = $nouvelle; // et non $ancienne
  }
  // Je retire les absorbées dans l'ancienne absorbante
  if ($eltsRestants = supprimeEltsDelEnsemble($absorbées, $yaml['contents'][$ancienne][$devt]['evts']['absorbe']))
    $yaml['contents'][$ancienne][$devt]['evts']['absorbe'] = $eltsRestants;
  else
    unset($yaml['contents'][$ancienne][$devt]['evts']['absorbe']);
}*/

// L'INSEE indique que 14617 (Sainte-Marie-aux-Anglais) a été absorbée au 1/1/2017 par 14431 (Mézidon Vallée d'Auge).
// La carte montre que ce chef-lieu est dans r14422 (Le Mesnil-Mauger), une des COMD de 14431
// Je considère donc que 14617 fusionne dans 14422 et non dans 14431
// Cela a pour conséquence que l'elit 14617 se retrouve dans 14422
// Il faudrait faire cette correction avant la construction des elits
// Idem pour 14233 / 14431 -> 14422 
// Idem pour 14567 / 14431 -> 14422 
//corrigeFusion([14617,14233,14567], '2017-01-01', 14431, 14422);
$yaml['contents'][14617]['2017-01-01']['evts']['fusionneDans'] = 14422; // et non 14431
$yaml['contents'][14233]['2017-01-01']['evts']['fusionneDans'] = 14422; // et non 14431
$yaml['contents'][14567]['2017-01-01']['evts']['fusionneDans'] = 14422; // et non 14431
unset($yaml['contents'][14431]['2017-01-01']['evts']['absorbe']); // et non [14233, 14567, 14617]
$yaml['contents'][14431]['2017-01-01']['elits'] = [14133,14431]; // et non [14133, 14233, 14431, 14567, 14617]
$yaml['contents'][14422]['2017-01-01']['evts']['absorbe'] = [14233,14567,14617]; // en plus de { devientDéléguéeDe: 14431 }
$yaml['contents'][14422]['2017-01-01']['elits'] = [14233,14422,14567,14617]; // et non [14422]


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
$yaml['contents'][14697]['2017-01-01']['evts']['fusionneDans'] = 14472; // et non 14654
$yaml['contents'][14624]['2017-01-01']['evts']['fusionneDans'] = 14472; // et non 14654
$yaml['contents'][14295]['2017-01-01']['evts']['fusionneDans'] = 14472; // et non 14654
$yaml['contents'][14447]['2017-01-01']['evts']['fusionneDans'] = 14472; // et non 14654
$yaml['contents'][14314]['2017-01-01']['evts']['fusionneDans'] = 14472; // et non 14654
$yaml['contents'][14010]['2017-01-01']['evts']['fusionneDans'] = 14472; // et non 14654
$yaml['contents'][14234]['2017-01-01']['evts']['fusionneDans'] = 14472; // et non 14654
$yaml['contents'][14363]['2017-01-01']['evts']['fusionneDans'] = 14472; // et non 14654
$yaml['contents'][14067]['2017-01-01']['evts']['fusionneDans'] = 14472; // et non 14654
unset($yaml['contents'][14654]['2017-01-01']['evts']['absorbe']);
// et non [14010, 14067, 14234, 14295, 14314, 14363, 14447, 14624, 14697]
$yaml['contents'][14654]['2017-01-01']['elits'] = [14654];
// et non [14010, 14067, 14234, 14295, 14314, 14363, 14447, 14624, 14654, 14697]
$yaml['contents'][14472]['2017-01-01']['evts']['absorbe'] = [14010,14067,14234,14295,14314,14363,14447,14624,14697];
// en plus de devientDéléguéeDe
$yaml['contents'][14472]['2017-01-01']['elits'] = [14010,14067,14234,14295,14314,14363,14447,14472,14624,14697]; // et non [14472]


// L'INSEE indique que 14262 (La Ferrière-au-Doyen) fusionne dans 14061
// La carte et l'AE2020 montrent que ce chef-lieu est dans r14629, une COMD de 14061
// Je considère donc que 14262 fusionne dans 14629
$yaml['contents'][14262]['2016-01-01']['evts']['fusionneDans'] = 14629; // et non 14061
unset($yaml['contents'][14061]['2016-01-01']['evts']['absorbe']); // au lieu de {absorbe: [14262]}
$yaml['contents'][14061]['2016-01-01']['elits'] = [14061]; // au lieu de [14061, 14262]
$yaml['contents'][14629]['2016-01-01']['evts']['absorbe'] = [14262]; // en plus de { devientDéléguéeDe: 14061 }
$yaml['contents'][14629]['2016-01-01']['elits'] = [14262,14629]; // et non [14629]

// L'INSEE indique que 14490 (Parfouru-l'Éclin) fusionne dans 14143
// La carte et l'AE2020 montrent que ce chef-lieu est dans r14372, une COMD de 14143
// Je considère donc que 14490 fusionne dans 14372
$yaml['contents'][14490]['2017-01-01']['evts']['fusionneDans'] = 14372; // et non 14143
unset($yaml['contents'][14143]['2017-01-01']['evts']['absorbe']); // au lieu de {absorbe: [14490]}
$yaml['contents'][14143]['2017-01-01']['elits'] = [14143]; // au lieu de [14143, 14490]
$yaml['contents'][14372]['2017-01-01']['evts']['absorbe'] = [14490]; // en plus de { devientDéléguéeDe: 14143 }
$yaml['contents'][14372]['2017-01-01']['elits'] = [14372,14490]; // et non [14372]

// L'INSEE indique que 24049 (Born-de-Champs) fusionne dans 24028
// La carte et l'AE2020 montrent que ce chef-lieu est dans 24497, ce que je transcris
$yaml['contents'][24049]['2016-01-01']['evts']['fusionneDans'] = 24497; // et non 24028
unset($yaml['contents'][24028]['2016-01-01']['evts']['absorbe']); // au lieu de {absorbe: [24049]}
$yaml['contents'][24028]['2016-01-01']['elits'] = [24028]; // au lieu de [24028, 24049]
$yaml['contents'][24497]['2016-01-01']['evts']['absorbe'] = [24049]; // en plus de { devientDéléguéeDe: 24028 }
$yaml['contents'][24497]['2016-01-01']['elits'] = [24049,24497]; // et non [14372]

// L'INSEE indique que 28244 (Mervilliers) fusionne dans 28199
// La carte et l'AE2020 montrent que ce chef-lieu est dans 28002, ce que je transcris
$yaml['contents'][28244]['2019-01-01']['evts']['fusionneDans'] = 28002; // et non 28199
unset($yaml['contents'][28199]['2019-01-01']['evts']['absorbe']); // au lieu de {absorbe: [28244]}
$yaml['contents'][28199]['2019-01-01']['elits'] = [28199]; // au lieu de [28199, 28244]
$yaml['contents'][28002]['2019-01-01']['evts']['absorbe'] = [28244]; // en plus de { devientDéléguéeDe: 28199 }
$yaml['contents'][28002]['2019-01-01']['elits'] = [28002,28244]; // et non [28002]

// Erreur sur elit=38506 (Thuellin) / r38022 -> r38541
$yaml['contents'][38506]['2016-01-01']['evts']['fusionneDans'] = 38541; // et non 38022 
unset($yaml['contents'][38022]['2016-01-01']['evts']['absorbe']); // au lieu de {absorbe: [38506]}
$yaml['contents'][38022]['2016-01-01']['elits'] = [38022]; // au lieu de [38022, 38506]
$yaml['contents'][38541]['2016-01-01']['evts']['absorbe'] = [38506]; // en plus de { devientDéléguéeDe: 38022 }
$yaml['contents'][38541]['2016-01-01']['elits'] = [38506,38541]; // et non [38541]

// Erreur sur elit=49146 (Les Gardes) / r49092 -> r49281
$yaml['contents'][49146]['2015-12-15']['evts']['fusionneDans'] = 49281; // et non 49092 
unset($yaml['contents'][49092]['2015-12-15']['evts']['absorbe']); // au lieu de {absorbe: [49146]}
$yaml['contents'][49092]['2015-12-15']['elits'] = [49092]; // au lieu de [49092, 49146]
$yaml['contents'][49281]['2015-12-15']['evts']['absorbe'] = [49146]; // en plus de { devientDéléguéeDe: 49092 }
$yaml['contents'][49281]['2015-12-15']['elits'] = [49146,49281]; // et non [49281]

// Erreur sur elit=49357 (Trèves-Cunault) / r49149 -> 49094
$yaml['contents'][49357]['2016-01-01']['evts']['fusionneDans'] = 49094; // et non 49149 
unset($yaml['contents'][49149]['2016-01-01']['evts']['absorbe']); // au lieu de {absorbe: [49357]}
$yaml['contents'][49149]['2016-01-01']['elits'] = [49149]; // au lieu de [49149, 49357]
$yaml['contents'][49149]['2018-01-01']['elits'] = [49149]; // au lieu de [49149, 49357]
$yaml['contents'][49094]['2016-01-01']['evts']['absorbe'] = [49357]; // en plus de { devientDéléguéeDe: 49149 }
$yaml['contents'][49094]['2016-01-01']['elits'] = [49094,49357]; // et non [49094]
$yaml['contents'][49094]['2018-01-01']['elits'] = [49094,49357]; // et non [49094]

// Erreur sur elit=73172 (Montpascal) / r73135 -> 73203
$yaml['contents'][73172]['2019-01-01']['evts']['fusionneDans'] = 73203; // et non 73135 
unset($yaml['contents'][73135]['2019-01-01']['evts']['absorbe']); // au lieu de {absorbe: [73172]}
$yaml['contents'][73135]['2019-01-01']['elits'] = [73135]; // au lieu de [73135, 73172]
$yaml['contents'][73203]['2019-01-01']['evts']['absorbe'] = [73172]; // en plus de { devientDéléguéeDe: 73135 }
$yaml['contents'][73203]['2019-01-01']['elits'] = [73172,73203]; // et non [73203]

// Erreur sur elit=79228 (Rigné) / c79329 -> 79171
$yaml['contents'][79228]['2019-01-01']['evts']['fusionneDans'] = 79171; // et non 79329 
unset($yaml['contents'][79329]['2019-01-01']['evts']['absorbe']); // au lieu de {absorbe: [79228]}
$yaml['contents'][79329]['2019-01-01']['elits'] = [79329]; // au lieu de [79228, 79329]
$yaml['contents'][79171]['2019-01-01']['evts']['absorbe'] = [79228]; // en plus de { devientDéléguéeDe: 79329 }
$yaml['contents'][79171]['2019-01-01']['elits'] = [79171,79228]; // et non [79171]



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

