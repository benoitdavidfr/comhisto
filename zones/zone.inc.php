<?php
/*PhpDoc:
name: zone.inc.php
title: zone.inc.php - def. la classe Zone
screens:
doc: |
  La classe Zone a pour objectif de restructurer le référentiel en zones pour faciliter son appariement avec sa géographie.
  Une zone correspond à un surface géographique (MultiPolygon).
  Une même zone correspond à plusieurs versions de Histo.
  L'objectif est:
    1) de construire l'ensemble des zones qui sont les classes d'équivalence pour la relation d'égalité géographique
    2) de structurer ces zones selon un treillis d'inclusion
  Ce treillis n'est pas une forêt car certaines zones changent de rattachement et ont donc plusieurs parents.
  Dans cette logique on adopte les simplifications définies dans ../zones/simplif.inc.php

  Chaque zone est identifiée par la version d'entité la plus ancienne.
  Ces identifiants sont de la forme {statut}{cinsee}@{dateDeCréation} où:
    - {statut} est une des valeurs 's' pour simple ou 'r' pour rattachée
    - {cinsee} est un code INSEE
    - {dateDeCréation} est la date de création de la version sous la forme YYYY-MM-DD

  L'ensemble des zones est construit en déduisant des infos Insee les inclusions et les égalités définis sur les identifiants
  d'entités versionnées. Cette déduction est définie dans histo.inc.php.
  Cette classe permet
    - de stocker les inclusions et égalités entre identifiants
    - puis de construire à partir de ces informations les zones 

journal:
  19/8/2020:
    - modif déf union + ecomp
  12/8/2020:
    - ajout déf union + modif ecomp
  28/6/2020:
    - appariement des zones du COG2020 ok
  25/6/2020:
    - première version
*/

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class Zone {
  static $all=[]; // [ stdId => Zone ] - contient ttes les zones identifiées par leur identifiant standard
  static $sameAs=[]; // [ id => stdId ] - standardisation des Id
  static $includes=[]; // enregistre les couples  (big inclus small) sous la forme [small => [big]]
  static $stats=['COG2020'=>0, 'COG2020ecomp'=>0]; // statistiques
  protected $vids; // liste des ids d'une zone
  protected $ref; // le référentiel dans lequel la zone est définie
  protected $parents; // [Zone] - zones parentes ou []
  protected $children; // [Zone] - liste des zones incluses ou []
  
  // crée une zone, l'enregistre et la retourne, génère une erreur si l'id est déjà utilisé
  static function create(string $id): Zone {
    if (isset(self::$sameAs[$id]))
      throw new Exception("Erreur, l'id $id existe déjà");
    $new = new self($id);
    self::$all[$id] = $new;
    self::$sameAs[$id] = $id;
    return $new;
  }
  
  // retrouve une zone par un de ses id
  static function get(string $id): ?Zone {
    if (!isset(self::$sameAs[$id]))
      return null; // l'id n'existe pas
    $stdId = self::$sameAs[$id];
    if (!isset(self::$all[$stdId]))
      return null; // la zone n'a pas été créée
    return self::$all[$stdId];
  }
  
  // si la zone existe la retourne, sinon la crée
  static function getOrCreate(string $id): Zone {
    if ($zone = self::get($id))
      return $zone;
    else
      return self::create($id);
  }
  
  // affirme que $bigId inclus $smallId, ne crée pas les zones correspondantes
  static function includes(string $bigId, string $smallId): void {
    //echo "<b>includes($bigId, $smallId)</b>\n";
    if (!isset(self::$includes[$smallId]))
      self::$includes[$smallId] = [$bigId];
    elseif (!in_array($bigId, self::$includes[$smallId]))
      self::$includes[$smallId][] = $bigId;
  }
  
  // retourne le meilleur id standardisé entre 2 id sous la forme {chaine}@{date}
  static function betterStdId(string $id1, string $id2): string {
    if (($pos1 = strpos($id1, '@')) === FALSE)
      throw new Exception("format $id1 incorrect");
    $id1Date = substr($id1, $pos1+1);
    //echo "id1Date=$id1Date\n";
    if (($pos2 = strpos($id2, '@')) === FALSE)
      throw new Exception("format $id2 incorrect");
    $id2Date = substr($id2, $pos2+1);
    
    if (strcmp($id1Date, $id2Date) > 0) {
      //echo "betterStdId($id1, $id2) -> $id2\n";
      return $id2;
    }
    elseif (strcmp($id1Date, $id2Date) < 0) {
      //echo "betterStdId($id1, $id2) -> $id1\n";
      return $id1;
    }
    elseif (strcmp($id1, $id2) < 0) { // dates identiques
      //echo "betterStdId($id1, $id2) -> $id1\n";
      return $id1;
    }
    else {
      //echo "betterStdId($id1, $id2) -> $id2\n";
      return $id2;
    }
  }
  
  // affirme que les zones sont identiques, parent et children ne sont pas définis, retourne le nouvel idStd
  static function sameAs(string $id1, string $id2): string {
    //echo "<b>sameAs($id1, $id2)</b>\n";
    if ($id1 == $id2) return $id1;
    if (isset(self::$sameAs[$id1]) && (self::$sameAs[$id1]==$id2)) return $id2;
    if (isset(self::$sameAs[$id2]) && (self::$sameAs[$id2]==$id1)) return $id1;
    if (isset(self::$sameAs[$id1]) && isset(self::$sameAs[$id2]) && (self::$sameAs[$id1]==self::$sameAs[$id2]))
      return self::$sameAs[$id1];
    
    // si $z1 existe, j'utilise son idStd à la place de $id1 et je déréférence la zone
    if ($z1 = self::get($id1)) {
      $id1 = $z1->id();
      unset(Zone::$all[$id1]);
    }
    // si $z2 existe, j'utilise son idStd à la place de $id2 et je déréférence la zone
    if ($z2 = self::get($id2)) {
      $id2 = $z2->id();
      unset(Zone::$all[$id2]);
    }
    // je calcule le nouvel idStd
    $idStd = self::betterStdId($id1, $id2);
    // je crée le résultat
    $z = new self($idStd);
    // je l'enregistre dans Zone::$all
    Zone::$all[$idStd] = $z;
    // je construis la nouvelle liste des vids
    $vids = [ $idStd => 1 ];
    if ($z1) {
      foreach ($z1->vids as $vid)
        $vids[$vid] = 1;
    }
    else {
      $vids[$id1] = 1;
    }
    if ($z2) {
      foreach ($z2->vids as $vid)
        $vids[$vid] = 1;
    }
    else {
      $vids[$id2] = 1;
    }
    $z->vids = array_keys($vids);
    
    // je remplis sameAs
    foreach ($z->vids as $vid) {
      self::$sameAs[$vid] = $idStd;
    }
    //echo 'Zone::$all = '; print_r(self::$all);
    return $idStd;
  }
  
  static function traiteInclusions(): void {
    //echo Yaml::dump(self::allAsArray()); die();
    
    // standardise les clés de self::$includes
    foreach (array_keys(self::$includes) as $small) {
      if ($zSmall = self::get($small)) {
        $stdId = $zSmall->id();
        if ($stdId <> $small) { // je standardise effecivement
          if (in_array($stdId, array_keys(self::$includes))) { // si $smallStdId est déjà présent alors fusion
            self::$includes[$stdId] = array_values(array_unique(array_merge(self::$includes[$stdId], self::$includes[$small])));
          }
          else { // sinon je copie juste
            self::$includes[$stdId] = self::$includes[$small];
          }
          unset(self::$includes[$small]);
        }
      }
    }
    
    // standardise les valeurs de self::$includes
    foreach (self::$includes as $small => $bigs) {
      $stdbigs = [];
      foreach ($bigs as $big) {
        $stdId = ($z = self::get($big)) ? $z->id() : $big;
        if (!in_array($stdId, $stdbigs))
          $stdbigs[] = $stdId;
      }
      self::$includes[$small] = $stdbigs;
    }
    
    // suppression des inclusions aussi présentes de manière transitive
    // a -> b + b -> c + a -> c => delete(a->c)
    // passe de 243 à 55
    foreach (self::$includes as $small => $bigs) {
      if (count($bigs) > 1) {
        foreach ($bigs as $b) {
          foreach ($bigs as $noc => $c) {
            if ($c <> $b) {
              if (in_array($c, self::$includes[$b] ?? [])) { // b -> c
                unset($bigs[$noc]); // delete(a->c)
              }
            }
          }
        }
        self::$includes[$small] = array_values($bigs);
      }
    }
    
    if (isset($_GET['action']) && (($_GET['action']=='showIncludes') || ($_GET['action']=='0testChangeDeRattachementPour'))) {
      ksort(self::$includes);
      echo Yaml::dump(['includes'=> self::$includes]);
      die("Fin showIncludes\n");
    }
    
    if (isset($_GET['action']) && ($_GET['action']=='showMultiinc')) { // affichage des multi-inclusions
      ksort(self::$includes);
      $nbre = 0;
      foreach (self::$includes as $small => $bigs) {
        if (count($bigs) > 1)
          $nbre++;
      }
      echo "$nbre inclusions ayant une cardinalité > 1\n";
    
      foreach (self::$includes as $small => $bigs) {
        if (count($bigs) > 1)
          echo Yaml::dump(['includes'=> [$small => $bigs]]);
      }
      die("Fin Zone::traiteInclusions()\n");
    }

    // transfert de self::$includes vers $children et $parents
    foreach (self::$includes as $small => $bigs) {
      $zsmall = self::getOrCreate($small);
      foreach ($bigs as $big) {
        $zbig = self::getOrCreate($big);
        $zsmall->parents[] = $zbig;
        $zbig->children[] = $zsmall;
      }
    }
    self::$includes = [];
    
    // recherche du référentiel dans lequel la zone est définie
    // Les zones valides sont définies dans COG2020s ou COG2020r
    foreach (self::$all as $id => $zone) {
      if ($idv = $zone->isValid()) {
        $zone->ref = 'COG2020'.substr($idv, 0, 1);
        Stats::incr($zone->ref);
      }
    }
    
    // les unions de zones définies dans COG2020
    foreach (self::$all as $id => $zone) {
      if (!$zone->parents)
        $zone->defCog2020union();
    }

    // les ecomp de zones définies dans COG2020
    foreach (self::$all as $id => $zone)
      $zone->defCog2020ecomp();
    
    ksort(self::$all);
    
    $nbreSansRef = 0;
    foreach (Zone::$all as $id => $zone) {
      if (!$zone->ref) {
        //echo "$id\n";
        foreach ([2019, 2018, 2017, 2016, 2015, 2014, 2013, 2003] as $refyear) {
          foreach ($zone->vids() as $vid) {
            //echo "  vid=$vid\n";
            $v = Histo::get($vid);
            //echo $v;
            if (($v->statut()=='s') && (strcmp($v->dCreation(), "$refyear-01-01") <= 0) && (strcmp($v->dFin(), "$refyear-01-01") > 0)) {
              //echo "$id -> ref $refyear ok\n";
              $zone->ref = "COG$refyear";
              Stats::incr("COG$refyear");
              break 2;
            }
          }
        }
        if (!$zone->ref) {
          //echo "$id -> noref\n";
          $nbreSansRef++;
        }
      }
    }
    Stats::set('nbreSansRef', $nbreSansRef);
    //die("Fin sansref nbreSansRef=$nbreSansRef\n");
  }
  
  // Méthode récursive pour identifier et marquer les COG2020union
  // Est marquée COG2020union une zone non définie dans COG2020(r|s) mais dont tous les enfants sont définies dans COG2020(r|s|union)
  // La méthode est appelée sur une potentielle COG2020union et retourne true ssi elle est définie cad COG2020(r|s|union)
  function defCog2020union(): bool {
    $defined = true;
    foreach ($this->children as $child) {
      if (!$child->defCog2020union())
        $defined = false; // si un enfant est indéfini alors l'union l'est
    }
    if ($this->children && $defined && !$this->ref) // si tous les enfants sont définis et le ref est indéfini alors c'est une union
      $this->ref = 'COG2020union';
    return in_array($this->ref, ['COG2020s','COG2020r','COG2020union']);
  }
  
  // Méthode pour identifier et marquer les COG2020ecomp
  // Est COG2020ecomp une zone non définie dans COG2020 et dont la mère est COG2020(r|s) et toutes les soeurs sont COG2020(r|s|union)
  // La méthode est appelée sur la mère d'une potentielle ecomp
  function defCog2020ecomp(): void {
    if (!in_array($this->ref, ['COG2020s','COG2020r']))
      return;
    $undefs = []; // les enfants non définis dans COG2020
    foreach ($this->children as $child) {
      if (!in_array($child->ref, ['COG2020s','COG2020r','COG2020union']))
        $undefs[] = $child;
    }
    if (count($undefs) == 1)
      $undefs[0]->ref = 'COG2020ecomp';
  }
  
  function id(): string { return $this->vids[0]; }
  function vids(): array { return $this->vids; }
  function ref(): ?string { return $this->ref; }
  
  function isValid(): ?string {
    foreach ($this->vids as $id) {
      if (Histo::get($id)->isValid())
        return $id;
    }
    return null;
  }
  
  // Retourne ttes les zones structurées hiérarchiquement
  static function allAsArray(): array {
    $all = [];
    foreach (self::$all as $zone) {
      if ($zone->parents) continue;
      //if (!$zone->children) continue;
      $id = $zone->id();
      /*if ($zone->isValid())
        $id = '<u>'.$id.'</u>';*/
      $all[$id] = $zone->asArray();
    }
    if (self::$includes)
      $all['includes'] = self::$includes;
    return $all;
  }
  
  // Affiche ttes les zones structurées hiérarchiquement
  static function dumpAll(): void {
    foreach (self::$all as $zone) {
      if ($zone->parents) continue;
      //if (!$zone->children) continue;
      $id = $zone->id();
      /*if ($zone->isValid())
        $id = '<u>'.$id.'</u>';*/
      try {
        echo Yaml::dump([$id => $zone->asArray()], 11, 2);
      } catch (Exception $e) {
          echo 'Exception reçue : ',  $e->getMessage(), "\n";
      }
    }
    if (self::$includes)
      echo Yaml::dump(['includes'=> self::$includes]);
  }
  
  // création à partir d'1 id
  function __construct(string $vid) {
    $this->vids = [ $vid ];
    $this->parents = [];
    $this->children = [];
    $this->ref = null;
  }
  
  function asArray(array $lignee=[]): array {
    //echo "asArray@",$this->id(),"\n";
    //$array = ['vids'=> [], 'children'=>[]];
    $array = [];
    foreach ($this->vids as $no => $id)
      if ($no)
        $array['sameAs'][] = $id;
    if ($this->ref)
      $array['ref'] = $this->ref;
    if (count($this->parents) > 1) {
      foreach ($this->parents as $parent) {
        $array['parents'][] = $parent->id();
      }
    }
    foreach ($this->children as $child) {
      $childId = $child->id();
      /*if ($child->isValid())
        $childId = '<u>'.$childId.'</u>';*/
      /*if (count($child->parents) > 1)
        $childId = '<i>'.$childId.'</i>';*/
      if (in_array($childId, $lignee)) {
        echo "lignee=",Yaml::dump($lignee,0),", childId=$childId\n";
        throw new Exception("Erreur de recursion infinie dans Zone::asArray()");
      }
      if (count($lignee) > 10)
        throw new Exception("count(lignee) > 10");
      $array['contains'][$childId] = $child->asArray(array_merge($lignee, [$childId]));
    }
    return $array; 
  }
};


// Tests unitaires
if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;


echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>test</title></head><body><pre>\n";

if (1) {
  Zone::sameAs('a@2000', 'b@2010'); // cas où aucune des 2 zones n'existe
  Zone::sameAs('a@2000', 'c@2010'); // cas où z1 existe et pas z2
  //Zone::sameAs('c@2010', 'a@2000');
  Zone::sameAs('c@2010', 'd@2000');
  //Zone::sameAs('c@2010', 'x@1943');
  echo 'Zone::$all='; print_r(Zone::$all);
  echo 'Zone::$sameAs='; print_r(Zone::$sameAs);
}


