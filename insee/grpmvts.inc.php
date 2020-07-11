<?php
/*PhpDoc:
name: grpmvts.inc.php
title: grpmvts.inc.php - définition de la classe GroupMvts
doc: |
  La classe GroupMvts permet de regrouper des mouvements élémentaires correspondant à une évolution sémantique
  et de les exploiter.
journal: |
  27/4/2020:
    - gestion spécifique du cas particulier de l'absorption le 1/1/2018 de 14507 par Pont-Farcy (14513)
      dans lequel Pont-Farcy change de département pour prendre le code 50649.
  24/4/2020:
    - nlle version avec
      - gestion des groupes de mvts concomitants avec MultiGroupMvts
      - réécriture de addToRpicom() dans le cas 32 pour traiter le changement de c. nouvelle de rattachement
      - modification du schéma pour isoler les évènements
  20/4/2020:
    - réécriture de GroupMvts::addToRpicom()
  19/4/2020:
    - première version traitant tous les cas de addToRpicom()
  16/4/2020:
    - amélioration de la doc
    - prise en compte du nv format de factorAvant() dans buildEvol()
      - réécriture des mod 10, 21 et 31
      - utilisation de l'evol par défaut pour les autres, à TESTER
  14/4/2020:
    - suppression de 6 doublons dans la lecture du CSV des mouvements INSEE
    - modification de factorAvant() en introduisant des codes INSEE modifiés pour les communes déléguées
  11/4/2020:
    - extraction de la classe GroupMvts dans grpmvts.inc.php
functions:
classes:
*/

{/* Erreur de doublon sur
["34","2014-04-01","COM","49328","0","SAUMUR","Saumur","Saumur","COM","49328","0","SAUMUR","Saumur","Saumur"]
["34","2014-04-01","COM","49328","0","SAUMUR","Saumur","Saumur","COM","49328","0","SAUMUR","Saumur","Saumur"]
["34","2014-04-01","COM","49328","0","SAUMUR","Saumur","Saumur","COM","49328","0","SAUMUR","Saumur","Saumur"]
["21","1977-01-01","COM","89344","0","SAINT FARGEAU","Saint-Fargeau","Saint-Fargeau","COM","89344","0","SAINT FARGEAU","Saint-Fargeau","Saint-Fargeau"]
["33","1973-01-01","COM","86160","0","MIREBEAU","Mirebeau","Mirebeau","COM","86160","0","MIREBEAU","Mirebeau","Mirebeau"]
["31","1973-01-01","COM","89068","0","CHABLIS","Chablis","Chablis","COM","89068","0","CHABLIS","Chablis","Chablis"]
*/}

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

{/*PhpDoc: functions
name: swapKeysInArray
title: "function swapKeysInArray(string $key1, string $key2, array $array): array - intervertit l'ordre des 2 clés dans array en gardant les valeurs correspondantes"
*/}
function swapKeysInArray(string $key1, string $key2, array $array): array {
  $result = [];
  foreach ($array as $key => $value) {
    if ($key == $key1)
      $result[$key2] = $array[$key2];
    elseif ($key == $key2)
      $result[$key1] = $array[$key1];
    else
      $result[$key] = $value;
  }
  return $result;
}
if ('TestSwapKeysInArray' == ($_GET['action'] ?? null)) { // Test swapKeysInArray
  $array = [
    'avant' => "avant",
    '46251' => "Saint-Céré",
    '46339' => "Saint-Jean-Lagineste",
    'après' => "après",
  ];
  echo "<pre>\n";
  print_r($array);
  print_r(swapKeysInArray('46251', '46339', $array));
  die("Fin test swapKeysInArray");
}

{/*PhpDoc: functions
name: countLeaves
title: "function countLeaves(array $tree): int - compte le nombre de feuilles stockées dans $tree considéré comme un arbre où chaque array est un noeud intermédiaire"
*/}
function countLeaves(array $tree): int {
  $count = 0;
  foreach ($tree as $key => $child) {
    if (is_array($child))
      $count += countLeaves($child);
    else
      $count++;
  }
  return $count;
}
if ('TestCountLeaves' == ($_GET['action'] ?? null)) { // Test countLeaves() 
  class TestForCountLeaves { };
  echo countLeaves(['a'=> 1]),"<br>\n";
  echo countLeaves(['a'=> 1, 'b'=> ['c'=> 2, 'd'=> 3]]),"<br>\n";
  echo countLeaves(['a'=> null]),"<br>\n";
  echo countLeaves(['a'=> new TestForCountLeaves]),"<br>\n";
  die("Fin test countLeaves()");
}

{/*Terminologie:
Création:
  actions:
    - n crééeAPartirDe [c1, c2, c3]
    - ci contribueA n
Suppression:
  actions:
    - sup seDissoutDans [d1, d2, d3]
    - di reçoitUnePartieDe sup
FusionOuAssociation:
  actions:
    - r est commune de rattachement
    - a: {sAssocieA: a}
    - r {prendCommeAssociée: a}
    - f: {fusionneDans: r}
    - r {absorbe: f}
    - r "Prend des c. associées et/ou absorbe des c. fusionnées"
  état:
    - a est associée à r {a: {estAssociéeA: r}}
    - r a pour associée a {r: {aPourAssociées: [a]}}
CommuneNouvelle:
  actions:
    - r est commune de rattachement
    - d: {devientDéléguéeDe: r}
    - r: {délègueA: d}
    - d: {seFondDans: r}
    - r: {absorbe: d} // qd pas de commune déléguée
  état:
    - d: {estDéléguéeDe: r}
    - r: {aPourDéléguées: [d]}
général:
  absorption/absorbe =
    soit fusion simple d'un c. s. dans une autre
    soit disparition d'une commune rattachée au profit de la c. de rattachement
  sa propre déléguée = qd une commune nouvelle a une commune déléguée et qu'elles ont ttes les 2 le même c. INSEE
  commune
    +- simple
      +- commune nouvelle - résultat de la création d'une commune nouvelle
        +- commune déléguante - ayant des déléguées
      +- commune fusionnée - résultat d'une fusion en appli. de cette législation
        +- commune associante - ayant des associées
    +- rattachée
      +- associée
      +- déléguée
*/}

require_once __DIR__.'/../../../../schema/jsonschema.inc.php';

{/*PhpDoc: classes
name: GroupMvts
title: GroupMvts - Groupe de mvts, chacun correspond à une évolution sémantique distincte
doc: |
  Les groupes sont générés par la méthode statique buildGroups() qui en produit un ensemble à partir d'un ens. de mvts
  élémentaires ; cette méthode est indépendante de la sémantique du mouvement.
  Ces groupes sont ensuite transformés en évolutions par la méthode buildEvol() qui interprète la sémantique du fichier INSEE
  des mouvements.
  La méthode asArray() exporte un groupe comme array Php afin notamment permettre de le visualiser en Yaml ou en JSON.
  La méthode mvtsPattern() retourne un motif de mouvement, permettant ainsi d'identifier les différents motifs et
  d'améliorer la qualité de la programmation de buildEvol().
methods:
*/}
class GroupMvts {
  // modalités de mod et étiquette associée à chacune
  const ModLabels = [
    '10'=> "Changement de nom",
    '20'=> "Création",
    '21'=> "Rétablissement", // = dé-fusion
    '30'=> "Suppression",
    '31'=> "Fusion simple",
    '32'=> "Création de commune nouvelle",
    '33'=> "Fusion association",
    '34'=> "Absorption de certaines c. rattachées et/ou transf. de leur statut",
      // labelINSEE: Transformation de fusion association en fusion simple
    '41'=> "Changement de code dû à un changement de département",
    '50'=> "Changement de code dû à un transfert de chef-lieu",
    '70'=> "Transformation de commune associé en commune déléguée",
  ];
  protected $mod; // code de modifications
  protected $label; // étiquette des modifications
  protected $date; // date d'effet des modifications
  protected $mvts; // [['avant/après'=>['type'=> type, 'id'=> id, 'name'=>name]]]
  
