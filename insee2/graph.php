<?php
/*PhpDoc:
name: graph.php
title: graph.php - structuration, visualisation et construction du Rpicom à partir des mouvements Insee
doc: |
  réécriture de l'interprétation des lignes du fichier mvtcommune2020
  Le script permet:
    - diverses visualisations (fichier brut, doublons, évts groupés, mouvements interprétés),
    - l'extraction des lignes non conformes aux specs,
    - la construction du Rpicom, cad l'historique par code Insee et par date en ordre chrono inverse

  Non conformités:
    - le seul type 70
    - 38 chgts de nom

  La mise à jour du 13/5/2020 rend le fichier invalide. Je ne l'utilise donc pas.

journal: |
  29/10/2020:
    - rédaction des spécifications du fichier des mouvements sur les communes
  27/10/2020:
    - 1ère version un peu complète à améliorer encore
  23/10/2020:
    - début de construction Rpicom
  20/10/2020:
    - création
includes:
screens:
classes:
functions:
*/
ini_set('memory_limit', '2G');

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/html.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (php_sapi_name() <> 'cli') {
  if (!isset($_GET['action'])) {
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>mvts</title></head><body>\n";
    echo "<a href='?action=specs'>Affichage des specs</a><br>\n";
    echo "<a href='?action=showPlainEvts'>Affichage des evts Insee simplement</a><br>\n";
    echo "<a href='?action=doublons'>Affichage des evts Insee en doublon</a><br>\n";
    echo "<a href='?action=showEvts'>Affichage des evts Insee</a><br>\n";
    echo "<a href='?action=mvts'>Affichage des mvts</a><br>\n";
    echo "<a href='?action=mvtserreurs'>Affichage des mvts non conformes aux specs</a><br>\n";
    echo "<a href='?action=rpicom'>Génération du Rpicom</a><br>\n";
    die();
  }
  else {
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>graph $_GET[action]</title></head><body><pre>\n";
  }
}
else {
  $_GET['action'] = 'cli';
}

{ // Les types d'évènements et leur libellé Insee
define('TYPESDEVENEMENT', [
  10 => "Changement de nom",
  20 => "Création",
  21 => "Rétablissement",
  30 => "Suppression",
  31 => "Fusion simple",
  32 => "Création de commune nouvelle",
  33 => "Fusion association",
  34 => "Transformation de fusion association en fusion simple",
  41 => "Changement de code dû à un changement de département",
  50 => "Changement de code dû à un transfert de chef-lieu",
  70 => "Transformation de commune associé en commune déléguée",
]);
}


// Classe abstraite parente des sous-classes par type de mouvement
abstract class Mvt {
  static $mvtsErreurs=[]; // liste des evts non conformes aux specs
  const SOUSCLASSES = [
    10 => 'ChgtDeNom',
    20 => 'Creation',
    21 => 'Retablissement',
    30 => 'Suppression',
    31 => 'Fusion',
    32 => 'CreaCNouv',
    33 => 'Association',
    34 => 'Integration',
    41 => 'ChgtCodeDuAChgtDept',
    50 => 'ChgtCodeDuATransfChefLieu',
    70 => 'TransfoComAComD',
  ];
  protected $date_eff;
  protected $mod;
  
  // génère un ensemble de mvts ([Mvt])
  static function createMvts(string $date_eff, string $mod, array $evts): array {
    $sousclasse = self::SOUSCLASSES[$mod] ?? 'AutreMvt';
    return $sousclasse::create($date_eff, $mod, $evts);
  }
  
  abstract static function create(string $date_eff, string $mod, array $evts): array;

  abstract function asArray(): array;
  
  abstract function buildRpicom(string $date_eff, array &$rpicoms): void;
};

class AutreMvt extends Mvt { // Mvt par défaut utilisé pour le développement 
  protected $evts; // liste des evts correspondants
  
  static function create(string $date_eff, string $mod, array $evts): array {
    return [ new self($date_eff, $mod, $evts) ];
  }

  function __construct(string $date_eff, string $mod, array $evts) {
    $this->date_eff = $date_eff;
    $this->mod = $mod;
    $this->evts = $evts;
  }

  function asArray(): array {
    //return [];
    return [
      $this->mod => [
        'date_eff'=> $this->date_eff,
        'evts'=> $this->evts,
      ],
    ];
  }
  
  function buildRpicom(string $date_eff, array &$rpicoms): void {}
};

function setMerge(&$dest, $value): void {// affecte $value à $dest s'il n'est pas déjà défini, sinon lui affecte le merge des 2 valeurs
  if (!isset($dest))
    $dest = $value;
  else
    $dest = mergeRpicom($dest, $value);
}

if (0) { // Test setMerge
  setMerge($dest['x'], [4,5,6]);
  print_r($dest);
  setMerge($dest['x'], [7]);
  print_r($dest);
  die();
}

function mergeRpicom(array $a, array $b): array { // fusionne 2 enregistrements Rpicom ayant même code et à la même date
  // Permet surtout de vérifier qu'il n'y a pas de collision entre deux versions
  //return [$a, $b];
  if (!isset($a['état'])) { // $a est en premier et $b en second
    return (isset($b['après']) ? ['après'=> $b['après']] : []) + ['évts'=> $a['évts'] + $b['évts']];
  }
  if (!isset($b['état'])) { // $b est en premier et $a en second
    return (isset($a['après']) ? ['après'=> $a['après']] : []) + ['évts'=> $b['évts'] + $a['évts']];
  }
  if ($a['état'] == $b['après']) { // $b est en premier et $a en second
    return (isset($a['après']) ? ['après'=> $a['après']] : []) + ['évts'=> $b['évts'] + $a['évts']];
  }
  if ($b['état'] == $a['après']) { // $a est en premier et $b en second
    return (isset($b['après']) ? ['après'=> $b['après']] : []) + ['évts'=> $a['évts'] + $b['évts']];
  }
  echo '$a='; print_r($a);
  echo '$b='; print_r($b);
  die("Erreur de collision dans mergeRpicom\n");
}

class ChgtDeNom extends Mvt { // 10 - Changement de nom 
  const TITLE = "10 - Changement de nom ";
  const SPECS = "Chaque arc correspond à un Changement de nom d'une entité. Le type et le code doivent être identiques avant et après
    cad:  
      <pre>(typecom_ap==typecom_av) && (com_av==com_ap)</pre>
    <i>Note:</i> Il existe de nombreuses non-conformités ; le second exemple ci-dessous en fournit un exemple.";
  const EXAMPLES = [
    '2018-11-08' => "Chaque ligne du tableau correspond à un changement de nom",
    '2007-04-01' => "Ces lignes sont considérées comme des non-conformités.",
  ];
  protected $c; // ['av'=>['typecom','com','libelle'], 'ap'=>[...], 'nolcsv'=> nolcsv]

