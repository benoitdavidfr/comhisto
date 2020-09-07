<?php
/*PhpDoc:
name: voronoi.php
title: voronoi.php - définir géométriquement les éléments puis les comhistog3 à partir des éléments
doc: |
  La première phase consiste à construire à partir de l'historique Insee les entités valides et pour chacune les éléments associés
  et à en déduire la géométrie associée à chaque élément.
    a) on part d'histeltd.yaml produit par properat.php que l'on charge dans la structure Histo/Version
    b) on sélectionne pour chaque code Insee sa version valide, s'il y en a une
    c) différents cas de figure
      - la version valide correspond à une COMS sans ERAT alors c'est une entité
      - la version valide correspond à une ERAT alors c'est une entité
      - la version valide correspond à une COMS avec ERAT alors il y a potentiellement 2 entités
        - celle correspondant à une éventuelle commune déléguée propre (ex. r01015)
        - celle correspondant à une éventuelle ECOMP
      J'ai 3 cas d'ECOMP:
        - dans le cas d'une association, le territoire de la commune chef-lieu est une ECOMP (ex c38139)
        - dans le cas d'une C. nouv. sans déléguée propre, le territoire de la C chef-lieu est une ECOMP (ex 11171 / 11080 -> 11080c)
        - dans le cas de la commune nouvelle 33055, la commune d'origine 33338 est absorbées dans la c. nouv. (ex 33338/33055)

  La seconde phase consiste à définir toutes les versions à partir des éléments définis dans la 1ère phase.

  A faire:
    - transformer les géométries en MultiPolygon
    - ajouter à eadming3 le champ statut
journal: |
  6/9/2020:
    - modification en amont des elts pour en faire des elts propres, cad hors ERAT et ajout du champ eltsNonDélégués pour 33055
    - adaptation du code
    - exécution de 11:00, qqs erreurs
      - manque 56173c
    - exécution le 2020-09-06T20:07:37+00:00
      - des erreurs qui semblent géométriques
        - 22203 - l'erreur provient d'eadming3
        - 54568 - l'erreur provient d'eadming3
        - 57603 - l'erreur provient d'eadming3
        - ...
  2/9/2020:
    - ajout chefs-lieux manquants
    - exécution sur la totalité
    - erreur
      Query failed: ERROR:  duplicate key value violates unique constraint "elt_pkey"
      DETAIL:  Key (cinsee)=(52018) already exists.
      Erreur Sql ligne 422
  31/8/2020:
    - génération comhistog3 partiel
  30/8/2020:
    - 10:41 testEntites ok
      cela signfie que les entités des CEntElts créés correspondent aux entités décrites dans COG2020
    - 13:46 - réciproquement chaque entité décrite dans COG2020 correspond à un CEntElts
    - 15:16 - semble fonctionner - bloqué sur chefs-lieux identiques
    - 18:00 - semble fonctionner - bloqué sur chefs-lieux identiques
  29/8/2020:
    - création
*/
ini_set('memory_limit', '1G');
set_time_limit(2*60);

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../../../../phplib/pgsql.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class PgSqlSA extends PgSql { // Extension de PgSql pour simplifier l'appel des fonctions d'analyse spatiale
  // geometry ST_VoronoiPolygons( g1 geometry , tolerance float8 , extend_to geometry );
  // $mPoints est un objet GeoJSON MultiPoint
  // $extendTo est une sous-requête SQL définssant l'extension
  // retourne une GeometryCollection de (Multi)Polygons
  static function voronoiPolygons(array $mPoints, float $tolerance, string $extendTo) {
    $sql = 'select ST_AsGeoJSON(ST_VoronoiPolygons('
      ."ST_GeomFromGeoJSON('".json_encode($mPoints)."'),"
      .'0,'
      .'('.$extendTo.')'
    ."))";
    //echo "sqlDansBuildVoronoi: $sql\n";
    $tuple = self::getTuples($sql)[0]; // je sais que le résultat contient un n-uplet
    return json_decode($tuple['st_asgeojson'], true);
  }
};

if (php_sapi_name() <> 'cli') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>voronoi</title></head><body><pre>\n";
  if (!isset($_GET['action'])) {
    echo "</pre><a href='?action=testEntites'>test les entités</a><br>\n";
    echo "<a href='?action=voronoi'>génère les Voronoi</a><br>\n";
    die();
  }
}
else {
  $_GET['action'] = 'prod';
}
echo "-- Début à ",date(DATE_ATOM),"\n";

