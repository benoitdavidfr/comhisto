<?php
/*PhpDoc:
name: voronoi.php
title: voronoi.php - définir géométriquement les éléments définis dans ../elts par l'algorithme de Voronoi
doc: |
journal: |
  30/8/2020:
    - 10:41 testEntites ok
      cela signfie que les entités des CEntElts créés correspondent aux entités décrites dans COG2020
    - 13:46 - réciproquement chaque entité décrite dans COG2020 correspond à un CEntElts
    - 15:16 - semble fonctionner - bloqué sur chefs-lieux identiques
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

class Wikipedia {
  static $coms = []; // [dept => [url => ['name='> name, 'geo'=>[lon, lat]]]]
  
  static function load(string $dir) {
    self::$coms = Yaml::parse(file_get_contents("$dir/comgeos.yaml"));
    $coms2 = Yaml::parse(file_get_contents("$dir/comgeos2.yaml"));
    foreach ($coms2 as $dept => $comsdept) {
      if ($dept <> 'title')
        foreach ($comsdept as $idcom => $com)
          self::$coms[$dept][$idcom] = $com;
    }
  }
  
  static function chercheGeo(string $cinsee, string $nom): array {
    $dept = substr($cinsee, 0, 2);
    if (!isset(self::$coms["d$dept"])) {
      throw new Exception ("Com $nom ($cinsee) Dept $dept non défini");
    }
    foreach (self::$coms["d$dept"] as $com) {
      if (in_array($nom, $com['names'])) {
        if (isset($com['geo']))
          return $com['geo'];
        else {
          throw new Exception ("Com $nom ($cinsee) trouvée SANS geo");
        }
      }
    }
    throw new Exception ("Com $nom ($cinsee) NON trouvée");
  }
};
Wikipedia::load(__DIR__.'/../join/wikipedia');
//print_r(Wikipedia::$coms);

class EltSet { // Ensemble d'éléments
  protected $set; // [eelt => 1]
  
  function __construct(array $elts) { // création à partir d'une liste de chaines de codes Insee
    $this->set = [];
    foreach ($elts as $elt)
      $this->set["e$elt"] = 1;
    ksort($this->set);
  }
  
  function __toString(): string { return implode('+', array_keys($this->set)); }
  
  function diff(self $b): self { // $this - b
    $result = clone $this;
    foreach (array_keys($b->set) as $elt)
      unset($result->set[$elt]);
    return $result;
  }
  
  function empty(): bool { return ($this->set==[]); }
  
  // nbre d'éléments dans l'ensemble
  function count(): int { return count($this->set); }
  
  function elts(): array {
    $elts = [];
    foreach (array_keys($this->set) as $eelt)
      $elts[] = substr($eelt, 1);
    return $elts;
  }
};

// Historique des codes Insee
class Histo {
  static $all=[];
  protected $cinsee;
  protected $versions;
  protected $v2020;
  
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
    
    $this->v2020 = array_values($this->versions)[count($this->versions)-1];
    if (!$this->v2020->etat()) // ne correspond pas réellement à une version mais à un évt de suppression
      $this->v2020 = null;
    elseif ($this->v2020->fin()) // la fin 
      $this->v2020 = null;
  }
  
  static function get(string $cinsee): self { return self::$all[$cinsee]; }
  
  static function getVersion(string $id): Version {
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

  function v2020(): ?Version { // retourne la version 2020 si elle est valide, null sinon
    return $this->v2020;
  }

  // retourne les coord. [lon,lat] du chef-lieu
  function chefLieu(): array {
    $cinsee = $this->cinsee;
    $sql = "select ST_AsGeoJSON(wkb_geometry) from chef_lieu_carto where insee_com='$cinsee'";
    //echo "$sql\n";
    $tuples = PgSql::getTuples($sql);
    if (count($tuples)==1) {
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
        return Wikipedia::chercheGeo($this->cinsee, $name);
      }
      catch (Exception $e) {}
    }
    if ($cinsee = $this->changeDeCodePour()) {
      return Histo::get($cinsee)->chefLieu();
    }
    throw new Exception("coord. non trouvées pour $this->cinsee, ".implode(',', array_keys($names)));
  }
};

// Version d'un Historique
class Version {
  protected $cinsee;
  protected $debut;
  protected $fin;
  protected $evts;
  protected $etat;
  protected $erat; // [ ('aPourDéléguées'|'aPourAssociées') => [{coddeInsee}]]
  protected $eltSet; // EltSet ou null
  protected $eltSetCD; // commeDéléguée EltSet ou null
  
  function __construct(string $cinsee, string $debut, array $version) {
    $this->cinsee = $cinsee;
    $this->debut = $debut;
    $this->fin = null;
    $this->evts = $version['evts'] ?? [];
    $this->etat = $version['etat'] ?? [];
    $this->erat = $version['erat'] ?? [];
    $this->eltSet = isset($version['eltsp']) ? new EltSet($version['eltsp']) : null;
    $this->eltSetCD = isset($version['eltsCommeDéléguée']) ? new EltSet($version['eltsCommeDéléguée']) : null;
    //print_r($version);
  }
  
  function setFin(string $fin) { $this->fin = $fin; }
  function type(): string { return in_array($this->etat['statut'], ['COMA', 'COMD', 'ARDM']) ? 'r' : 's'; }
  function cinsee(): string { return $this->cinsee; }
  function statut(): string { return $this->etat['statut']; }
  function etat(): array { return $this->etat; }
  
  function erats(): array { // [ Version ]
    $erats = [];
    foreach (array_values($this->erat) as $listeCodesInsee) {
      //print_r($listeCodesInsee);
      foreach ($listeCodesInsee as $codeInsee) {
        $erats[] = Histo::getVersion("r$codeInsee@$this->debut");
      }
    }
    return $erats;
  }
  
  function evts(): array { return $this->evts; }
  function eltSet(): ?EltSet { return $this->eltSet; }
  function eltSetErat(): ?EltSet { return $this->eltSetCD ?? $this->eltSet; }
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
    return [
      'debut'=> $this->debut,
      'fin'=> $this->fin,
      'evts'=> $this->evts,
      'etat'=> $this->etat,
      'erat'=> $this->erat,
      'elts'=> $this->eltSet ? $this->eltSet->__toString() : null,
      'eltSetCD'=> $this->eltSetCD ? $this->eltSetCD->__toString() : '',
    ];
  }
};

Histo::load('histeltd.yaml');
//echo Yaml::dump(Histo::allAsArray(), 3, 2);

class CEntElts { // couple (entité (coms, erat, ecomp) définie dans COG2020, éléments correspondants)
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
  
  // enregistre les éléments dans la table des éléments
  function storeElts(): void {
    $eid = $this->ent;
    if ($this->eltSet->count() == 1) {
      $elt = $this->eltSet->elts()[0];
      $sql = "insert into elt(cinsee, geom) select '$elt', geom from eadming3 where eid='$eid'";
      echo "sql=$sql\n";
      PgSql::query($sql);
    }
    else {
      $eltMPoints = $this->eltMPoints();
      echo Yaml::dump(['$eltMPoints'=> $eltMPoints]);
      $voronoiPolygons = PgSqlSA::voronoiPolygons($eltMPoints, 0, "select geom from eadming3 where eid='$eid'");
      if (count($voronoiPolygons['geometries']) <> count($eltMPoints['coordinates'])) {
        //echo Yaml::dump(['$undefPoints'=> $undefPoints]);
        //throw new Exception("Erreur buildVoronoi() sur $this->defId, nbre de polygones incorrect");
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
      }
      echo "sql=$sql\n";
      PgSql::query($sql);
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
else {
  PgSql::query("drop table if exists elt");
  PgSql::query("create table elt(
    cinsee char(5) not null primary key, -- code Insee
    geom geometry -- géométrie Polygon|MultiPolygon 4326
  )");
  $date_atom = date(DATE_ATOM);
  PgSql::query("comment on table elt is 'couche des éléments générée le $date_atom'");
}
//print_r($entites);

foreach (Histo::$all as $cinsee => $histo) {
  if (!($v2020 = $histo->v2020())) {
    //echo "$cinsee non valide\n";
    continue;
  }
  //echo Yaml::dump([$cinsee => ['histo'=> $histo->asArray(), 'v2020'=> $v2020->asArray()]], 4, 2);
  /* Algo:
  - je construis les objets CEntElts = couple (entité (coms, erat, ecomp) définie dans COG2020, éléments correspondants)
  - si v2020 est un COMS sans ERAT alors (coms, elts)
  - si v2020 est un ERAT  alors (erat, elts)
  - si v2020 est un COMS avec ERAT alors (ccoms, elts - ceux des ERAT)
  */
  $cEntElts = [];
  if (($v2020->statut()=='COMS') && !$v2020->erats()) {
    $cEntElts[] = new CEntElts("s$cinsee", $v2020->eltSet());
  }
  elseif ($v2020->statut()=='COMS') { // COMS avec ERAT
    $eltSet = $v2020->eltSet();
    foreach ($v2020->erats() as $erat) {
      $eltSet = $eltSet->diff($erat->eltSet());
    }
    if (!$eltSet->empty())
      $cEntElts[] = new CEntElts("c$cinsee", $eltSet);
  }
  elseif ($v2020->statut()=='COMM') { // COMM avec ERAT
    $eltSet = $v2020->eltSet();
    foreach ($v2020->erats() as $erat) {
      $eltSet = $eltSet->diff($erat->eltSetErat());
    }
    if (!$eltSet->empty())
      $cEntElts[] = new CEntElts("c$cinsee", $eltSet);
    // + l'entité rattachée propre
    $cEntElts[] = new CEntElts("r$cinsee", $v2020->eltSetErat());
  }
  elseif (in_array($v2020->statut(), ['COMD','COMA','ARDM'])) {
    $cEntElts[] = new CEntElts("r$cinsee", $v2020->eltSet());
  }
  else {
    echo "cas non traité pour $cinsee\n";
  }
  if (!$cEntElts) {
    if ($_GET['action']=='testEntites')
      echo "Aucun cEntElt pour $cinsee\n";
  }
  else {
    foreach ($cEntElts as $cEntElt) {
      echo '<b>',Yaml::dump(['$cEntElt'=> $cEntElt->asArray()]),"</b>\n";
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
if ($_GET['action']=='testEntites') {
  echo Yaml::dump(['$entites'=> $entites]);
}
die("Fin ok\n");