  static function create(string $date_eff, string $mod, array $evts): array {
    $result = [];
    foreach ($evts as $evt) {
      if (($evt['ap']['com']<>$evt['av']['com']) || ($evt['ap']['typecom']<>$evt['av']['typecom'])) {
        Mvt::$mvtsErreurs[] = ['date_eff'=> $date_eff, 'mod'=> $mod, 'evts'=> [$evt]];
      }
      else
        $result[] = new self($date_eff, $mod, $evt);
    }
    return $result;
  }

  function __construct(string $date_eff, string $mod, array $evt) {
    $this->date_eff = $date_eff;
    $this->mod = $mod;
    $this->c = $evt;
  }
  
  function asArray(): array {
    return [$this->c['ap']['com'] => ['chgtDeNom'=> $this->c]];
  }
  
  function buildRpicom(string $date_eff, array &$rpicoms): void {
    setMerge($rpicoms[$this->c['ap']['com']][$date_eff], [
      'après'=> [
        'statut'=> $this->c['ap']['typecom'],
        'name'=> $this->c['ap']['libelle'],
      ],
      'évts'=> ['changeDeNomPour'=> $this->c['ap']['libelle']],
      'état'=> [
        'statut'=> $this->c['av']['typecom'],
        'name'=> $this->c['av']['libelle'],
      ],
    ]);
  }
};