class Params {
  const GEN_ELTS = false; // si true on génère les élts dans la table elt, sinon on n'y touche pas
};
if (!Params::GEN_ELTS)
  echo "Attention: Les élts ne sont pas générés\n";

// stockage des chefs-lieux provenant de Wikipedia ou saisis dans le Géoportail
class ChefLieu {
  static $all = []; // [iddept => [id => ['names'=> [name], 'geo'=>[lon, lat]]]]
  
  // chargement initial à parir des 2 fichiers Yaml
  static function load(string $dir) {
    self::$all = Yaml::parsefile("$dir/cheflieuwp.yaml");
    foreach (Yaml::parsefile("$dir/cheflieugp.yaml") as $dept => $chefslieuxdept) {
      if ($dept <> 'title')
        foreach ($chefslieuxdept as $id => $cheflieu)
          self::$all[$dept][$id] = $cheflieu;
    }
  }
  
  // recherche de coordonnées géo du chef-lieu à partir du no de département et du nom
  static function chercheGeo(string $cinsee, string $nom): array {
    $dept = substr($cinsee, 0, 2);
    if (!isset(self::$all["d$dept"])) {
      throw new Exception ("Com $nom ($cinsee) Dept $dept non défini");
    }
    foreach (self::$all["d$dept"] as $cheflieu) {
      if (in_array($nom, $cheflieu['names'])) {
        if (isset($cheflieu['geo']))
          return $cheflieu['geo'];
        else {
          throw new Exception ("Chef-lieu $nom ($cinsee) trouvé SANS geo");
        }
      }
    }
    throw new Exception ("Chef-lieu $nom ($cinsee) NON trouvé");
  }
};
ChefLieu::load(__DIR__.'/../cheflieu');
//print_r(ChefLieu::$all);

class EltSet { // Ensemble d'éléments
  protected $set; // [eelt => 1]
  
  function __construct(array $elts) { // création à partir d'une liste de chaines de codes Insee
    $this->set = [];
    foreach ($elts as $elt)
      $this->set["e$elt"] = 1;
    ksort($this->set);
  }
  
  function __toString(): string { return implode('+', array_keys($this->set)); }
  
  /*function diff(self $b): self { // $this - b
    $result = clone $this;
    foreach (array_keys($b->set) as $elt)
      unset($result->set[$elt]);
    return $result;
  }*/
  
  //function empty(): bool { return ($this->set==[]); }
  
  // nbre d'éléments dans l'ensemble
  function count(): int { return count($this->set); }
  
  function elts(): array {
    $elts = [];
    foreach (array_keys($this->set) as $eelt)
      $elts[] = substr($eelt, 1);
    return $elts;
  }

  function ajout(self $b): void { // $this += $b 
    $this->set = array_merge($this->set, $b->set);
    ksort($this->set);
  }
};

// Historique des codes Insee
class Histo {
  static $all=[];
  protected $cinsee;
  protected $versions;
  protected $vvalide; // ?Version - la vesion valide ou null si le code est périmé
  
  static function load(string $fpath) {
    $yaml = Yaml::parseFile($fpath);
    //print_r($yaml);
    foreach ($yaml['contents'] as $cinsee => $histo) {
      self::$all[$cinsee] = new Histo($cinsee, $histo);
    }
    //print_r(self::$all);
  }
    
  function __construct(string $cinsee, array $histo) {
    $this->cinsee = $cinsee;
    $vprec = null;
    foreach ($histo as $dv => $version) {
      if ($vprec)
        $vprec->setFin($dv);
      $this->versions[$dv] = new Version($cinsee, $dv, $version);
      $vprec = $this->versions[$dv];
    }
    
    $this->vvalide = array_values($this->versions)[count($this->versions)-1];
    if (!$this->vvalide->etat()) // ne correspond pas réellement à une version mais à un évt de suppression
      $this->vvalide = null;
    elseif ($this->vvalide->fin()) // si la date de fin est définie alors le code est périmé/abrogé
      $this->vvalide = null;
  }
  