  static function readMvtsInseePerDate(string $filepath): array { // lecture du fichier CSV INSEE des mts et tri par ordre chronologique
    $mvtcoms = []; // Liste des mvts retriée par ordre chronologique
    $mvtsUniq = []; // Utilisé pour la vérification d'unicité des enregistrements
    $file = fopen($filepath, 'r');
    $headers = fgetcsv($file);
    $nbrec = 0;
    while($record = fgetcsv($file)) { // lecture des mvts et structuration dans $mvtcoms par date d'effet
      //print_r($record);
      $json = json_encode($record, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
      if (isset($mvtsUniq[$json])) {
        //echo "Erreur de doublon sur $json\n";
        continue;
      }
      $mvtsUniq[$json] = 1;
      $rec = [];
      foreach ($headers as $i => $header)
        $rec[$header] = $record[$i];
      //print_r($rec);
      $yaml = [
        'mod'=> $rec['mod'],
        'label'=> GroupMvts::ModLabels[$rec['mod']],
        'date_eff'=> $rec['date_eff'],
        'avant'=> [
          'type'=> $rec['typecom_av'],
          'id'=> $rec['com_av'],
          'name'=> $rec['libelle_av'],
        ],
        'après'=> [
          'type'=> $rec['typecom_ap'],
          'id'=> $rec['com_ap'],
          'name'=> $rec['libelle_ap'],
        ],
      ];
      addValToArray($yaml, $mvtcoms[$rec['date_eff']]);
      //echo str_replace("-\n ", '-', Yaml::dump([0 => $rec], 99, 2));
      //if (++$nbrec >= 100) break; //die("nbrec >= 100");
    }
    fclose($file);
    ksort($mvtcoms); // tri sur la date d'effet
    //echo Yaml::dump($mvtcoms, 99, 2);
    return $mvtcoms;
  }
    
  static function buildGroups(array $mvtcoms): array {
    {/*PhpDoc: methods
    name: buildGroups
    title: "static function buildGroups(array $mvtcoms): array - Regroupement d'un ens. de mvts élémentaires en un ens. de groupes de mvts"
    doc: |
      L'algorithme consiste à considérer le graphe dont les sommets sont constitués des codes INSEE de commune
      et les arêtes l'existence d'un mvt entre 2 codes.
      Les groupes de mvts sont les parties connexes de ce graphe.
      L'avantage de cet algorithme est qu'il est indépendant de la sémantique des mod.
      Les mvts élémentaires initiaux doivent être du même mod et avoir la même date d'effet.
    */}
    $result = [];
    while ($mvtcoms) { // j'itère tant qu'il reste des mvts dans l'ensemble des mvts en entrée
      $comConcerned = []; // liste des communes concernées par le groupe de mvts que je construis
      // j'initialise la liste des communes concernées avec celles de la première arrête qui n'est pas une boucle
      foreach ($mvtcoms as $i => $mvt) {
        if ($mvt['avant']['id'] <> $mvt['après']['id']) {
          $comConcerned[$mvt['avant']['id']] = 1;
          $comConcerned[$mvt['après']['id']] = 1;
          $mod = $mvt['mod'];
          break;
        }
      }
      //echo Yaml::dump(['aggrMvtsCom::$comConcerned'=> array_keys($comConcerned)], 4, 2);
      if (!$comConcerned) { // Si je n'ai trouvé aucun arc non boucle cela veut dire que je n'ai que des boucles
        // dans ce cas chaque boucle correspond à une partie connexe
        foreach ($mvtcoms as $i => $mvt)
          $result[] = new GroupMvts([$mvt]);
        //echo Yaml::dump(['GroupMvts::buildGroups::$result'=> $result], 4, 2);
        return $result;
      }
      // Sinon, ici j'ai initialisé $comConcerned avec 2 communes et $mod avec une valeur
      // puis j'ajoute à $comConcerned les mvts du même mod et dont un des 2 id appartient à $comConcerned
      // et au fur et à mesure j'ajoute à $groupOfMvts la liste des mvts ainsi sélectionnés
      $groupOfMvts = []; // liste des mvts appartenant au groupe courant
      $done = false;
      while (!$done) { // je boucle ttq j'ajoute au moins un nouveu mt au groupe
        $done = true;
        foreach ($mvtcoms as $i => $mvt) {
          //echo Yaml::dump(["mvt $i"=> $mvt]);
          if (isset($comConcerned[$mvt['avant']['id']]) || isset($comConcerned[$mvt['après']['id']])) {
            $groupOfMvts[] = $mvt;
            $comConcerned[$mvt['avant']['id']] = 1; // ajout aux communes concernées
            $comConcerned[$mvt['après']['id']] = 1; // ajout aux communes concernées
            unset($mvtcoms[$i]); // je supprime un mvt de l'ensemble en entrée ce qui garantit que je ne boucle pas
            $done = false;
          }
        }
      }
      //echo Yaml::dump(['GroupMvts::buildGroups'=> $groupOfMvts], 4, 2);
      $result[] = self::newGroupMvts($groupOfMvts);
    }
    return $result;
  }
  
  static function newGroupMvts(array $groupOfMvts) {
    $mod = $groupOfMvts[0]['mod'];
    foreach ($groupOfMvts as $mvt) {
      if ($mvt['mod'] <> $mod)
        return new MultiGroupMvts($groupOfMvts);
    }
    return new GroupMvts($groupOfMvts);
  }
  
  function __construct(array $groupOfMvts) {
    //echo Yaml::dump(['GroupMvts::__construct::$groupOfMvts'=> $groupOfMvts], 4, 2);
    $this->mod = $groupOfMvts[0]['mod'];
    $this->label = $groupOfMvts[0]['label'];
    $this->date = $groupOfMvts[0]['date_eff'];
    $this->mvts = [];
    foreach ($groupOfMvts as $mvt) {
      $this->mvts[] = [
        'avant'=> $mvt['avant'],
        'après'=> $mvt['après'],
      ];
    }
  }
  
  function mod(): string { return $this->mod; }
  
  function asArray(): array {
    $array = [
      'mod'=> $this->mod,
      'label'=> $this->label,
      'date'=> $this->date,
      'mvts'=> [],
    ];
    foreach ($this->mvts as $mvt) {
      $array['mvts'][] = [
        'avant'=> $mvt['avant'],
        'après'=> $mvt['après'],
      ];
    }
    return $array;
  }
  
  // liste des ids concernés
  function ids(): array {
    $ids = [];
    foreach($this->mvts as $mvt) {
      $ids[$mvt['avant']['id']] = 1;
      $ids[$mvt['après']['id']] = 1;
    }
    return array_keys($ids);
  }
  
  function __toString(): string {
    return "GroupMvts:{mod: $this->mod, date: $this->date, ids: [".implode(',', $this->ids())."]}";
  }
  
  // liste des names concernés
  function names(): array {
    $names = [];
    foreach($this->mvts as $mvt) {
      $names[$mvt['avant']['name']] = 1;
      $names[$mvt['après']['name']] = 1;
    }
    return array_keys($names);
  }
  
  // Teste si $this est avant $after cad au moins un (type,id,name) de $this[après] est commun avec $after[avant]
  // Ce test ne marche pas pour un 21 suivi d'un 33 qui corespond à un cas
  function isBefore(GroupMvts $after): bool {
    if (($this->mod == '21') && ($after->mod == '33'))
      return true;
    if (($this->mod == '33') && ($after->mod == '21'))
      return false;
    foreach ($this->mvts as $mvtBefore) {
      foreach ($after->mvts as $mvtAfter) {
        if (($mvtBefore['après']['type'] == $mvtAfter['avant']['type'])
          && ($mvtBefore['après']['id'] == $mvtAfter['avant']['id'])
          && ($mvtBefore['après']['name'] == $mvtAfter['avant']['name']))
            return true;
      }
    }
    return false;
  }
  
  function show(): void {
    echo "<table border=1>\n";
    echo "<tr><td><i>mod</i></td><td colspan=5>$this->mod</td></tr>\n";
    echo "<tr><td><i>label</i></td><td colspan=5>$this->label</td></tr>\n";
    echo "<tr><td><i>date</i></td><td colspan=5>$this->date</td></tr>\n";
    echo "<tr><td><b>type</b></td><td><b>id</b></td><td><b>name</b></td>",
      "<td><b>type</b></td><td><b>id</b></td><td><b>name</b></td></tr>\n";
    foreach($this->mvts as $mvt) {
      echo "<tr><td>",$mvt['avant']['type'],"</td><td>",$mvt['avant']['id'],"</td><td>",$mvt['avant']['name'],"</td>",
       "<td>",$mvt['après']['type'],"</td><td>",$mvt['après']['id'],"</td><td>",$mvt['après']['name'],"</td></tr>\n";
    }
    echo "</table>\n";
  }
  
  // factorisation des mvts sur la partie avant et standardisation de cette factorisation en changeant l'ordre des mvts
  private function factorAvant(): array {
    {/*PhpDoc: methods
    name: factorAvant
    title: "private function factorAvant(Criteria $trace): array - factorisation des mvts sur l'avant utilisée par buildEvol()"
    doc: |
      Dans les communes nouvelles, un même id est utilisé pour le chef-lieu et la commune déléguée
      Il faut donc intégrer le type dans la clé
      Le format de factorAvant est:
        [ {id_avant}=> [
            {type_avant}=> [
              'name'=> {name_avant},
              'après'=> [
                {id_après}=> [
                  {type_après}=> [
                    'name'=> {name_après}
                  ]
                ]
              ]
            ]
        ]]
    */}
    //echo Yaml::dump(['$this'=> $this->asArray()], 99, 2);
    $factAv = [];
    foreach ($this->mvts as $mvt) {
      $typav = $mvt['avant']['type'];
      $idav = $mvt['avant']['id'];
      $factAv[$idav][$typav]['name'] = $mvt['avant']['name'];
      $typap = $mvt['après']['type'];
      $idap = $mvt['après']['id'];
      if (isset($factAv[$idav][$typav]['après'][$idap][$typap])) {
        echo Yaml::dump(['$this'=> $this->asArray()], 99, 2);
        throw new Exception("Erreur d'écrasement dans factorAvant() sur $idav$typav/$idap$typap");
      }
      $factAv[$idav][$typav]['après'][$idap][$typap] = [ 'name'=> $mvt['après']['name'] ];
    }
    
    //if (0 && $trace->is(['mod'=> $this->mod])) echo Yaml::dump(['$factAv'=> $factAv], 4, 2);
    //return $factAv;
    
    // standardisation
    // je met en premier les règles ayant plusieurs après
    // puis je met dans l'ordre les COM puis les COMA puis les COMD puis les ARM
    $factAv2 = [];
    foreach ($factAv as $idav => $avant1) {
      foreach ($avant1 as $typav => $avant2)
        if ((countLeaves($avant2['après']) > 1) && ($typav == 'COM'))
          $factAv2[$idav][$typav] = $avant2;
    }
    foreach ($factAv as $idav => $avant1) {
      foreach ($avant1 as $typav => $avant2)
        if ((countLeaves($avant2['après']) > 1) && ($typav == 'COMA'))
          $factAv2[$idav][$typav] = $avant2;
    }
    foreach ($factAv as $idav => $avant1) {
      foreach ($avant1 as $typav => $avant2)
        if ((countLeaves($avant2['après']) > 1) && ($typav == 'COMD'))
          $factAv2[$idav][$typav] = $avant2;
    }
    foreach ($factAv as $idav => $avant1) {
      foreach ($avant1 as $typav => $avant2)
        if ((countLeaves($avant2['après']) > 1) && ($typav == 'ARM'))
          $factAv2[$idav][$typav] = $avant2;
    }
    foreach ($factAv as $idav => $avant1) {
      foreach ($avant1 as $typav => $avant2)
        if ((countLeaves($avant2['après']) > 1) && !in_array($typav, ['COM','COMA','COMD','ARM']))
          $factAv2[$idav][$typav] = $avant2;
    }
    foreach ($factAv as $idav => $avant1) {
      foreach ($avant1 as $typav => $avant2)
        if ((countLeaves($avant2['après']) == 1) && ($typav == 'COM'))
          $factAv2[$idav][$typav] = $avant2;
    }
    foreach ($factAv as $idav => $avant1) {
      foreach ($avant1 as $typav => $avant2)
        if ((countLeaves($avant2['après']) == 1) && ($typav == 'COMA'))
          $factAv2[$idav][$typav] = $avant2;
    }
    foreach ($factAv as $idav => $avant1) {
      foreach ($avant1 as $typav => $avant2)
        if ((countLeaves($avant2['après']) == 1) && ($typav == 'COMD'))
          $factAv2[$idav][$typav] = $avant2;
    }
    foreach ($factAv as $idav => $avant1) {
      foreach ($avant1 as $typav => $avant2)
        if ((countLeaves($avant2['après']) == 1) && ($typav == 'ARM'))
          $factAv2[$idav][$typav] = $avant2;
    }
    foreach ($factAv as $idav => $avant1) {
      foreach ($avant1 as $typav => $avant2)
        if ((countLeaves($avant2['après']) == 1) && !in_array($typav, ['COM','COMA','COMD','ARM']))
          $factAv2[$idav][$typav] = $avant2;
    }

    // je met les variables après dans l'ordre des avants
    // et dans chaque partie après de la règle j'ordonne les variables par no
    $vars = []; // [ {id} => {no}]
    $no = 0;
    foreach ($factAv2 as $idav => $avant)
      $vars[$idav] = ++$no;
    foreach ($factAv2 as $idav => $avant1) {
      foreach ($avant1 as $typav => $avant2)
        foreach (array_keys($avant2['après']) as $idap)
          if (!isset($vars[$idap]))
            $vars[$idap] = ++$no;
    }
    foreach ($factAv2 as $idav => $avant1) {
      foreach ($avant1 as $typav => $avant2) {
        $done = false;
        $compteur = 0;
        while (!$done) {
          $done = true;
          foreach (array_keys($avant2['après']) as $no => $idap) {
            if ($no <> 0) {
              if ($vars[$idap] < $vars[$idapprev]) {
                $avant2['après'] = swapKeysInArray($idapprev, $idap, $avant2['après']);
                $done = false;
                break;
              }
            }
            $idapprev = $idap;
          }
          if ($compteur++ > 1000) die("compteur explosé 1000 ligne ".__LINE__);
        }
        $factAv2[$idav][$typav] = $avant2;
      }
    }
    return $factAv2;
  }
  
  // restructuration en [['id'=>id, ,'type'=>type, 'name'=>name, 'après'=> [['id'=>id, ,'type'=>type, 'name'=>name]]]]
  // plus facile à utiliser dans addToRpicom()
  function factAvant2(): array {
    $fav2 = [];
    foreach ($this->factorAvant() as $idav => $avant1) {
      foreach ($avant1 as $typav => $avant) {
        $sfav2 = [];
        foreach ($avant['après'] as $idap => $après1) {
          foreach ($après1 as $typap => $après) {
            $sfav2[] = ['id'=> $idap, 'type'=> $typap, 'name'=> $après['name']];
          }
        }
        $fav2[] = ['id'=> $idav, 'type'=> $typav, 'name'=> $avant['name'], 'après'=> $sfav2];
      }
    }
    return $fav2;
  }
  
  // fabrique un nouvel objet GroupMvts en utilisant l'ordre de factorAvant
  function factAvDefact(array $factAv=[]): GroupMvts {
    if (!$factAv)
      $factAv = $this->factorAvant();
    $groupOfMvts = [];
    foreach ($factAv as $idav => $avant1) {
      foreach ($avant1 as $typav => $avant) {
        foreach ($avant['après'] as $idap => $après1) {
          foreach ($après1 as $typap => $après) {
            $groupOfMvts[] = [
              'mod'=> $this->mod,
              'label'=> $this->label,
              'date_eff'=> $this->date,
              'avant'=> [
                'type'=> $typav,
                'id'=> $idav,
                'name'=> $avant['name'],
              ],
              'après'=> [
                'type'=> $typap,
                'id'=> $idap,
                'name'=> $après['name'],
              ],
            ];
          }
        }
      }
    }
    return new self($groupOfMvts);
  }
  
  function mvtsPattern(Criteria $trace): array {
    {/*PhpDoc: methods
    name: mvtsPattern
    title: "function mvtsPattern(Criteria $trace): array - fabrique le motif correspondant au groupe"
    */}
    $factAv = $this->factorAvant($trace);
    if ($trace->is(['mod'=> $this->mod]))
      echo Yaml::dump(['factorAvant'=> $factAv], 6, 2);
    $mvtsPat = [
      'mod'=> $this->mod,
      'label'=> $this->label,
      'nb'=> 1, // utilisé pour lister les patterns et compter leur nbre d'occurence
      'règles'=> [],
      'example'=> [
        'factAv'=> ['date'=> $this->date, '$factAv'=> $factAv],
        'group'=> $this,
      ],
    ];
    $vars = []; // [ {id} => {no}]
    $no = 0;
    foreach ($factAv as $idav => $avant1) {
      foreach ($avant1 as $typav => $avant2)
        if (!isset($vars[$idav]))
          $vars[$idav] = ++$no;
    }
    $suffix = ['COM'=>'', 'COMA'=>'a', 'COMD'=>'d', 'ARM'=>'m'];
    foreach ($factAv as $idav => $avant1) {
      foreach ($avant1 as $typav => $avant2) {
        foreach($avant2['après'] as $idap => $après1) {
          foreach($après1 as $typap => $après2) {
            if (!isset($vars[$idap]))
              $vars[$idap] = ++$no;
            $idavf = 'id'.$vars[$idav].$suffix[$typav];
            $idapf = 'id'.$vars[$idap].$suffix[$typap];
            addValToArray(
              $idapf,
              $mvtsPat['règles'][$idavf]);
          }
        }
      }
    }
    if ($trace->is(['mod'=> $this->mod]))
      echo Yaml::dump(['$mvtsPat'=> $mvtsPat], 4, 2);
    return $mvtsPat;
  }
  
  // evol par défaut
  function defaultEvol(string $idChefLieuAv,string $idChefLieuAp, Base $coms, Criteria $trace): array {
    foreach ($this->factorAvant($trace) as $idav => $avant1) {
      foreach ($avant1 as $typav => $avant) {
        if ($typav == 'COM')
          $input[$idav]['name'] = $avant['name'];
        elseif ($typav == 'COMA')
          $input[$idChefLieuAv]['associées'][$idav]['name'] = $avant['name'];
        elseif ($typav == 'COMD')
          $input[$idChefLieuAv]['déléguées'][$idav]['name'] = $avant['name'];
        elseif ($typav == 'ARM')
          $input[$idChefLieuAv]['ardtMun'][$idav]['name'] = $avant['name'];
        else
          throw new Exception("Cas imprévu ligne ".__LINE__);
        // première itération uniq. sur le nom pour qu'il apparaisse en premier
        foreach ($avant['après'] as $idap => $après1) {
          foreach ($après1 as $typap => $après) {
            if ($typap == 'COM')
              $result[$idap]['name'] = $après['name'];
          }
        }
        foreach ($avant['après'] as $idap => $après1) {
          foreach ($après1 as $typap => $après) {
            if ($typap == 'COM')
              $result[$idap]['name'] = $après['name'];
            elseif ($typap == 'COMA')
              $result[$idChefLieuAp]['associées'][$idap]['name'] = $après['name'];
            elseif ($typap == 'COMD')
              $result[$idChefLieuAp]['déléguées'][$idap]['name'] = $après['name'];
            elseif ($typap == 'ARM')
              $result[$idChefLieuAp]['ardtMun'][$idap]['name'] = $après['name'];
            else
              throw new Exception("Cas imprévu ligne ".__LINE__);
          }
        }
      }
    }
    foreach (array_keys($input) as $idav) {
      if (!isset($result[$idav]))
        unset($coms->$idav);
    }
    foreach ($result as $idap => $com) {
      if ($idap <> '69123')
        $coms->$idap = $com;
    }
    return [
      'mod'=> $this->mod,
      'label'=> $this->label,
      'date'=> $this->date,
      'input'=> $input,
      'result'=> $result,
    ];
  }
  
  function buildEvol(Base $coms, Criteria $trace): array {
    {/*PhpDoc: methods
    name: buildEvol
    title: "function buildEvol(Base $coms, Criteria $trace): array - Fabrique une évolution sémantique à partir d'un groupe de mvts et met à jour la base des communes"
    */}
    
    switch($this->mod) {
      case '10': { // Changement de nom
        $factAv = $this->factorAvant($trace);
        $idChefLieu = array_keys($factAv)[0];
        return $this->defaultEvol($idChefLieu, $idChefLieu, $coms, $trace);
      }

      case '20': { // Création
        return $this->defaultEvol('PasDeChefLieu', 'PasDeChefLieu', $coms, $trace);
      }
        
      case '21': { // Rétablissement
        $factAv = $this->factorAvant(new Criteria(['not']));
        // J'utilise le principe que l'idChefLieu est clé de la première ligne et doit aussi être parmi les après
        $idChefLieu = array_keys($factAv)[0];
        $typChefLieu = array_keys($factAv[$idChefLieu])[0];
        $après = $factAv[$idChefLieu][$typChefLieu]['après'];
        if ($typChefLieu == 'ARM') { // cas particulier des scissions des ARM de Lyon (69123)
          $idLyon = '69123';
          $lyon = $coms->$idLyon;
          foreach ($après as $idap => $après1)
            foreach ($après1 as $typap => $après)
              $lyon['ardtMun'][$idap]['name'] = $après['name'];
          $coms->$idLyon = $lyon;
          return $this->defaultEvol('69123', '69123', $coms, $trace);
        }
        if (!in_array($idChefLieu, array_keys($après))) {
          return [
              'mod'=> $this->mod,
              'label'=> $this->label,
              'date'=> $this->date,
              'ALERTE'=> "Erreur: idChefLieu non trouvé ligne ".__LINE__,
              'input'=> $this->asArray(),
            ];
        }
        return $this->defaultEvol($idChefLieu, $idChefLieu, $coms, $trace);
      }
 
      case '30': { // Suppression
        return $this->defaultEvol('PasDeChefLieu', 'PasDeChefLieu', $coms, $trace);
      }
    
      case '31': { // Fusion simple
        // Ttes les règles sont de la forme {id{i}=> id0} où id0 est le la commune fusionnée
        return $this->defaultEvol('PasDeChefLieu', 'PasDeChefLieu', $coms, $trace);
      }

      case '32': { // Création de commune nouvelle
        // Le ChefLieuAp est le seul id d'arrivée qui est de type COM
        $idChefLieuAp = null;
        foreach ($this->factorAvant($trace) as $idav => $avant1) {
          foreach ($avant1 as $typav => $avant) {
            foreach ($avant['après'] as $idap => $après1) {
              foreach ($après1 as $typap => $après) {
                if ($typap == 'COM') {
                  $idChefLieuAp = $idap;
                  break 4;
                }
              }
            }
          }
        }
        if (!$idChefLieuAp)
          return [
              'mod'=> $this->mod,
              'label'=> $this->label,
              'date'=> $this->date,
              'ALERTE'=> "Erreur: idChefLieu non trouvé ligne ".__LINE__,
              'input'=> $this->asArray(),
            ];
        return $this->defaultEvol('PasDeChefLieu', $idChefLieuAp, $coms, $trace);
      }
    
      case '33': // Fusion association
      case '34': { // Transformation de fusion association en fusion simple ou suppression de communes déléguées
        // Le ChefLieu est le seul id d'arrivée qui est de type COM
        $idChefLieu = null;
        foreach ($this->factorAvant(new Criteria(['not'])) as $idav => $avant1) {
          foreach ($avant1 as $typav => $avant) {
            foreach ($avant['après'] as $idap => $après1) {
              foreach ($après1 as $typap => $après) {
                if ($typap == 'COM') {
                  $idChefLieu = $idap;
                  break 4;
                }
              }
            }
          }
        }
        if (!$idChefLieu)
          return [
              'mod'=> $this->mod,
              'label'=> $this->label,
              'date'=> $this->date,
              'ALERTE'=> "Erreur: idChefLieu non trouvé ligne ".__LINE__,
              'input'=> $this->asArray(),
            ];
        return $this->defaultEvol($idChefLieu, $idChefLieu, $coms, $trace);
      }

      case '41': { // Changement de code dû à un changement de département
        return $this->defaultEvol('PasDeChefLieu', 'PasDeChefLieu', $coms, $trace);
      }

      case '50': { // Changement de code dû à un transfert de chef-lieu
        // Le ChefLieuAv est le seul id avant qui est de type COM
        $idChefLieuAv = null;
        foreach ($this->factorAvant($trace) as $idav => $avant1) {
          foreach ($avant1 as $typav => $avant) {
            if ($typav == 'COM') {
              $idChefLieuAv = $idav;
                break 2;
            }
          }
        }
        // Le ChefLieuAp est le seul id d'arrivée qui est de type COM
        $idChefLieuAp = null;
        foreach ($this->factorAvant($trace) as $idav => $avant1) {
          foreach ($avant1 as $typav => $avant) {
            foreach ($avant['après'] as $idap => $après1) {
              foreach ($après1 as $typap => $après) {
                if ($typap == 'COM') {
                  $idChefLieuAp = $idap;
                  break 4;
                }
              }
            }
          }
        }
        if (!$idChefLieuAv || !$idChefLieuAp)
          return [
              'mod'=> $this->mod,
              'label'=> $this->label,
              'date'=> $this->date,
              'ALERTE'=> "Erreur: idChefLieu non trouvé ligne ".__LINE__,
              'input'=> $this->asArray(),
            ];
        return $this->defaultEvol($idChefLieuAv, $idChefLieuAp, $coms, $trace);
      }
    
      case '70': { // Transformation de commune associée en commune déléguée
        if (($this->date == '2020-01-01') && ($this->mvts[0]['avant']['id'] == '52064')) {
          /*$factAv:
            52064:
              COM:
                name: Bourmont-entre-Meuse-et-Mouzon
                après:
                  52224: { COMD: { name: Gonaincourt } }
            Je considère que c'est une erreur INSEE, 
            n'existe pas sur le COG du web https://www.insee.fr/fr/metadonnees/cog/commune/COM52224-gonaincourt
          */
          return [];
        }
        return $this->defaultEvol('PasDeChefLieu', 'PasDeChefLieu', $coms, $trace);
      }
    
      default:
        throw new Exception("mod $this->mod non traité ligne ".__LINE__);
    }
  }

  function analyzeMod32(): array { // analyse des GpMvts mode 32 et retourne une structure adhoc
    // recherche de la nouvelle c. de rattachement
    //echo "Appel de analyzeMod32()\n";
    foreach ($this->mvts as $mvt) {
      if ($mvt['après']['type'] == 'COM') {
        $ncrat = $mvt['après'];
        break;
      }
    }
    if (!isset($ncrat)) {
      die("<b>Erreur ncrat non trouvé</b>\n");
    }
    //echo Yaml::dump(['$ncrat'=> $ncrat], 1, 2);
    $graphe = []; // [idav => [typav => avant + ['après'=> [typidap => après]]]
    $mvts = []; // recopie des mvts non traités
    foreach ($this->mvts as $mvt) {
      if (($mvt['après']['type'] == 'COM') && ($mvt['après']['id'] == $ncrat['id'])) {
        $graphe[$mvt['avant']['id']][$mvt['avant']['type']] = array_merge($mvt['avant'], ['après'=> []]);
      }
      else
        $mvts[] = $mvt;
    }
    foreach ($mvts as $mvt) {
      if (!isset($graphe[$mvt['avant']['id']][$mvt['avant']['type']]))
        die("<b>entrée dans graphe non trouvée</b>\n");
      $graphe[$mvt['avant']['id']][$mvt['avant']['type']]
        ['après'][$mvt['après']['type'].$mvt['après']['id']] = $mvt['après'];
    }
    // $graphe : [idav => [typav => avant + ['après'=> [typidap => après]]]
    if (1) { // vérification du schéma de $graphe
      $schemaSrce = <<<'EOT'
type: object
additionalProperties: false
patternProperties:
  '^\d[\dAB]\d\d\d$': # idav
    type: object
    additionalProperties: false
    patternProperties:
      '^(COM|COMD|COMA)$': # typav
        type: object
        additionalProperties: false
        properties:
          type: {enum: [COM, COMD, COMA]}
          id: {type: [string, integer]}
          name: {type: string}
          après:
            type: object
            additionalProperties: false
            patternProperties:
              '^(COM|COMD)\d[\dAB]\d\d\d$': # typidap
                type: object
                additionalProperties: false
                properties:
                  type: {enum: [COM, COMD]}
                  id: {type: [string, integer]}
                  name: {type: string}

EOT;
      $schema = new JsonSchema(Yaml::parse($schemaSrce));
      $status = $schema->check($graphe);
      if (!$status->ok()) {
        echo Yaml::dump(['$graphe'=> $graphe], 3, 2);
        $status->showErrors();
        die("Arrêt ligne ".__LINE__);
      }
    }
    $pcrat = null;
    foreach ($graphe as $idav => $avant1) {
      //echo Yaml::dump(['$graphe: $idav => $avant1'=> [$idav => $avant1]], 4, 2);
      if ($idav == $ncrat['id']) { // ncrat
        $ncrat['prevName'] = $avant1['COM']['name'];
        //unset($graphe[$idav]['COM']);
        //if (isset($graphe[$idav]['COMD']))
          //$graphe[$idav] = $graphe[$idav]['COMD'];
        $graphe[$idav] = array_values($avant1)[0];
      }
      elseif (count($avant1) > 1) {
        echo "<b>Dans analyzeMod32() chgt de c. de rattachement de $idav pour $ncrat[id]</b>\n";
        if ($pcrat) {
          die("Erreur de 2nde précédente c. de rattachement\n");
        }
        $pcrat = $avant1['COM'];
        unset($pcrat['après']);
        //unset($graphe[$idav]['COM']);
        $graphe[$idav] = $graphe[$idav]['COMD'];
      }
      else {
        $graphe[$idav] = array_values($avant1)[0];
      }
      // $graphe : [ idav => avant + ['après'=> [typidap => après]]]
      //echo Yaml::dump(['$graphe[$idav]'=> $graphe[$idav]], 4, 2);
      if ($graphe[$idav]['après'])
        $graphe[$idav]['après'] = array_values($graphe[$idav]['après'])[0];
      // $graphe : [ idav => avant + ['après'=> après]]
    }
    if ($pcrat) {
      //$this->show();
      $result = [
        'ncrat'=> $ncrat,
        'pcrat'=> $pcrat,
        'transformations'=> $graphe,
      ];
      //echo Yaml::dump(['$result'=> $result], 4, 2);
    }
    else {
      //$this->show();
      $result = [
        'ncrat'=> $ncrat,
        'transformations'=> $graphe,
      ];
      //echo Yaml::dump(['$result'=> $result], 4, 2);
    }
    /* Format du résultat
    'ncrat' => ['type'=> type, 'id'=> id, 'name'=> name, 'prevName'=> prevName] // nlle commune de rattachement
    ('pcrat' => ['type'=> type, 'id'=> id, 'name'=> name])? // évent. précédente c. de rattachement
    'transformations' => [ // ens. de règles id -> id
      id => ['type'=> type, 'id'=> id, 'name'=> name, 'après'=> ['type'=> type, 'id'=> id, 'name'=> name]]
    ]
    */
    if (1) { // vérification du schéma du résultat
      $schemaSrce = <<<'EOT'
type: object
additionalProperties: false
required: [ncrat, transformations]
properties:
  ncrat:
    type: object
    additionalProperties: false
    required: [type, id, name, prevName]
    properties:
      type: {enum: [COM]}
      id: {type: [string, integer]}
      name: {type: string}
      prevName: {type: string}
  pcrat:
    type: object
    additionalProperties: false
    required: [type, id, name]
    properties:
      type: {enum: [COM]}
      id: {type: [string, integer]}
      name: {type: string}
  transformations:
    type: object
    additionalProperties: false
    patternProperties:
      '^\d[\dAB]\d\d\d$': # id
        type: object
        additionalProperties: false
        properties:
          type: {enum: [COM, COMD, COMA]}
          id: {type: [string, integer]}
          name: {type: string}
          après:
            type: object
            additionalProperties: false
            properties:
              type: {enum: [COMD]}
              id: {type: [string, integer]}
              name: {type: string}

EOT;
      $schema = new JsonSchema(Yaml::parse($schemaSrce));
      $status = $schema->check($result);
      if (!$status->ok()) {
        echo Yaml::dump(['analyzeMod32():$result'=> $result], 6, 2);
        $status->showErrors();
        die("Arrêt ligne ".__LINE__);
      }
    }
    return $result;
  }

  function addToRpicom(Base $rpicom, Criteria $trace): void {
    {/*PhpDoc: methods
    name: rpicom
    title: "function addToRpicom(Base $$rpicom, Criteria $trace): void - Ajoute au RPICOM le groupe"
    doc: |
      Nlle version démarrée le 20/4/2020 s'appuyant sur factAvant2() pour simplifier le code.
    */}
    if ($trace->is(['mod'=> $this->mod]))
      $rpicom->startExtractAsYaml();
    switch($this->mod) {
      case '10': { // Changement de nom
        $fav2 = $this->factAvant2();
        //echo Yaml::dump(['$fav2'=> $fav2]);
        $rpicom->mergeToRecord($fav2[0]['id'], [
          $this->date => [
            'évènement'=> ['changeDeNomPour'=> $fav2[0]['après'][0]['name']],
            'name'=> $fav2[0]['name'],
          ]
        ]);
        break;
      }
      
      case '20': { // Création
        $fav2 = $this->factAvant2();
        $idcréé = $fav2[0]['après'][1]['id'];
        $crééeAPartirDe = [];
        foreach ($fav2 as $avant)
          $crééeAPartirDe[] = $avant['id'];
        $rpicom->mergeToRecord($idcréé, [
          $this->date => [
            'évènement'=> ['crééeAPartirDe'=> $crééeAPartirDe]
          ]
        ]);
        foreach ($fav2 as $avant) {
          $rpicom->mergeToRecord($avant['id'], [
            $this->date => [
              'évènement'=> ['contribueA'=> $idcréé],
              'name'=> $avant['name'],
            ]
          ]);
        }
        break;
      }
      
      case '21': { // Rétablissement
        /*Cas particulier des 2 ARM créés
          Sinon
            La commune de rattachement est toujours id1 définie dans la 1ère règle
            Le reste de la première règle donne les entités créées
            Les autres règles sont de la forme:
              {idia: [idi]}   <=> la c.a. $idia est rétablie comme c.s.
              {idia: [idia]}  <=> la c.a. $idia reste associée
              {idid: [idi]}   <=> la c.d. $idid est rétablie comme c.s.
            De plus des c. dans la partie droite de la règle 1 indique de nouvelles entités
        */
        $fav2 = $this->factAvant2();
        if ($fav2[0]['type'] == 'ARM') { // cas particuliers {id1m: [id1m, id2m]}
          $ardtMcréé = $fav2[0]['après'][1];
          $rpicom->mergeToRecord($ardtMcréé['id'], [
            $this->date => [
              'après'=> ['name' => $ardtMcréé['name']],
              'évènement'=> ['rétabliCommeArrondissementMunicipalDe'=> $fav2[0]['id']],
            ]
          ]);
          break;
        }
        $idr = $fav2[0]['id']; // La c. de rattachement
        $comr = [
          'évènement'=> "Commune rétablissant des c. rattachées ou fusionnées",
          'name'=> $fav2[0]['name'],
        ];
        $rétablies = []; // identifiants et après des entités rétablies définies par la 1ère règle
        foreach ($fav2[0]['après'] as $après) {
          if ($après['id'] <> $idr)
            $rétablies[$après['id']] = $après;
        }
        array_shift($fav2);
        foreach ($fav2 as $avant) {
          if ($avant['type'] == 'COMD') { // {idid: [idi]} <=> la c.d. $idid est rétablie comme c.s.
            if ($avant['id'] <> $idr) {
              $rpicom->mergeToRecord($avant['id'], [
                $this->date => [
                  'évènement'=> "Commune déléguée rétablie comme commune simple",
                  'name'=> $avant['name'],
                  'estDéléguéeDe'=> $idr,
                ]
              ]);
            }
            else { // cas {idrd: [idr]}
              $comr['commeDéléguée'] = ['name'=> $avant['name']];
            }
            unset($rétablies[$avant['id']]);
          }
          elseif ($avant['après'][0]['type'] == 'COM') { // cas {idia: [idi]}   <=> la c.a. $idia est rétablie comme c.s.
            $rpicom->mergeToRecord($avant['id'], [
              $this->date => [
                'évènement'=> "Commune associée rétablie comme commune simple",
                'name'=> $avant['name'],
                'estAssociéeA'=> $idr,
              ]
            ]);
            unset($rétablies[$avant['id']]);
          }
          elseif ($avant['après'][0]['type'] == 'COMA') { // cas {idia: [idia]}  <=> la c.a. $idia reste associée
            $rpicom->mergeToRecord($avant['id'], [
              $this->date => [
                'évènement'=> ['resteAssociéeA'=> $idr],
                'name'=> $avant['name'],
                'estAssociéeA'=> $idr,
              ]
            ]);
            unset($rétablies[$avant['id']]);
          }
        }
        $rpicom->mergeToRecord($idr, [
          $this->date => $comr
        ]);
        foreach ($rétablies as $rétablie) { // les autres entités rétablies
          if ($rétablie['type'] == 'COM') {
            $rpicom->mergeToRecord($rétablie['id'], [
              $this->date => [
                'évènement'=> ['rétablieCommeSimpleDe'=> $idr],
              ]
            ]);
          }
          else {
            $rpicom->mergeToRecord($rétablie['id'], [
              $this->date => [
                'évènement'=> ['rétablieCommeAssociéeDe'=> $idr],
              ]
            ]);
          }
        }
        break;
      }
      
      case '30': { // Suppression
        $fav2 = $this->factAvant2();
        $idsup = $fav2[0]['id'];
        $seDissoutDans = [];
        foreach ($fav2[0]['après'] as $après)
          $seDissoutDans[] = $après['id'];
        $rpicom->mergeToRecord($idsup, [
          $this->date => [
            'évènement'=> ['seDissoutDans'=> $seDissoutDans],
            'name'=> $fav2[0]['name'],
          ]
        ]);
        foreach ($fav2[0]['après'] as $après) {
          $rpicom->mergeToRecord($après['id'], [
            $this->date => [
              'évènement'=> ['reçoitUnePartieDe'=> $idsup],
              'name'=> $après['name'],
            ]
          ]);
        }
        break;
      }
      
      case '32': { // Création de commune nouvelle
        // Il faut identifier un jeu test avec les différents cas type pour vérifier dessus le résultat
        $jeutest = [
          '49018', // chgt de c. de rattachement de 49101 pour 49018
          '49101', // chgt de c. de rattachement de 49101 pour 49018
          '49149', // chgt de c. de rattachement de 49149 pour 49261
          '49261', // chgt de c. de rattachement de 49149 pour 49261
          '49065', // chgt de c. de rattachement de 49065 pour 49080
          '49080', // chgt de c. de rattachement de 49065 pour 49080
          '21183', // avec 2 déléguées
          '01033', // avec 3 déléguées
          '14126', // avec absorption des déléguées et COMA en entrée
          '14514', // simple sans déléguée
        ];
        $jeutest = [];
        
        $ana32 = $this->analyzeMod32();
        /* Format analyzeMod32()
        'ncrat' => ['type'=> type, 'id'=> id, 'name'=> name, 'prevName'=> prevName] // nlle commune de rattachement
        ('pcrat' => ['type'=> type, 'id'=> id, 'name'=> name])? // évent. précédente c. de rattachement
        'transformations' => [ // ens. de règles id -> id
          id => ['type'=> type, 'id'=> id, 'name'=> name, 'après'=> ['type'=> type, 'id'=> id, 'name'=> name]]
        ]
        */
        $ncrat = $ana32['ncrat'];
        if (in_array($ncrat['id'], $jeutest)) {
          $this->show();
          echo Yaml::dump(['$ana32'=> $ana32], 4, 2);
          $rpicom->startExtractAsYaml();
        }
        
        $pcrat = $ana32['pcrat'] ?? null;
        if (!$pcrat) {
          $comrat = [
            'après'=> [ 'name'=> $ncrat['name'] ],
            'évènement' => "Se crée en commune nouvelle",
            'name' => $ncrat['prevName'],
          ];
          if ($ana32['transformations'][$ncrat['id']]['après']) {
            $comrat['évènement'] = "Se crée en commune nouvelle avec commune déléguée propre";
            $comrat['après']['commeDéléguée'] = ['name'=> $ana32['transformations'][$ncrat['id']]['après']['name']];
          }
          $rpicom->mergeToRecord($ncrat['id'], [ $this->date => $comrat ]);
        }
        else {
          $comrat = [
            'après'=> [
              'name'=> $ncrat['name']
            ],
            'évènement' => "Devient commune nouvelle",
            'name' => $ncrat['prevName'],
          ];
          if ($ana32['transformations'][$ncrat['id']]['après']) {
            $comrat['évènement'] = "Se crée en commune nouvelle avec commune déléguée propre";
            $comrat['après']['commeDéléguée'] = ['name'=> $ana32['transformations'][$ncrat['id']]['après']['name']];
          }
          $rpicom->mergeToRecord($ncrat['id'], [ $this->date => $comrat ]);
          $compcrat = [
            'évènement'=> ['perdRattachementPour' => $ncrat['id']],
            'name' => $pcrat['name'],
          ];
          if ($ana32['transformations'][$pcrat['id']]) {
            $compcrat['commeDéléguée'] = ['name'=> $ana32['transformations'][$pcrat['id']]['name']];
          }
          $rpicom->mergeToRecord($pcrat['id'], [ $this->date => $compcrat ]);
        }
        // expression de l'évolution du statut des communes autres que ncrat et pcrat ainsi que leur ancien état
        foreach ($ana32['transformations'] as $id => $transfo) {
          // $transfo : ['type'=> type, 'id'=> id, 'name'=> name, 'après'=> ['type'=> type, 'id'=> id, 'name'=> name]]
          if (($id <> $ncrat['id']) && (!$pcrat || ($id <> $pcrat['id']))) {
            if ($transfo['après']) {
              $comd = [
                'après'=> [
                  'name'=> $transfo['après']['name'],
                  'estDéléguéeDe'=> $ncrat['id'],
                ],
              ];
              if (($transfo['type'] <> 'COMD') || $pcrat)
                $comd['évènement'] = ['devientDéléguéeDe'=> $ncrat['id']];
              else
                $comd['évènement'] = ['resteDéléguéeDe'=> $ncrat['id']];
              $comd['name'] = $transfo['name'];
            }
            else {
              $comd = [
                'après'=> [], // signifie qu'elle disparait
                'évènement'=> ['seFondDans'=> $ncrat['id']],
                'name'=> $transfo['name'],
              ];
            }
            if ($transfo['type'] == 'COMA')
              $comd['estAssociéeA'] = 'unknown';
            elseif ($transfo['type'] == 'COMD')
              $comd['estDéléguéeDe'] = 'unknown';
            $rpicom->mergeToRecord($id, [ $this->date => $comd ]);
          }
        }
        if (in_array($ncrat['id'], $jeutest)) {
          $rpicom->showExtractAsYaml(5, 2);
        }
        break;
      }
      
      case '31': // Fusion simple
      case '33': { // Fusion association
        /* la c. de rattachement est définie par la seule règle {idr: [idr]}
          Les autres règles sont de la forme:
            {idi: [idia,  idr]} <=> idi s'associe à idr
            {idi: [idr]} <=> idi fusionneDans idr
        */
        $fav2 = $this->factAvant2();
        //echo Yaml::dump(['$fav2'=> $fav2], 4, 2);
        // identif. de la c. de rattachement - définie par la seule règle {idr: [idr]}
        foreach ($fav2 as $no => $avant) {
          if ((count($avant['après']) == 1) && ($avant['après'][0]['id'] == $avant['id'])) {
            $idr = $avant['id'];
            $rpicom->mergeToRecord($idr, [
              $this->date => [
                'évènement' => "Prend des c. associées et/ou absorbe des c. fusionnées",
                'name' => $avant['name'],
              ]
            ]);
            unset($fav2[$no]);
            break;
          }
        }
        if (!isset($idr)) { // cas particulier de création d'un nouvel id dans le cas d'une Fusion simple
          $idr = $fav2[0]['après'][0]['id'];
          $fusionnées = [];
          foreach ($fav2 as $no => $avant) {
            $fusionnées[] = $avant['id'];
          }
          $rpicom->mergeToRecord($idr, [
            $this->date => [
              'évènement'=> ['crééeParFusionSimpleDe' => $fusionnées],
            ]
          ]);
        }
        // balaie les c. associées ou fusionnées
        foreach ($fav2 as $no => $avant) {
          if (count($avant['après']) == 1) { // cas {idi: [idr]} <=> idi fusionneDans idr
            $com = [
              'évènement'=> ['fusionneDans' => $idr],
              'name' => $avant['name'],
            ];
          }
          else { // {idi: [idia,  idr]} <=> idi s'associe à idr
            $com = [
              'évènement'=> ['sAssocieA' => $idr],
              'name' => $avant['name'],
            ];
          }
          if ($avant['type'] == 'COMA')
            $com['estAssociéeA'] = 'unknown';
          $rpicom->mergeToRecord($avant['id'], [ $this->date => $com ]);
        }
        break;
      }

      case '34': { // Absorption de certaines c. associées ou déléguées
        $factAv = $this->factorAvant($trace);
        $fav2 = $this->factAvant2();
        foreach ($fav2 as $noav => $avant) {
          if ($noav == 0) { // Commune de rattachement
            $idr = $avant['id'];
            // cas particulier de chgt de département consécutif à l'absorption
            if ((count($avant['après'])==1) && ($avant['après'][0]['id'] <> $idr)) {
              echo "cas particulier détecté de chgt de département consécutif à l'absorption sur $idr\n";
              // le code $idr disparait
              $rpicom->mergeToRecord($idr, [
                $this->date => [
                  'évènement'=> ['quitteLeDépartementEtPrendLeCode'=> $avant['après'][0]['id']],
                  'name'=> $avant['après'][0]['name'],
                ]
              ]);
              // le code après disparait
              $rpicom->mergeToRecord($avant['après'][0]['id'], [
                $this->date => [
                  'évènement'=> ['arriveDansLeDépartementAvecLeCode'=> $idr],
                ]
              ]);
            }
            $comr = [
              'après'=> [ 'name'=> $avant['après'][0]['name'] ],
              'évènement'=> "Absorbe certaines de ses c. rattachées ou certaines de ses c. associées deviennent déléguées",
              'name'=> $avant['name']
            ];
          }
          elseif (count($avant['après']) == 1) { // la c. avant fusionne dans idr
            if (($avant['id'] == $idr) && ($avant['type']=='COMD')) { // cas {i1d: [id1]}
              $comr['commeDéléguée'] = [
                'name'=> $avant['name'],
              ];
            }
            else { // cas {idi: [id1]}
              $état = $avant['type']=='COMA' ? 'estAssociéeA' : ($avant['type']=='COMD' ? 'estDéléguéeDe' : $avant['type']);
              $rpicom->mergeToRecord($avant['id'], [
                $this->date => [
                  'évènement'=> ['fusionneDans'=> $idr],
                  'name'=> $avant['name'],
                   $état=> $idr,
                ]
              ]);
            }
          }
          elseif ($avant['après'][1]['type'] == $avant['type']) { // cas {idi: [id1, idi]}
            $action = $avant['type']=='COMA' ? 'resteAssociéeA' :
               ($avant['type']=='COMD' ? 'resteDéléguéeDe' : $avant['type']);
            $état = $avant['type']=='COMA' ? 'estAssociéeA' : ($avant['type']=='COMD' ? 'estDéléguéeDe' : $avant['type']);
            $rpicom->mergeToRecord($avant['id'], [
              $this->date => [
                'évènement'=> [$action => $idr],
                'name' => $avant['name'],
                 $état => $idr,
              ]
            ]);
          }
          elseif (($avant['après'][1]['type'] == 'COMD') && ($avant['type'] == 'COMA')) { // cas {idia: [id1, idid]}
            // example: 39256
            $action = $avant['type']=='COMA' ? 'resteAssociéeA' :
               ($avant['type']=='COMD' ? 'resteDéléguéeDe' : $avant['type']);
            $état = $avant['type']=='COMA' ? 'estAssociéeA' : ($avant['type']=='COMD' ? 'estDéléguéeDe' : $avant['type']);
            $rpicom->mergeToRecord($avant['id'], [
              $this->date => [
                'évènement'=> ['changedAssociéeEnDéléguéeDe' => $idr],
                'name' => $avant['name'],
                 $état => $idr,
              ]
            ]);
          }
          else {
            echo Yaml::dump([$noav => $avant]);
            throw new Exception("mod $this->mod non traité ligne ".__LINE__);
          }
        }
        $rpicom->mergeToRecord($idr, [ $this->date => $comr ]);
        $rpicom->showExtractAsYaml(5, 2);
        return;
        //die("Arrêt ligne ".__LINE__);
      }
      
      case '41': { // Changement de code dû à un changement de département
        // motif {id1: [id2]}
        $fav2 = $this->factAvant2();
        // Le code avant est créé
        $rpicom->mergeToRecord($fav2[0]['id'], [
          $this->date => [
            'évènement'=> ['quitteLeDépartementEtPrendLeCode'=> $fav2[0]['après'][0]['id']],
            'name'=> $fav2[0]['name'],
          ]
        ]);
        // le code après disparait
        $rpicom->mergeToRecord($fav2[0]['après'][0]['id'], [
          $this->date => [
            'évènement'=> ['arriveDansLeDépartementAvecLeCode'=> $fav2[0]['id']],
          ]
        ]);
        break;
      }
      
      case '50': { // Changement de code dû à un transfert de chef-lieu
        $fav2 = $this->factAvant2();
        //echo Yaml::dump(['$fav2'=> $fav2], 4, 2);
        
        // nouveau chef-lieu
        $ratap = array_pop($fav2);
        $rpicom->mergeToRecord($ratap['id'], [
          $this->date => [
            'évènement'=> "Commune rattachée devient commune de rattachement",
            'name'=> $ratap['name'],
          ]
        ]);
        // ancien chef-lieu
        $ratav = array_shift($fav2);
        $rpicom->mergeToRecord($ratav['id'], [
          $this->date => [
            'évènement'=> ['perdRattachementPour'=> $ratav['après'][1]['id']],
            'name'=> $ratav['name'],
          ]
        ]);
        // ttes les autres c. associées
        foreach($fav2 as $avant) {
          $rpicom->mergeToRecord($avant['id'], [
            $this->date => [
              'évènement'=> ['changeDeRattachementPour'=> $avant['après'][1]['id']],
              'name'=> $avant['name'],
              'estAssociéeA'=> $ratav['id'],
            ]
          ]);
        }
        break;
      }
      
      case '70': { // Transformation de commune associé en commune déléguée 
        /*
          date: '2020-01-01'
          mvts:
            - avant: { type: COM, id: '52064', name: Bourmont-entre-Meuse-et-Mouzon }
              après: { type: COMD, id: '52224', name: Gonaincourt }
          Je considère que c'est une erreur INSEE.
        */
        break;
      }
      
      default: {
        echo Yaml::dump(['$group'=> $this->asArray()], 3, 2);
        throw new Exception("mod $this->mod non traité ligne ".__LINE__);
      }
    }
    if ($trace->is(['mod'=> $this->mod]))
      $rpicom->showExtractAsYaml(5, 2);
  }
  
  function compte(Criteria $trace): array {
    {/*PhpDoc: methods
    name: compte
    title: "compte(array $comptes, Criteria $trace): array - compte"
    doc: |
    */}
    if ($trace->is(['mod'=> $this->mod]))
      echo Yaml::dump(['$group'=> $this->asArray()], 3, 2);
    $fav2 = $this->factAvant2();
    if ($trace->is(['mod'=> $this->mod]))
      echo Yaml::dump(['$fav2'=> $fav2], 4, 2);
    switch($this->mod) {
      case '21': { // Rétablissement
        /*Cas particulier des 2 ARM créés
          Sinon
            La commune de rattachement est toujours id1 définie dans la 1ère règle
            Le reste de la première règle donne les entités créées
            Les autres règles sont de la forme:
              {idia: [idi]}   <=> la c.a. $idia est rétablie comme c.s.
              {idia: [idia]}  <=> la c.a. $idia reste associée
              {idid: [idi]}   <=> la c.d. $idid est rétablie comme c.s.
            De plus des c. dans la partie droite de la règle 1 indique de nouvelles entités
        */
        if ($fav2[0]['type'] == 'ARM') return [];
        $nbcomSortie = 0;
        foreach ($fav2[0]['après'] as $après) {
          if ($après['type'] == 'COM')
            $nbcomSortie++;
        }
        return [$this->date => ['+'=> $nbcomSortie-1, 'M'=> 1]];
      }
      
      case '20': { // Création
        return [$this->date => ['M'=> count($fav2), '+'=> 1]];
      }
      
      case '30': { // Suppression
        return [$this->date => ['M'=> count($fav2)-1, '-'=> 1]];
      }
      
      case '31': // Fusion simple
      case '33': // Fusion association
      case '32': { // Création de commune nouvelle
        /*La c. nouvelle créée est la seule c. simple à droite, je l'appele idr
          chaque règle est de la forme:
            {idr: [idr]} <=> idr devient commune nouvelle sans c. déléguée propre
            {idr: [idr, idrd]} <=> idr devient commune nouvelle avec c. déléguée propre
            {idi: [idr]} / i<>r <=> idi est absorbée dans idr
            {idi: [idid, idr]} / i<>r <=> idi devient déléguée de idr
            {idia: [idr]} / i<>r <=> est absorbée dans idr
            {idia: [idid, idr]} / i<>r <=> idia devient déléguée de idr
          -> Compter le nbre de COM en entrée
        */
        $nbcomEntrée = 0;
        foreach ($fav2 as $entrée) {
          if ($entrée['type']=='COM') $nbcomEntrée++;
        }
        return [$this->date => ['-'=> $nbcomEntrée-1, 'M'=> 1]];
      }

      case '41': { // Changement de code dû à un changement de département'
        return [$this->date => ['CD'=> 1]];
      }
      
      case '50': { // Changement de code dû à un transfert de chef-lieu
        return [$this->date => ['-'=> 1, '+'=> 1]];
      }
      
      // aucune incidence
      case '10': // Changement de nom
      case '70': // Transformation de commune associé en commune déléguée
      case '34': {// Absorption de certaines c. associées ou déléguées
        return [];
      }
      
      default: {
        echo Yaml::dump(['$group'=> $this->asArray()], 3, 2);
        throw new Exception("mod $this->mod non traité ligne ".__LINE__);
      }
    }
  }
};


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;


require_once __DIR__.'/../../vendor/autoload.php';

// Tests unitaires des classe Verbose et Base
echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>test Base</title></head><body>\n";

if (!isset($_GET['action'])) {
  echo "<a href='?action=TestSwapKeysInArray'> Test de la fonction swapKeysInArray()</a><br>\n";
  echo "<a href='?action=TestCountLeaves'> Test de la fonction countLeaves()</a><br>\n";
  die();
}