class Creation extends Mvt { // 20 - Création 
  const TITLE = "20 - Création";
  const SPECS = "Chaque mouvement est défini autour de la commune créée définie par un noeud d'arrivée
    ayant plus d'un arc entrant (1). 
    Chaque noeud de départ de ces arcs (2) définit une commune contributrice avant le mouvement.
    En dehors de ces arcs, chaque commune contributrice correspond à un arc avant/après (3).  
    <i>Note:</i> il n'existe que 6 créations qui interviennent à des dates différentes.
    ";
  const EXAMPLES = ['1989-02-15'=> "Création de Chamrousse à partir des communes contributrices de
    Saint-Martin-d'Uriage, Séchilienne et Vaulnaveys-le-Haut."];
  protected $creee; // commune créée ['typecom','com','libelle'] (ap)
  protected $contribs; // communes contributrices [['av'=>[], 'ap'=>[], 'nolcsv'=>nolcsv]]
  
  static function create(string $date_eff, string $mod, array $evts): array {
    $result = []; // [Mvt]
    $graphAv=[]; // [avNode => [apNode => evt]] / node est l'encodage JSON de av ou ap
    $graphAp=[]; // [apNode => [avNode => evt]] / node est l'encodage JSON de av ou ap
    
    foreach ($evts as $evt) { // construction de $graphAv et $graphAp
      $ap = json_encode($evt['ap']);
      $av = json_encode($evt['av']);
      $graphAv[$av][$ap] = $evt;
      $graphAp[$ap][$av] = $evt;
    }
    
    // la commune créée est le noeud d'arrivée ayant plus d'un arc entrant
    foreach ($graphAp as $creee => $avNodes) {
      $contribs = [];
      if (count($avNodes) > 1) {
        foreach ($avNodes as $contrib => $evt) {
          unset($graphAp[$contrib][$creee]);
          $contribs[] = array_values($graphAp[$contrib])[0];
        }
        return [new self($date_eff, $mod, json_decode($creee, true), $contribs)];
      }
    }
  }

  function __construct(string $date_eff, string $mod, array $creee, array $contribs) {
    $this->date_eff = $date_eff;
    $this->mod = $mod;
    $this->creee = $creee;
    $this->contribs = $contribs;
  }
  
  function asArray(): array {
    return [$this->creee['com'] => [
      'creation'=> [
        'créée'=> $this->creee,
        'contribs'=> $this->contribs,
      ]
    ]];
  }
  
  function buildRpicom(string $date_eff, array &$rpicoms): void {
    $contribIds = [];
    foreach ($this->contribs as $contrib)
      $contribIds[] = $contrib['av']['com'];
    setMerge($rpicoms[$this->creee['com']][$date_eff], [
      'après'=> [
        'statut'=> $this->creee['typecom'],
        'name'=> $this->creee['libelle'],
      ],
      'évts'=> ['crééeAPartirDe'=> $contribIds],
    ]);
    foreach ($this->contribs as $contrib) {
      setMerge($rpicoms[$contrib['ap']['com']][$date_eff], [
        'après'=> [
          'statut'=> $contrib['ap']['typecom'],
          'name'=> $contrib['ap']['libelle'],
        ],
        'évts'=> ['contribueA'=> $this->creee['com']],
        'état'=> [
          'statut'=> $contrib['av']['typecom'],
          'name'=> $contrib['av']['libelle'],
        ],
      ]);
    }
  }
};

class Retablissement extends Mvt { // 21 - Rétablissement - On devrait plutot parler de scission 
  const TITLE = "21 - Rétablissement - Je préfère plutôt parler de scission";
  const SPECS = "La commune/ARM se scindant (appelée source) (1) est identifiée par l'arc satisfaisant le critère  
      <pre>(typecom_av in {'COM','ARM'}) && (typecom_ap==typecom_av) && (com_av==com_ap)</pre>
    Les autres arcs partant de l'état avant de la source identifient les entités concernées (2 et 2').
    Soit l'entité est créée ce qui est défini par l'absence d'autre arc y arrivant (2),
    soit elle préexiste et est modifiée (2') et cet arc (3) définit ses états avant/après.  
    En outre, un arc peut partir d'un autre noeud et arriver sur l'état après de la source (4).
    <i>Cas particulier:</i>  
      &nbsp;- scission le 12/8/1964 du 5ème Ardt de Lyon pour créer le 9ème
    ";
  const EXAMPLES = [
    '1949-12-14'=> "Un exemple simple: Saint-Trojan-les-Bains (17411) se scinde en 2 pour créer Le Grand-Village-Plage (17485).",
    '2019-12-31'=> "Un autre plus complexe: Saline (14712) se scinde en se renommant Troarn et en créant
      la commune associée de Bures-sur-Dives (14114) ;
      cette scission entraîne la transformation de la commune déléguée de Sannerville (14666) en commune simple
      et la disparition de la commune déléguée de Troarn.",
    '1964-08-12'=> "Dernier exemple de la scission du 5ème Ardt de Lyon pour créer le 9ème."
  ];
  protected $source; // commune se scindant ['av'=>['typecom','com','libelle'], 'ap'=>[...], 'nolcsv'=> nolcsv]
  protected $entites; // liste des entités créées ou dont le statut est modifié [['av'=>[...]?, 'ap'=>[...], 'nolcsv'=> nolcsv]]

  static function create(string $date_eff, string $mod, array $evts): array {
    $result = []; // [Mvt]
    $graphAv=[]; // [avNode => [apNode => evt]] / node est l'encodage JSON de av ou ap
    $graphAp=[]; // [apNode => [avNode => evt]] / node est l'encodage JSON de av ou ap
    
    foreach ($evts as $evt) { // construction de $graphAv et $graphAp
      $ap = json_encode($evt['ap']);
      $av = json_encode($evt['av']);
      $graphAv[$av][$ap] = $evt;
      $graphAp[$ap][$av] = $evt;
    }
    while ($graphAv) {
      // la commune/ARM source / (typecom_av in {'COM','ARM'}) && (typecom_ap==typecom_av) && (com_av==com_ap)
      $source = [];
      foreach ($graphAv as $av => $graphAvAv) {
        foreach ($graphAvAv as $ap => $evt) {
          if (in_array($evt['av']['typecom'], ['COM','ARM'])
              && ($evt['ap']['typecom']==$evt['av']['typecom']) && ($evt['ap']['com']==$evt['av']['com'])) {
            $source = $evt;
            $sourceAv = $av;
            $sourceAp = $ap;
            unset($graphAv[$av][$ap]);
            unset($graphAp[$ap][$av]);
            break 2;
          }
        }
      }
      if (!$source) {
        echo '$grapAv='; print_r($graphAv);
        echo '$result='; print_r($result);
        throw new Exception("source non trouvée");
      }
      //echo "source=",json_encode($source),"\n";
      // les entités concernées sont les arrivées des arcs partant de source
      $entites = [];
      foreach ($graphAv[$sourceAv] as $entiteAp => $entiteEvt) {
        unset($graphAv[$sourceAv][$entiteAp]);
        if (!$graphAv[$sourceAv])
          unset($graphAv[$sourceAv]);
        unset($graphAp[$entiteAp][$sourceAv]);
        // S'il existe un autre arc arrivant à $entiteAp alors son départ est l'entité avant
        //print_r($entiteAp);
        //print_r($entiteEvt);
        if (!$graphAp[$entiteAp]) {
          $entite = ['ap'=> $entiteEvt['ap'], 'nolcsv'=> $entiteEvt['nolcsv']];
          $entites[] = $entite;
          //echo "entite=",json_encode($entite),"\n";
        }
        else {
          //print_r($graphAp[$entiteAp]);
          $entite = array_values($graphAp[$entiteAp])[0];
          $entites[] = $entite;
          $entiteAv = array_keys($graphAp[$entiteAp])[0];
          unset($graphAv[$entiteAv][$entiteAp]);
          if (!$graphAv[$entiteAv])
            unset($graphAv[$entiteAv]);
          unset($graphAp[$entiteAp]);
          //echo "entite=",json_encode($entite),"\n";
        }
      }
      // elt complémentaire
      if (isset($graphAp[$sourceAp]) && $graphAp[$sourceAp]) {
        $entite = array_values($graphAp[$sourceAp])[0];
        $entites[] = $entite;
        $entiteAv = array_keys($graphAp[$sourceAp])[0];
        unset($graphAv[$entiteAv][$sourceAp]);
        if (isset($graphAv[$entiteAv]) && !$graphAv[$entiteAv])
          unset($graphAv[$entiteAv]);
      }
      $result[] = new self($date_eff, $mod, $source, $entites);
    }
    return $result;
  }
  
  function __construct(string $date_eff, string $mod, array $source, array $entites) {
    $this->date_eff = $date_eff;
    $this->mod = $mod;
    $this->source = $source;
    $this->entites = $entites;
  }
  
  function asArray(): array {
    return [
      $this->source['ap']['com'] => [
        'Scission(21)' => [
          'source'=> $this->source,
          'entités'=> $this->entites,
        ]
      ]
    ];
  }
  
  function buildRpicom(string $date_eff, array &$rpicoms): void {
    $creeeIds = [];
    foreach ($this->entites as $entite) {
      if (!isset($entite['av']))
        $creeeIds[] = $entite['ap']['com'];
    }
    setMerge($rpicoms[$this->source['ap']['com']][$date_eff], [
      'après'=> [
        'statut'=> $this->source['ap']['typecom'],
        'name'=> $this->source['ap']['libelle'],
      ],
      'évts'=> ['seScindePourCréer'=> $creeeIds],
      'état'=> [
        'statut'=> $this->source['av']['typecom'],
        'name'=> $this->source['av']['libelle'],
      ],
    ]);
    foreach ($this->entites as $entite) {
      if ($entite['ap']['com'] == $this->source['ap']['com']) { // cas particulier d'intégration de la déléguée propre
        $rpicoms[$this->source['ap']['com']][$date_eff]['état']['commeDéléguée']['name'] = $entite['av']['libelle'];
      }
      elseif (!isset($entite['av'])) { // cas de création d'une nouvelle entité
        setMerge($rpicoms[$entite['ap']['com']][$date_eff], [
          'après'=> [
            'statut'=> $entite['ap']['typecom'],
            'name'=> $entite['ap']['libelle'],
          ],
          'évts'=> ['crééeParScissionDe'=> $this->source['av']['com']],
        ]);
      }
      elseif ($entite['ap'] <> $entite['av']) { // modification
        setMerge($rpicoms[$entite['ap']['com']][$date_eff], [
          'après'=> [
            'statut'=> $entite['ap']['typecom'],
            'name'=> $entite['ap']['libelle'],
          ],
          'évts'=> ['modifiéeLorsDeLaScissionDe'=> $this->source['av']['com']],
          'état'=> [
            'statut'=> $entite['av']['typecom'],
            'name'=> $entite['av']['libelle'],
          ],
        ]);
      }
      else { // conservation
        setMerge($rpicoms[$entite['ap']['com']][$date_eff], [
          'après'=> [
            'statut'=> $entite['ap']['typecom'],
            'name'=> $entite['ap']['libelle'],
          ],
          'évts'=> ['conservéeLorsDeLaScissionDe'=> $this->source['av']['com']],
          'état'=> [
            'statut'=> $entite['av']['typecom'],
            'name'=> $entite['av']['libelle'],
          ],
        ]);
      }
    }
  }
};

class Suppression extends Mvt { // 30 - Suppression
  const TITLE = "30 - Suppression";
  const SPECS = "La commune supprimée est le noeud de départ ayant plus d'un arc sortant (1). 
    Chaque noeud d'arrivée de ces arcs définit une commune réceptrice après le mouvement (2).
    En dehors de ces arcs, chaque commune réceptrice correspond à un arc avant/après (3).  
    <i>Note:</i> il n'existe que 6 suppressions qui interviennent à des dates différentes.
    ";
  const EXAMPLES = ['1968-03-02'=> "Hocmont (08227) est supprimée et son territoire est réparti
    dans Guignicourt-sur-Vence (08203) et Touligny (08454)."];
  protected $suppr; // commune supprimée ['av'=>['typecom','com','libelle'], 'nolcsv'=>nolcsv]
  protected $receps; // communes réceptrices [['av'=>[...], 'ap'=>[...], 'nolcsv'=>nolcsv]]
  
  static function create(string $date_eff, string $mod, array $evts): array {
    $result = []; // [Mvt]
    $graphAv=[]; // [avNode => [apNode => evt]] / node est l'encodage JSON de av ou ap
    $graphAp=[]; // [apNode => [avNode => evt]] / node est l'encodage JSON de av ou ap
    
    foreach ($evts as $evt) { // construction de $graphAv et $graphAp
      $ap = json_encode($evt['ap']);
      $av = json_encode($evt['av']);
      $graphAv[$av][$ap] = $evt;
      $graphAp[$ap][$av] = $evt;
    }
    
    // la commune supprimée est le noeud de départ ayant plus d'un arc sortant
    $receps = [];
    foreach ($graphAv as $supprAv => $graphAvAv) {
      if (count($graphAvAv) > 1) {
        foreach ($graphAvAv as $recepAp => $evt) {
          $suppr = ['av'=> $evt['av'], 'nolcsv'=>$evt['nolcsv']];
          unset($graphAp[$recepAp][$supprAv]);
          $receps[] = array_values($graphAp[$recepAp])[0];
        }
      }
    }
    return [new self($date_eff, $mod, $suppr, $receps)];
  }
  
  function __construct(string $date_eff, string $mod, array $suppr, array $receps) {
    $this->date_eff = $date_eff;
    $this->mod = $mod;
    $this->suppr = $suppr;
    $this->receps = $receps;
  }
  
  function asArray(): array {
    return [$this->suppr['av']['com'] => [
      'suppression'=> [
        'suppr'=> $this->suppr,
        'receps'=> $this->receps,
      ]
    ]];
  }

  function buildRpicom(string $date_eff, array &$rpicoms): void {
    $recepIds = [];
    foreach ($this->receps as $recep)
      $recepIds[] = $recep['av']['com'];
    setMerge($rpicoms[$this->suppr['av']['com']][$date_eff], [
      'après'=> [],
      'évts'=> ['seDissoutDans'=> $recepIds],
      'état'=> [
        'statut'=> $this->suppr['av']['typecom'],
        'name'=> $this->suppr['av']['libelle'],
      ],
    ]);
    foreach ($this->receps as $recep) {
      setMerge($rpicoms[$recep['ap']['com']][$date_eff], [
        'après'=> [
          'statut'=> $recep['ap']['typecom'],
          'name'=> $recep['ap']['libelle'],
        ],
        'évts'=> ['reçoitUnePartieDe'=> $this->suppr['av']['com']],
        'état'=> [
          'statut'=> $recep['av']['typecom'],
          'name'=> $recep['av']['libelle'],
        ],
      ]);
    }
  }
};

class FusionRattachement extends Mvt { // 31 (Fusion simple) || 32 (Création de commune nouvelle) || 33 (Fusion association)
  protected $cheflieu; // code du chef-lieu
  protected $libelle_ap; // libellé après du chef-lieu
  protected $fusionnees; // [{comi} => [typecom_av: {typecom_av}, libelle_av: {libelle_av}]] / y.c. ex cheflieu
  protected $rattachees; // [{comi} => [typecom_av: {typecom_av}, libelle_av: {libelle_av}, libelle_ap: {libelle_ap}]]
  