  static function get(string $cinsee): self {
    if (isset(self::$all[$cinsee]))
      return self::$all[$cinsee];
    else
      throw new Exception("aucun Histo ne correspond à $cinsee");
  }
  
  static function getVersion(string $id): Version {
    $type = substr($id, 0, 1);
    $cinsee = substr($id, 1, 5);
    $dv0 = substr($id, 7);
    if (!isset(self::$all[$cinsee]))
      throw new Exception("aucun Histo ne correspond à $id");
    $histo = self::$all[$cinsee];
    if (isset($histo->versions[$dv0])) {
      return $histo->versions[$dv0];
    }
    else {
      foreach ($histo->versions as $dv => $version) {
        if (($dv <= $dv0) && (!$version->fin() || ($dv0 < $version->fin())))
          return $version;
      }
    }
    throw new Exception("aucune Version ne correspond à $id");
  }
  
  function asArray(): array {
    $array = [];
    foreach ($this->versions as $dv => $version)
      $array[$dv] = $version->asArray();
    return $array;
  }

  static function allAsArray(): array {
    $array = [];
    foreach (self::$all as $cinsee => $histo) {
      $array[$cinsee] = $histo->asArray();
    }
    return $array;
  }

  function vvalide(): ?Version { // retourne la version valide si elle existe, null sinon
    return $this->vvalide;
  }

  // retourne les coord. [lon,lat] du chef-lieu
  function chefLieu(): array {
    $cinsee = $this->cinsee;
    $sql = "select ST_AsGeoJSON(wkb_geometry) from chef_lieu_carto where insee_com='$cinsee'";
    //echo "$sql\n";
    $tuples = PgSql::getTuples($sql);
    if (count($tuples) > 0) {
      $geojson = json_decode($tuples[0]['st_asgeojson'], true);
      return $geojson['coordinates'];
    }
    $names = []; // [ nom => 1 ]
    foreach ($this->versions as $dv => $version) {
      if ($name = $version->etat()['name'] ?? null)
        $names[$name] = 1;
    }
    foreach (array_keys($names) as $name) {
      try {
        return ChefLieu::chercheGeo($this->cinsee, $name);
      }
      catch (Exception $e) {}
    }
    if ($cinsee = $this->changeDeCodePour()) {
      return Histo::get($cinsee)->chefLieu();
    }
    throw new Exception("coord. non trouvées pour $this->cinsee, ".implode(',', array_keys($names)));
  }
  
  function changeDeCodePour(): ?string { // retourne le nouveau code ou null s'il n'y a pas de chgt de code
    $derniereVersion = array_values($this->versions)[count($this->versions)-1];
    return $derniereVersion->evts()['changeDeCodePour'] ?? null;
  }
  
  function insertComhisto(): void { // insertion des versions dans la table comhisto
    foreach ($this->versions as $version)
      $version->insertComhisto();
  }
};

// Version d'un Historique
class Version {
  protected $cinsee;
  protected $debut;
  protected $fin;
  protected $evts;
  protected $etat;
  protected $erat; // [ ('aPourDéléguées'|'aPourAssociées'|'aPourArdm') => [{codeInsee}]]
  protected $eltSet; // ?EltSet - elts positifs et propres, cad hors ERAT
  protected $eltSetND; // ?EltSet - dans le cas de 33055, elts non délégués
  
  function __construct(string $cinsee, string $debut, array $version) {
    $this->cinsee = $cinsee;
    $this->debut = $debut;
    $this->fin = null;
    $this->evts = $version['evts'] ?? [];
    $this->etat = $version['etat'] ?? [];
    $this->erat = $version['erat'] ?? [];
    $this->eltSet = isset($version['eltsp']) ? new EltSet($version['eltsp']) : null;
    $this->eltSetND = isset($version['eltsNonDélégués']) ? new EltSet($version['eltsNonDélégués']) : null;
    //print_r($version);
  }
  
  function setFin(string $fin) { $this->fin = $fin; }
  function type(): string { return in_array($this->etat['statut'], ['COMA', 'COMD', 'ARDM']) ? 'r' : 's'; }
  function cinsee(): string { return $this->cinsee; }
  function statut(): string { return $this->etat['statut']; }
  function etat(): array { return $this->etat; }
  