  static function create(string $date_eff, string $mod, array $evts): array {
    $result = []; // [Mvt]
    $graphAv=[]; // [avNode => [apNode => nolcsv]] / node est l'encodage JSON de av ou ap
    $graphAp=[]; // [apNode => [avNode => nolcsv]] / node est l'encodage JSON de av ou ap
    $frat = []; // [cheflieuAp => [fusionneeAv => ['rJson'=> rattacheeAp?, 'no'=>nolcsv]]]
    
    foreach ($evts as $evt) { // construction de $graphAv et $graphAp
      $ap = json_encode($evt['ap']);
      $av = json_encode($evt['av']);
      $graphAv[$av][$ap] = $evt;
      $graphAp[$ap][$av] = $evt;
    }
    foreach ($graphAp as $cheflieuJson => $avNodes) {
      // un chef-lieu est un noeud d'arrivée de type COM et ayant plus d'un arc entrant
      $cheflieu = json_decode($cheflieuJson, true);
      if (($cheflieu['typecom'] == 'COM') && (count($avNodes) > 1)) {
        //echo "chef-lieu: $cheflieu\n";
        // les fusionnees/rattachees sont les noeuds de départ ayant un arc vers le chef-lieu
        foreach ($avNodes as $fJson => $evt) {
          // si une fusionnee a un autre arc que celui vers le chef-lieu alors elle est rattachéee
          if (count($graphAv[$fJson]) >= 2) {
            foreach (array_keys($graphAv[$fJson]) as $rJson) {
              if ($rJson <> $cheflieuJson) {
                //echo "    rattachee: $rattachee\n";
                $frat[$cheflieuJson][$fJson] = ['rJson'=> $rJson, 'no'=> $evt['nolcsv']];
              }
            }
          }
          else {
            //echo "    fusionnee: $fusionnee\n";
            $frat[$cheflieuJson][$fJson] = ['no'=> $evt['nolcsv']];
          }
        }
        $mvt = new self($date_eff, $mod, $cheflieu, $frat[$cheflieuJson]);
        //echo '$mvt='; print_r($mvt);
        $result[] = $mvt;
      }
    }
    //echo Yaml::dump(['frat'=> [$date_eff=> [$mod=> $frat]]], 6, 2);
    foreach ($frat as $cheflieuJson => $fJsons) {
      foreach ($fJsons as $fJson => $rno) {
        unset($graphAv[$fJson][$cheflieuJson]);
        if (isset($rno['rJson']))
          unset($graphAv[$fJson][$rno['rJson']]);
        //echo "graphAv="; print_r($graphAv);
        if (!$graphAv[$fJson])
          unset($graphAv[$fJson]);
      }
    }
    if ($graphAv) {
      echo "graphAv="; print_r($graphAv); die();
    }
    return $result;
  }
  
  function __construct(string $date_eff, string $mod, array $cheflieu, array $frat) {
    $this->date_eff = $date_eff;
    $this->mod = $mod;
    $this->cheflieu = $cheflieu['com'];
    $this->libelle_ap = $cheflieu['libelle'];
    $this->fusionnees = [];
    $this->rattachees = [];
    foreach ($frat as $fJson => $rno) {
      if (!isset($rno['rJson'])) {
        $f = json_decode($fJson, true);
        $this->fusionnees[$f['com']] = ['typecom_av'=> $f['typecom'], 'libelle_av'=> $f['libelle'], 'nolcsv'=> $rno['no']];
      }
      else {
        $f = json_decode($fJson, true);
        $r = json_decode($rno['rJson'], true);
        $this->rattachees[$f['com']] = [
          'typecom_av'=> $f['typecom'], 'libelle_av'=> $f['libelle'], 'libelle_ap'=> $r['libelle'], 'nolcsv'=> $rno['no']
        ];
      }
    }
  }
  
  function asArray(): array {
    //return [];
    $labels = [
      31 => 'fusion(31)',
      32 => 'communeNouvelle(32)',
      33 => 'association(33)',
    ];
    return [
      $this->cheflieu => [
        $labels[$this->mod] => 
          ['libelle_ap'=> $this->libelle_ap]
          + ($this->fusionnees ? ['fusionnées'=> $this->fusionnees] : [])
          + ($this->rattachees ? ['rattachées'=> $this->rattachees] : [])
      ],
    ];
  }
  
  function buildRpicom(string $date_eff, array &$rpicoms): void {
    $fusionnees = $this->fusionnees;
    if (isset($fusionnees[$this->cheflieu])) {
      $rpicoms[$this->cheflieu][$date_eff]['évts']['absorbe'] = [];
      $rpicoms[$this->cheflieu][$date_eff]['état'] = [
        'statut'=> $fusionnees[$this->cheflieu]['typecom_av'],
        'name'=> $fusionnees[$this->cheflieu]['libelle_av']
      ];
      unset($fusionnees[$this->cheflieu]);
    }
    $rattachees = $this->rattachees;
    if (isset($rattachees[$this->cheflieu])) {
      $rpicoms[$this->cheflieu][$date_eff]['évts']['rattache'] = [$this->cheflieu];
      $rpicoms[$this->cheflieu][$date_eff]['état'] = [
        'statut'=> $rattachees[$this->cheflieu]['typecom_av'],
        'name'=> $rattachees[$this->cheflieu]['libelle_av']
      ];
      unset($rattachees[$this->cheflieu]);
    }
    if ($fusionnees) {
      foreach ($fusionnees as $fcom => $fusionnee) {
        $rpicoms[$fcom][$date_eff]['évts'] = ['fusionneDans'=> $this->cheflieu];
        $rpicoms[$fcom][$date_eff]['état'] = [
          'statut'=> $fusionnee['typecom_av'],
          'name'=> $fusionnee['libelle_av']
        ];
        $rpicoms[$this->cheflieu][$date_eff]['évts']['absorbe'][] = $fcom;
      }
    }
    if ($rattachees) {
      foreach ($rattachees as $rcom => $rattachee) {
        $rpicoms[$rcom][$date_eff]['évts'] = ['seRattacheA'=> $this->cheflieu];
        $rpicoms[$rcom][$date_eff]['état'] = [
          'statut'=> $rattachee['typecom_av'],
          'name'=> $rattachee['libelle_av']
        ];
        $rpicoms[$this->cheflieu][$date_eff]['évts']['rattache'][] = $rcom;
      }
    }
  }
};

class Fusion extends FusionRattachement { // 31 - Fusion simple
  const TITLE = "31 - Fusion simple";
  const SPECS = "Chaque mouvement de fusion simple s'effectue autour d'un chef-lieu défini comme un noeud d'arrivée de type COM
    ayant plus d'un arc entrant (1).
    Parmi les noeuds de départ de ces arcs, un porte le même code que le noeud d'arrivée, c'est l'état avant du chef-lieu.
    Les autres noeuds de départ correspondent aux entités fusionnées.  
    <i>Cas particulier:</i> 
    ";
  const EXAMPLES = [
    '2006-09-01'=> "",
    '1947-08-27'=> "Cas particulier de création du nouveau code 14764 par cette fusion ;
      il parait impossible de savoir laquelle des 2 est chef-lieu."
  ];
};


class CreaCNouv extends FusionRattachement { // 32 - Création de commune nouvelle
  const TITLE = "32 - Création de commune nouvelle";
  const SPECS = "Chaque mouvement de création de commune nouvelle s'effectue autour d'un chef-lieu défini comme un noeud d'arrivée
    de type COM ayant plus d'un arc entrant (1).
    Parmi les noeuds de départ de ces arcs, un porte le même code que le noeud d'arrivée (2), c'est l'état avant du chef-lieu.
    Parmi ces noeuds de départ, certains ont un autre arc sortant (2 et 3') dont le noeud d'arrivée définit une commune déléguée
    (5 et 4) ;
    d'autres n'ont pas d'autre arc sortant (3), ce sont des communes fusionnées.
    ";
  const EXAMPLES = [
    '2019-03-01'=> "Le chef-lieu est La Selle-sur-le-Bied (45307), parmi les 2 communes concernées seule Saint-Loup-de-Gonois
      (45287) donne lieu à la création d'une commune déléguée.",
    '2019-02-28'=> "Création de 2 communes nouvelles, chacune avec 2 communes déléguées.",
  ];
};

class Association extends FusionRattachement { // 33 - Fusion association
  const TITLE = "33 - Fusion association";
  const SPECS = "Le motif de Fusion-association est similaire à celui de la Création de commune nouvelle avec 2 différences
    (a) que les noeuds d'arrivée (4) sont des communes associées et non des communes déléguées et
    (b) que le chef-lieu ne peut donner lieu à une commune associée (absence du 5).
    ";
  const EXAMPLES = ['2010-12-09'=> ""];
};

class Integration extends Mvt { // 34 - Transformation de fusion association en fusion simple 
  const TITLE = "34 - Transformation de fusion association en fusion simple ";
  const SPECS = "Chaque mouvement de Transformation de fusion association en fusion simple s'effectue autour d'un chef-lieu défini
    par un arc ayant un noeud de départ (2) et un noeud d'arrivée (1) tous les 2 de type COM.
    Les autres noeuds de départ des arcs arrivant au noeud d'arrivée (1) du chef-lieu correspondent aux entités fusionnées (3).  
    <i>Note:</i> Le mouvement peut s'appliquer aussi à une commune nouvelle et pas uniquement à une fusion association.
    ";
  const EXAMPLES = [
    '2020-01-01'=> "",
    '2008-01-01'=> "",
    '2018-01-01'=> "Cas particulier de changement de département de Pont-Farcy après l'intégration de Pleines-Œuvres.",
  ];
  
  
  // Un mvt correspond à un chef-lieu et toutes ses intégrées/rattachées
  protected $cheflieu; // ['av'=>['typecom','com','libelle'], 'ap'=>[...], 'nolcsv'=> nolcsv]
  protected $integrees; // [['av'=>['typecom','com','libelle'], 'nolcsv'=> nolcsv]]  // liste des entités intégrées
  protected $resteRats; // [['av'=>['typecom','com','libelle'], 'ap'=>[...], 'nolcsv'=> nolcsv]] // liste des entités restant rattachées
  