  function erats(): array { // [ Version ]
    $erats = [];
    foreach ($this->erat as $listeCodesInsee) {
      //print_r($listeCodesInsee);
      foreach ($listeCodesInsee as $codeInsee) {
        $erats[] = Histo::getVersion("r$codeInsee@$this->debut");
      }
    }
    return $erats;
  }
  
  function evts(): array { return $this->evts; }
  function eltSet(): ?EltSet { return $this->eltSet; }
  function eltSetND(): ?EltSet { return $this->eltSetND; }
  function debut(): string { return $this->debut; }
  function fin(): ?string { return $this->fin; }
  
  function name(string $type): ?string {
    if (!$this->etat)
      return '';
    elseif (isset($this->etat['nomCommeDéléguée']) && ($type=='r'))
      return $this->etat['nomCommeDéléguée'];
    else
      return $this->etat['name'];
  }
  
  function asArray(): array {
    return array_merge(
      ['debut'=> $this->debut],
      $this->fin ? ['fin'=> $this->fin] : [],
      $this->evts ? ['evts'=> $this->evts] : [],
      $this->etat ? ['etat'=> $this->etat] : [],
      $this->erat ? ['erat'=> $this->erat] : [],
      $this->eltSet ? ['eltsp'=> $this->eltSet->__toString()] : [],
      $this->eltSetND ? ['eltsNonDélégués'=> $this->eltSetND->__toString()] : []
    );
  }
  
  function estAssociation(): bool { return (array_keys($this->erat) == ['aPourAssociées']); }
  function estCNouvelle(): bool { return (array_keys($this->erat) == ['aPourDéléguées']); }
  function estCAvecARDM(): bool { return (array_keys($this->erat) == ['aPourArdm']); }
  function existeDelegueePropre(): bool { return in_array($this->cinsee, $this->erat['aPourDéléguées']); }
  
  function eltSetAvecErat(): EltSet {
    $eltSetAvecErat = clone $this->eltSet;
    foreach ($this->erats() as $erat) {
      $eltSetAvecErat->ajout($erat->eltSet);
    }
    return $eltSetAvecErat;
  }
  
  function insertComhisto(): void {
    // Voir la génération des déléguées propres
    if (!$this->etat) return;
    $elts = $this->eltSetAvecErat()->elts();
    if (count($elts) == 1) {
      $elt = $elts[0];
      $geomsql = "geom from elt where cinsee='$elt'";
    }
    else {
      $geomsql = "ST_Union(geom) from elt where cinsee in ('".implode("','", $elts)."')";
    }
    $type = $this->type();
    $cinsee = $this->cinsee;
    $debut = $this->debut;
    $fin = $this->fin ? "'".$this->fin."'" : 'null';
    $crat = isset($this->etat['crat']) ? "'".$this->etat['crat']."'" : 'null';
    $dnom = str_replace("'", "''", $this->etat['name']);
    $sql = "insert into comhistog3(type, cinsee, debut, fin, crat, dnom, geom)\n"
      ."select '$type', '$cinsee', '$debut', $fin, $crat, '$dnom', $geomsql";
    //echo "sql=$sql\n";
    try {
      if (($affrows = PgSql::query($sql)->affected_rows()) <> 1) {
        echo "Erreur sur affected_rows=$affrows, sql=$sql\n";
        //die("Erreur affected_rows\n");
      }
    }
    catch (Exception $e) {
      echo $e->getMessage(),"\n";
      echo "sql=$sql\n";
      die("Erreur Sql\n");
    }
  }
};

Histo::load('histeltd.yaml');
//echo Yaml::dump(Histo::allAsArray(), 3, 2);

class CEntElts { // couple (entité (coms, erat, ecomp) définie dans COG2020, éléments correspondants)
  const ONLY_SHOW_SQL = false; // true <=> les reqêtes SQL de création d'elts sont affichées mais pas exécutées
  protected $ent; // entité (coms, erat, ecomp) définie dans COG2020 identifiée par le type et le code Insee
  protected $eltSet; // ensemble d'élts, sous la forme d'un EltSet
  
  function __construct(string $ent, EltSet $eltSet) {
    $this->ent = $ent;
    $this->eltSet = $eltSet;
  }
  
  function asArray(): array {
    return ['ent'=> $this->ent, 'eltSet'=> $this->eltSet->__toString() ];
  }
  