  static function create(string $date_eff, string $mod, array $evts): array {
    $result = []; // [Mvt]
    $graphAv=[]; // [avNode => [apNode => evt]] / node est l'encodage JSON de av ou ap
    $graphAp=[]; // [apNode => [avNode => evt]] / node est l'encodage JSON de av ou ap
    
    // construction de $graphAv et $graphAp
    foreach ($evts as $evt) {
      $ap = json_encode($evt['ap']);
      $av = json_encode($evt['av']);
      $graphAv[$av][$ap] = $evt;
      $graphAp[$ap][$av] = $evt;
    }
    while ($graphAv) {
      // je commence par extraire une rattachante / correspond à un evt COM -> COM
      $cheflieu = [];
      foreach ($graphAv as $av => $graphAvAv) {
        foreach ($graphAvAv as $ap => $evt) {
          if (($evt['av']['typecom']=='COM') && ($evt['ap']['typecom']=='COM')) {
            $cheflieu = $evt;
            unset($graphAv[$av][$ap]);
            unset($graphAp[$ap][$av]);
            break 2;
          }
        }
      }
      //echo '$graphAv='; print_r($graphAv);
      if (!$cheflieu) {
        echo '$graphAv='; print_r($graphAv);
        throw new Exception("cheflieu non trouvé");
      }
      //echo "cheflieu=",json_encode($cheflieu),"\n";
      $integrees = [];
      $resteRats = [];
      // je cherche ensuite les rattachees à ce chef-lieu / cRattachée -> cRattachante
      foreach ($graphAp[json_encode($cheflieu['ap'])] as $rattachee => $evt) {
        //echo "rattachee=$rattachee\n";
        unset($graphAv[$rattachee][json_encode($cheflieu['ap'])]);
        if (!$graphAv[$rattachee]) {
          $integrees[] = ['av'=> $evt['av'], 'nolcsv'=> $evt['nolcsv']];
          unset($graphAv[$rattachee]);
        }
        // je cherche ensuite si cette rattachee est intégrée ou si elle reste rattachée
        // ((cResteRattachée -> cRattachante) + (cResteRattachée -> cResteRattachée) + (cRattachante -> cResteRattachée))*
        elseif (count($graphAv[$rattachee]) <> 1)
          throw new Exception("Erreur de détection de cResteRattachée -> cResteRattachée");
        else {
          $resteRatJson = array_keys($graphAv[$rattachee])[0]; // key -> string
          $resteRats[] = array_values($graphAv[$rattachee])[0]; // value -> array
          unset($graphAv[$rattachee]);
          unset($graphAv[json_encode($cheflieu['av'])][$resteRatJson]);
        }
        if (isset($graphAv[json_encode($cheflieu['av'])]) && !$graphAv[json_encode($cheflieu['av'])])
          unset($graphAv[json_encode($cheflieu['av'])]);
      }
      $result[] = new self($cheflieu, $integrees, $resteRats);
    }
    return $result;
  }
  
  function __construct(array $cheflieu, array $integrees, array $resteRats) {
    $this->cheflieu = $cheflieu;
    $this->integrees = $integrees;
    $this->resteRats = $resteRats;
  }
  
  function asArray(): array {
    return [
      $this->cheflieu['ap']['com']=> [
        'Intégration(34)'=> [
          'cheflieu' => $this->cheflieu,
          'integrees' => $this->integrees,
          'resteRats' => $this->resteRats,
        ]
      ]
    ];
  }

  function buildRpicom(string $date_eff, array &$rpicoms): void {
    //return;
    if ($this->cheflieu['av']['com'] == $this->cheflieu['ap']['com']) {
      setMerge($rpicoms[$this->cheflieu['ap']['com']][$date_eff], [
        'après'=> [
          'statut'=> $this->cheflieu['ap']['typecom'],
          'name'=> $this->cheflieu['ap']['libelle'],
        ],
        'évts'=> ['absorbe'=> []],
        'état'=> [
          'statut'=> $this->cheflieu['av']['typecom'],
          'name'=> $this->cheflieu['av']['libelle'],
        ],
      ]);
    }
    else {
      setMerge($rpicoms[$this->cheflieu['ap']['com']][$date_eff], [
        'après'=> [
          'statut'=> $this->cheflieu['ap']['typecom'],
          'name'=> $this->cheflieu['ap']['libelle'],
        ],
        'évts'=> ['avaitPourCode'=> $this->cheflieu['av']['com']],
      ]);
      setMerge($rpicoms[$this->cheflieu['av']['com']][$date_eff], [
        'après'=> [],
        'évts'=> ['absorbe'=> [], 'changeDeCodePour'=> $this->cheflieu['ap']['com']],
        'état'=> [
          'statut'=> $this->cheflieu['av']['typecom'],
          'name'=> $this->cheflieu['av']['libelle'],
        ],
      ]);
    }
    foreach ($this->integrees as $integree) {
      if ($integree['av']['com']==$this->cheflieu['av']['com']) { // cas d'intégration de la commune déléguée propre
        $rpicoms[$this->cheflieu['ap']['com']][$date_eff]['état']['commeDéléguée']['name'] = $integree['av']['libelle'];
      }
      else { // cas d'intégration d'une rattachée autre que la commune délégue propre
        setMerge($rpicoms[$integree['av']['com']][$date_eff], [
          'après'=> [],
          'évts'=> ['fusionneDans'=> $this->cheflieu['av']['com']],
          'état'=> [
            'statut'=> $integree['av']['typecom'],
            'name'=> $integree['av']['libelle'],
          ],
        ]);
      }
      $rpicoms[$this->cheflieu['ap']['com']][$date_eff]['évts']['absorbe'][] = $integree['av']['com'];
    }
    foreach ($this->resteRats as $resteRat) {
      setMerge($rpicoms[$resteRat['av']['com']][$date_eff], [
        'après'=> [
          'statut'=> $resteRat['ap']['typecom'],
          'name'=> $resteRat['ap']['libelle'],
        ],
        'évts'=> ['resteRattachée'=> $this->cheflieu['av']['com']],
        'état'=> [
          'statut'=> $resteRat['av']['typecom'],
          'name'=> $resteRat['av']['libelle'],
        ],
      ]);
    }
  }
};

class ChgtCodeDuAChgtDept extends Mvt { // 41 - Changement de code dû à un changement de département 
  const TITLE = "41 - Changement de code dû à un changement de département";
  const SPECS = "Chaque arc correspond à un Changement de nom de code.";
  const EXAMPLES = ['2018-01-01'=> '', '2016-12-31'=> ''];
  protected $c; // ['av'=>['typecom','com','libelle'], 'ap'=>[...], 'nolcsv'=> nolcsv]

  static function create(string $date_eff, string $mod, array $evts): array {
    $result = [];
    foreach ($evts as $evt)
      $result[] = new self($date_eff, $mod, $evt);
    return $result;
  }
  
  function __construct(string $date_eff, string $mod, array $evt) {
    $this->date_eff = $date_eff;
    $this->mod = $mod;
    $this->c = $evt;
  }
  
  function asArray(): array {
    return [$this->c['ap']['com'] => ['chgtCodeDuAChgtDept'=> $this->c]];
  }
  
  function buildRpicom(string $date_eff, array &$rpicoms): void {
    setMerge($rpicoms[$this->c['ap']['com']][$date_eff], [
      'après'=> [
        'statut'=> $this->c['ap']['typecom'],
        'name'=> $this->c['ap']['libelle'],
      ],
      'évts'=> ['avaitPourCode'=> $this->c['av']['com']],
    ]);
    setMerge($rpicoms[$this->c['av']['com']][$date_eff], [
      'après'=> [],
      'évts'=> ['changeDeCodePour'=> $this->c['ap']['com']],
      'état'=> [
        'statut'=> $this->c['av']['typecom'],
        'name'=> $this->c['av']['libelle'],
      ],
    ]);
  }
};

class ChgtCodeDuATransfChefLieu extends Mvt { // 50 - Changement de code dû à un transfert de chef-lieu 
  const TITLE = "50 - Changement de code dû à un transfert de chef-lieu ";
  const SPECS = "L'ancien chef-lieu peut être identifié au moyen du critère de sélection suivant:
      <pre>(typecom_av=='COM') && (typecom_ap=='COMA') && (com_av==com_ap)</pre>
    le nouveau chef-lieu peut être identifié au moyen du critère de sélection suivant:
      <pre>(typecom_av=='COMA') && (typecom_ap=='COM') && (com_av==com_ap)</pre>
    les communes associées restant associées peuvent être identifiées au moyen du critère de sélection suivant:
      <pre>(typecom_av=='COMA') && (typecom_ap=='COMA') && (com_av==com_ap)</pre>
    <i>Note:</i> Il n'existe que 2 mvts de ce type.";
  const EXAMPLES = ['2014-01-07'=> '', '1990-02-01'=> ''];
  
  protected $cheflieu_av; // ['av'=>[...], 'ap'=>[...], 'nolcsv'=>nolcsv] // av contient le cheflieu avant, ap les infos comme rattachée
  protected $cheflieu_ap; // ['av'=>[...], 'ap'=>[...], 'nolcsv'=>nolcsv] // ap contient le cheflieu après, av les infos comme rattachée
  protected $rattachees; // [['av'=>[...], 'ap'=>[...], 'nolcsv'=>nolcsv]] // les entités restant rattachées
  
  static function create(string $date_eff, string $mod, array $evts): array {
    $rattachees = [];
    foreach ($evts as $i => $evt) {
      if (($evt['av']['typecom']=='COM') && ($evt['ap']['typecom']=='COMA') && ($evt['ap']['com']==$evt['av']['com']))
        $cheflieu_av = $evt;
      elseif (($evt['av']['typecom']=='COMA') && ($evt['ap']['typecom']=='COM') && ($evt['ap']['com']==$evt['av']['com']))
        $cheflieu_ap = $evt;
      elseif (($evt['av']['typecom']=='COMA') && ($evt['ap']['typecom']=='COMA') && ($evt['ap']['com']==$evt['av']['com']))
        $rattachees[] = $evt;
    }
    return [new self($date_eff, $mod, $cheflieu_av, $cheflieu_ap, $rattachees)];
  }
  
  function __construct(string $date_eff, string $mod, array $cheflieu_av, array $cheflieu_ap, array $rattachees) {
    $this->date_eff = $date_eff;
    $this->mod = $mod;
    $this->cheflieu_av = $cheflieu_av;
    $this->cheflieu_ap = $cheflieu_ap;
    $this->rattachees = $rattachees;
  }
  
  function asArray(): array {
    return [ $this->cheflieu_ap['ap']['com'] => [
      'chgtCodeDuATransfChefLieu' => [
        'cheflieu_av'=> $this->cheflieu_av,
        'cheflieu_ap'=> $this->cheflieu_ap,
        'rattachees'=> $this->rattachees,
      ]
    ]];
  }
  
  function buildRpicom(string $date_eff, array &$rpicoms): void {
    setMerge($rpicoms[$this->cheflieu_av['ap']['com']][$date_eff], [
      'après'=> [
        'statut'=> $this->cheflieu_av['ap']['typecom'],
        'name'=> $this->cheflieu_av['ap']['libelle'],
        'crat'=> $this->cheflieu_ap['ap']['com'],
      ],
      'évts'=> ['perdChefLieuAuProfitDe'=> $this->cheflieu_ap['ap']['com']],
      'état'=> [
        'statut'=> $this->cheflieu_av['av']['typecom'],
        'name'=> $this->cheflieu_av['av']['libelle'],
      ],
    ]);
    setMerge($rpicoms[$this->cheflieu_ap['ap']['com']][$date_eff], [
      'après'=> [
        'statut'=> $this->cheflieu_ap['ap']['typecom'],
        'name'=> $this->cheflieu_ap['ap']['libelle'],
      ],
      'évts'=> ['devientChefLieuALaPlaceDe'=> $this->cheflieu_av['av']['com']],
      'état'=> [
        'statut'=> $this->cheflieu_ap['av']['typecom'],
        'name'=> $this->cheflieu_ap['av']['libelle'],
        'crat'=> $this->cheflieu_av['av']['com'],
      ],
    ]);
    
  }
}

class TransfoComAComD extends Mvt { // 70 - Transformation de commune associé en commune déléguée 
  const TITLE = "70 - Transformation de commune associé en commune déléguée";
  const SPECS = "Il n'existe qu'une seule ligne correspondant à ce type que je ne comprend pas.";
  const EXAMPLES = ['2020-01-01'=> ''];
  
  static function create(string $date_eff, string $mod, array $evts): array {
    Mvt::$mvtsErreurs[] = ['date_eff'=> $date_eff, 'mod'=> $mod, 'evts'=> $evts];
    return [];
  }
  