  function testEntite(array &$entites) {
    if (!isset($entites[$this->ent]))
      echo Yaml::dump(['CEntElts KO'=> $this->asArray()]);
    /*else
      echo "$this->ent ok\n";*/
    else
      unset($entites[$this->ent]);
  }
  
  static function createTable(): void {
    if (self::ONLY_SHOW_SQL) return;
    PgSql::query("drop table if exists elt");
    PgSql::query("create table elt(
      cinsee char(5) not null primary key, -- code Insee
      geom geometry -- géométrie Polygon|MultiPolygon 4326
    )");
    $date_atom = date(DATE_ATOM);
    PgSql::query("comment on table elt is 'couche des éléments générée le $date_atom'");
  }
  
  // enregistre les éléments dans la table des éléments
  function storeElts(): void {
    $eid = $this->ent;
    if ($this->eltSet->count() == 1) {
      $elt = $this->eltSet->elts()[0];
      $sql = "insert into elt(cinsee, geom) select '$elt', geom from eadming3 where eid='$eid'";
      try {
        if (self::ONLY_SHOW_SQL)
          echo "sql=$sql\n";
        else
          PgSql::query($sql);
      }
      catch (Exception $e) {
        echo $e->getMessage(),"\n";
        echo "sql=$sql\n";
        die("Erreur Sql ligne ".__LINE__."\n");
      }
    }
    else {
      $eltMPoints = $this->eltMPoints();
      $voronoiPolygons = PgSqlSA::voronoiPolygons($eltMPoints, 0, "select geom from eadming3 where eid='$eid'");
      if (count($voronoiPolygons['geometries']) <> count($eltMPoints['coordinates'])) {
        $yaml = [
          '$this->eltSet->elts()'=> [],
          '$cEntElt'=> $this->asArray(),
          '$eltMPoints'=> $eltMPoints,
        ];
        foreach ($this->eltSet->elts() as $elt) {
          $yaml['$this->eltSet->elts()'][$elt] = Histo::get($elt)->asArray();
        }
        echo Yaml::dump($yaml, 4, 2);
        die("Erreur storeElts() sur $eid, nbre incorrect de polygones\n");
      }
      $elts = $this->eltSet->elts();
      foreach ($voronoiPolygons['geometries'] as $no => $voronoiPolygon) {
        $elt = $elts[$no];
        $sql = "insert into elt(cinsee, geom) "
          ."select '$elt', ST_Intersection("
          ."  ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($voronoiPolygon)."'), 4326),\n"
          ."  (select geom from eadming3 where eid='$eid')\n"
          .")";
        try {
          if (self::ONLY_SHOW_SQL)
            echo "sql=$sql\n";
          else
            PgSql::query($sql);
        }
        catch (Exception $e) {
          echo $e->getMessage(),"\n";
          echo "sql=$sql\n";
          die("Erreur Sql ligne ".__LINE__."\n");
        }
      }
    }
  }
  
  function eltMPoints(): array { // Retourne un MultiPoint GeoJSON avec un point par élément
    $points = [];
    foreach ($this->eltSet->elts() as $elt) {
      $points[] = Histo::get($elt)->chefLieu();
    }
    return ['type'=> 'MultiPoint', 'coordinates'=> $points];
  }
};

PgSql::open('host=172.17.0.4 dbname=gis user=docker password=docker');

if ($_GET['action']=='testEntites') {
  $sql = "select eid from eadming3";
  foreach (PgSql::query($sql) as $tuple) {
    $entites[$tuple['eid']] = 1;
  }
}
elseif (Params::GEN_ELTS) {
  CEntElts::createTable();
}
//print_r($entites);