  function asArray(): array {}
  function buildRpicom(string $date_eff, array &$rpicoms): void {}
};


if (!($fevts = fopen(__DIR__.'/../data/mvtcommune2020.csv', 'r')))
  die("Erreur d'ouverture du fichier CSV des mouvements\n");

$evts = []; // [date_eff => [ mod => [[ 'av'=> av, 'ap'=> ap ]]]]
$prevRecord = []; // pour tester les doublons

$nolcsv=0; // num. de ligne dans le fichier CSV, 0 est la ligne des en-têtes
$headers = fgetcsv($fevts, 0, ',');
foreach ($headers as $i => $header)
  $headers[$i] = strtolower($header);
if ($_GET['action']=='showPlainEvts') {
  foreach ([4,5,6,10,11,12] as $i) unset($headers[$i]);
  echo "<table border=1><th>no</th><th>",implode('</th><th>', $headers),"</th>\n";
}
while ($record = fgetcsv($fevts, 0, ',')) {
  $nolcsv++;
  if ($_GET['action']=='showPlainEvts') {
    //if ($record[0]<>10) continue;
    foreach ([4,5,6,10,11,12] as $i) unset($record[$i]);
    echo "<tr><td>$nolcsv</td><td>",implode('</td><td>', $record),"</td></tr>\n";
    continue;
  }
  if ($_GET['action']=='doublons') {
    if ($record == $prevRecord)
      echo "$nolcsv: ",implode('|', $record)," en doublon\n";
    $prevRecord = $record;
    continue;
  }
  if ($record == $prevRecord)
    continue;
  $rec = [];
  foreach ($headers as $i => $header)
    $rec[$header] = $record[$i];
  //if (!in_array($rec['mod'], [31,32,33])) { $prevRecord = $record; continue; }
  //if (!in_array($rec['mod'], [10,20,31,32,33,34,41,50])) { $prevRecord = $record; continue; }
  //if (!in_array($rec['mod'], [30])) { $prevRecord = $record; continue; }
  $evts[$rec['date_eff']][$rec['mod']][] = [
    'av'=> ['typecom'=> $rec['typecom_av'], 'com'=> $rec['com_av'], 'libelle'=> $rec['libelle_av']],
    'ap'=> ['typecom'=> $rec['typecom_ap'], 'com'=> $rec['com_ap'], 'libelle'=> $rec['libelle_ap']],
    'nolcsv'=> $nolcsv,
  ];
  $prevRecord = $record;
}

if (in_array($_GET['action'], ['showPlainEvts','doublons'])) {
  die("</table>\nFin $_GET[action]\n");
}

function showEvts(string $date_eff, string $mod, array $evts): void {
  $rows = [[
    Html::bold('nol'),
    Html::bold('typecom_av'), Html::bold('com_av'), Html::bold('libelle_av'),
    Html::bold('typecom_ap'), Html::bold('com_ap'), Html::bold('libelle_ap')
  ]];
  foreach ($evts as $evt) {
    //echo Yaml::dump($evt);
    $rows[] = [
      $evt['nolcsv'],
      $evt['av']['typecom'], $evt['av']['com'], $evt['av']['libelle'],
      $evt['ap']['typecom'], $evt['ap']['com'], $evt['ap']['libelle'],
    ];
  }
  echo Html::table([], [
    [Html::bold('date_eff'), $date_eff],
    [Html::bold('mod'), $mod.' - '.TYPESDEVENEMENT[$mod]],
    [
      [ 'colspan'=> 2,
        'value'=> Html::table([], $rows),
      ]
    ]
  ]);
  //die();
}

if ($_GET['action']=='specs') { // affichage des specs
  echo "</pre>\n";
  echo <<<EOT
    <h1>Spécifications du fichier des mouvements sur les communes</h1>
    Dans ce document, le fichier des mouvements est considéré comme un graphe des noeuds avant (av) vers les noeuds après (ap).<br>
    Chaque type de mouvement est spécifié par un motif de sous-graphe dans ce graphe.\n
EOT;
  foreach (array_unique(Mvt::SOUSCLASSES) as $mod => $sousclasse) {
    echo '<h2>',$sousclasse::TITLE,'</h2>';
    echo str_replace("  \n", "<br>\n", $sousclasse::SPECS),"<br>\n";
    echo "<img src='figures/$mod.png'>\n";
    echo "<h3>Exemples</h3>\n";
    foreach ($sousclasse::EXAMPLES as $date_eff => $comment) {
      showEvts($date_eff, $mod, $evts[$date_eff][$mod]);
      if ($comment)
        echo "$comment</p>\n";
    }
  }
  die("fin $_GET[action] ok\n");
}

if ($_GET['action'] == 'showEvts') {
  //echo '</pre>';
  foreach ($evts as $date_eff => $evts1) {
    foreach ($evts1 as $mod => $evts2) {
      //if ($mod == 10)
      showEvts($date_eff, $mod, $evts2);
    }
  }
  die("Fin $_GET[action]\n");
}

if ($_GET['action'] == 'rpicom') { // initialisation de $rpicoms
  $coms = Yaml::parseFile('../insee/com20200101.yaml');
  $rpicoms = [];
  foreach ($coms['contents'] as $ccom => $com) {
    $rpicoms[$ccom]['now']['etat'] = $com;
    unset($coms['contents'][$ccom]);
  }
  unset($coms);
  //echo Yaml::dump($rpicoms, 7, 2),"\n";
}

foreach ($evts as $date_eff => $evts1) {
  foreach ($evts1 as $mod => $evts2) {
    try {
      $mvts = Mvt::createMvts($date_eff, $mod, $evts2);
    }
    catch (Exception $e) {
      echo Html::bold($e->getMessage()),"\n\n"; die();
    }
    if ($_GET['action']=='mvts') {
      $mvtsAsArray = [];
      foreach ($mvts as $mvt) {
        if ($array = $mvt->asArray()) {
          //print_r($array);
          $mvtsAsArray += $array;
        }
      }
      if ($mvtsAsArray)
        echo Yaml::dump([$date_eff => $mvtsAsArray], 7, 2),"\n";
    }
    elseif ($_GET['action']=='rpicom') {
      foreach ($mvts as $mvt) {
        $mvt->buildRpicom($date_eff, $rpicoms);
      }
    }
  }
}
if ($_GET['action'] == 'mvtserreurs') {
  //print_r(Mvt::$mvtsErreurs);
  foreach (Mvt::$mvtsErreurs as $mvtsErreur)
    showEvts($mvtsErreur['date_eff'], $mvtsErreur['mod'], $mvtsErreur['evts']);
}
if ($_GET['action'] == 'rpicom') {
  ksort($rpicoms);
  echo Yaml::dump($rpicoms, 3, 2),"\n";
}
die("fin $_GET[action] ok\n");