// Phase 1 - création des éléments dans la table elt
if (Params::GEN_ELTS) {
  foreach (Histo::$all as $cinsee => $histo) {
    //if (substr($cinsee, 0, 1) >= 4) break;
    //if (substr($cinsee, 0, 1) < 8) continue;
    $cEntElts = [];
    if (!($vvalide = $histo->vvalide())) {
      //echo "$cinsee non valide\n";
      continue;
    }
    //echo Yaml::dump([$cinsee => ['histo'=> $histo->asArray(), 'v2020'=> $v2020->asArray()]], 4, 2);
    /* Algo:
    - je construis les objets CEntElts = couple (entité (coms, erat, ecomp) définie dans COG2020, éléments correspondants)
      - si vvalide est un COMS sans ERAT alors (coms, elts)
      - si vvalide est un ERAT  alors (erat, elts)
      - si vvalide est un COMS avec ERAT alors il y a potentiellement 2 entités
        - celle correspondant à une éventuelle commune déléguée propre (ex. r01015)
        - celle correspondant à une éventuelle ECOMP avec 3 cas d'ECOMP:
          - dans le cas d'une association, le territoire de la commune chef-lieu est une ECOMP (ex c38139)
          - dans le cas d'une C. nouv. sans déléguée propre, le territoire de la C chef-lieu est une ECOMP (ex 11171 / 11080 -> 11080c)
          - dans le cas de la commune nouvelle 33055, la commune d'origine 33338 est absorbées dans la c. nouv. (ex 33338/33055)
    */
    elseif (in_array($vvalide->statut(), ['COMD','COMA','ARDM'])) { // ERAT
      $cEntElts[] = new CEntElts("r$cinsee", $vvalide->eltSet());
    }
    elseif (!($erats = $vvalide->erats())) { // COMS sans ERAT
      $cEntElts[] = new CEntElts("s$cinsee", $vvalide->eltSet());
    }
    // COM avec ERAT
    elseif ($vvalide->estCAvecARDM()) { // dans les cas de C. avec ARDM on ne fait rien
      continue;
    }
    elseif ($vvalide->estAssociation()) { // dans le cas d'une association, le territoire de la commune chef-lieu est une ECOMP
      $cEntElts[] = new CEntElts("c$cinsee", $vvalide->eltSet());
    }
    elseif ($vvalide->eltSetND()) { // dans le cas de la C nouvelle 33055, la c d'origine 33338 est absorbées dans la c. nouv.
      $cEntElts[] = new CEntElts("c$cinsee", $vvalide->eltSetND());
      $cEntElts[] = new CEntElts("r$cinsee", $vvalide->eltSet());
    }
    elseif ($vvalide->estCNouvelle()) { // dans les autres cas de C. nouv., déléguée propre ou non
      if ($vvalide->existeDelegueePropre()) // s'il existe une delegue propre
        $cEntElts[] = new CEntElts("r$cinsee", $vvalide->eltSet());
      else // sinon
        $cEntElts[] = new CEntElts("c$cinsee", $vvalide->eltSet());
    }
    else {
      echo "Cas non traité pour $cinsee\n";
    }
    if (!$cEntElts) {
      if ($_GET['action']=='testEntites')
        echo "Aucun cEntElt pour $cinsee\n";
    }
    else {
      foreach ($cEntElts as $cEntElt) {
        //echo '<b>',Yaml::dump(['$cEntElt'=> $cEntElt->asArray()]),"</b>\n";
        if ($_GET['action']=='testEntites') {
          // teste si chaque entité identifiée par ce process existe bien dans COG2020 et vice-versa
          $cEntElt->testEntite($entites);
        }
        else {
          $cEntElt->storeElts();
        }
      }
    }
  }
}
if ($_GET['action']=='testEntites') {
  echo Yaml::dump(['$entites'=> $entites]);
}

//die("-- Fin ok phase elts à ".date(DATE_ATOM)."\n");

// Phase 2 - 
PgSql::query("drop table if exists comhistog3");
PgSql::query("create table comhistog3(
  type char(1) not null, -- 's' ou 'r'
  cinsee char(5) not null, -- code Insee
  debut char(10) not null, -- date de création de la version dans format YYYY-MM-DD
  fin char(10), -- date du lendemain de la fin de la version dans format YYYY-MM-DD, ou null ssi version valide à la date de référence
  crat char(5), -- pour une entité rattachée code Insee de la commune de rattachement, sinon null
  dnom varchar(256), -- dernier nom
  geom geometry, -- géométrie
  primary key (type, cinsee, debut) -- la clé est composée du type, du code Insee et de la date de création
)");
$date_atom = date(DATE_ATOM);
PgSql::query("comment on table comhistog3 is 'couche du référentiel générée le $date_atom'");

foreach (Histo::$all as $cinsee => $histo) {
  //if (substr($cinsee, 0, 1) >= 4) break;
  $histo->insertComhisto();
}

die("-- Fin ok à ".date(DATE_ATOM)."\n");
