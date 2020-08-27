<?php
/*PhpDoc:
name: voronoi.php
title: voronoi.php - 
screens:
doc: |
  Complète la table comhistog3 avec les zones non définies dans COG2020 mais construite par l'algo de Voronoi
  
journal: |
  26-27/8/2020:
    - nombreux points identiques, modifs à la main de comgeos.yaml et comgeos2.yaml
    - arrêt sur s33340@1943-01-01, s33430@1943-01-01, s33506@1943-01-01 pour stocker les Voroni créés
    - import dans PgSql des Voronoi, erreur sur une géométrie nulle
  25/8/2020:
    - création
*/
ini_set('memory_limit', '1G');

require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (php_sapi_name() <> 'cli') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>voronoi</title></head><body><pre>\n";
  if (!isset($_GET['action'])) {
    echo "</pre><a href='?action=testGeo'>testGeo</a><br>\n";
    echo "<a href='?action=voronoi'>voronoi</a><br>\n";
    die();
  }
}
else {
  $_GET['action'] = 'prod';
}

if (1) { // exécute les ordres Sql
  require_once __DIR__.'/../../../../phplib/pgsql.inc.php';
}
else { // sinon les affiche seulement
  class PgSql {
    static function open(string $connection_string): void {}
    static function query(string $sql) { echo "$sql\n"; return new PgSql; }
    function affected_rows(): int { return 1; }
  };
}

class Wikipedia {
  static $coms = []; // [dept => [url => ['name='> name, 'geo'=>[lon, lat]]]]
  
  static function init() {
    self::$coms = Yaml::parse(file_get_contents(__DIR__.'/wikipedia/comgeos.yaml'));
    $coms2 = Yaml::parse(file_get_contents(__DIR__.'/wikipedia/comgeos2.yaml'));
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
Wikipedia::init();
//print_r(Wikipedia::$coms);

// Historique des codes Insee
class Histo {
  static $all;
  protected $cinsee;
  protected $versions;
  
  static function load(string $fpath) {
    $yaml = Yaml::parseFile($fpath);
    //print_r($yaml);
    foreach ($yaml['contents'] as $cinsee => $histo) {
      self::$all[$cinsee] = new Histo($cinsee, $histo);
    }
    //print_r(self::$all);
  }
  
  static function get(string $cinsee): self { return self::$all[$cinsee]; }
  
  static function getVersion(string $id): Version {
    $cinsee = substr($id, 1, 5);
    $dv = substr($id, 7);
    return self::$all[$cinsee]->versions[$dv];
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
  }
  
  // retourne les coord. [lon,lat] du chef-lieu
  function chefLieu(): array {
    $cinsee = $this->cinsee;
    $sql = "select ST_AsGeoJSON(wkb_geometry) from chef_lieu_carto where insee_com='$cinsee'";
    //echo "$sql\n";
    $query = PgSql::query($sql);
    $query->next();
    if ($query->valid()) {
      $tuple = $query->current();
      $geojson = json_decode($tuple['st_asgeojson'], true);
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
  
  function changeDeCodePour(): ?string {
    $derniereVersion = array_values($this->versions)[count($this->versions)-1];
    return $derniereVersion->evts()['changeDeCodePour'] ?? null;
  }
};

// Version d'un Historique
class Version {
  protected $cinsee;
  protected $debut;
  protected $fin;
  protected $evts;
  protected $etat;
  
  function __construct(string $cinsee, string $debut, array $version) {
    $this->cinsee = $cinsee;
    $this->debut = $debut;
    $this->fin = null;
    $this->evts = $version['evts'] ?? [];
    $this->etat = $version['etat'] ?? [];
    //print_r($version);
  }
  
  function setFin(string $fin) { $this->fin = $fin; }
  function type(): string { return in_array($this->etat['statut'], ['COMA', 'COMD', 'ARDM']) ? 'r' : 's'; }
  function cinsee(): string { return $this->cinsee; }
  function etat(): array { return $this->etat; }
  function evts(): array { return $this->evts; }
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
};

Histo::load('../zones/histelt.yaml');

PgSql::open('host=172.17.0.4 dbname=gis user=docker password=docker');

// Chaque objet est un couple dont le premier membre est une zone définie dans COG2020
// et le second membre est un arbre de zones non définies
class DefUndefs {
  protected $defId; // id d'une zone valide définie dans COG2020, on prend l'id le plus récent et non l'id std
  protected $ref; // ref de la zone définie
  protected $undefs; // [ id => Zone ] - id et zone non définies
  
  function __construct(string $defId, array $sameAs, string $ref, array $undefs) {
    $this->defId = $defId;
    $date = substr($defId, 7);
    foreach ($sameAs as $id) {
      $date2 = substr($id, 7);
      if (strcmp($date2, $date) > 0) {
        $date = $date2;
        $this->defId = $id;
      }
    }
    $this->ref = $ref;
    $this->undefs = $undefs;
  }
  
  function asArray(): array {
    $undefs = [];
    foreach ($this->undefs as $id => $undef)
      $undefs[$id] = $undef->asArray();
    return ['defId'=> $this->defId, 'ref'=>$this->ref, 'undefs'=> $undefs]; 
  }

  // retourne un MultiPoint avec un point par zone non définie
  function points(): array {
    $points = [];
    foreach ($this->undefs as $undef)
      $points = array_merge($points, $undef->points());
    return ['type'=>'MultiPoint', 'coordinates'=> $points];
  }
  
  // retourne le GeoJSON des polygones de Voronoi associés à un objet
  /*
  select ST_AsGeoJSON(ST_VoronoiPolygons(
    ST_GeomFromGeoJSON('{"type":"MultiPoint","coordinates":[[5.6877,45.9292],[5.6877,45.9542],[5.6794,45.9044]]}'),
    0,
    (select wkb_geometry from commune_carto where id='01079')
  ));
  */
  function buildVoronoi(): array {
    if ($this->ref == 'ecomp')
      $id = 'c'.substr($this->defId, 1, 5);
    else
      $id = substr($this->defId, 0, 6);
    $undefPoints = $this->points();
    $sql = 'select ST_AsGeoJSON(ST_VoronoiPolygons('
      ."ST_GeomFromGeoJSON('".json_encode($undefPoints)."'),"
      .'0,'
      ."(select geom from eadming3 where eid='$id')"
    ."))";
    //echo "$sql\n";
    foreach (PgSql::query($sql) as $tuple)
      break; // je prend le premier tuple
    $result = json_decode($tuple['st_asgeojson'], true);
    if (count($result['geometries']) <> count($undefPoints['coordinates'])) {
      echo Yaml::dump(['$undefPoints'=> $undefPoints]);
      throw new Exception("Erreur buildVoronoi() sur $this->defId, nbre de polygones incorrect");
    }
    return $result;
  }
  
  // retourne les Feature de chaque undef en intersectant le Voronoi avec la zone définie
  function unDefAsFeatureCollection() {
    if ($this->ref == 'ecomp')
      $id = 'c'.substr($this->defId, 1, 5);
    else
      $id = substr($this->defId, 0, 6);
    $undefProperties = $this->undefProperties();
    //echo Yaml::dump(['$undefProperties'=>$undefProperties], 4, 2);
    $buildVoronoi = $this->buildVoronoi();
    //echo Yaml::dump(['voronoi'=>$buildVoronoi], 4, 2);
    $features = [];
    foreach ($buildVoronoi['geometries'] as $no => $voronoiPolygon) {
      //echo "no=$no\n";
      $sql = "select ST_AsGeoJSON(ST_Intersection(\n"
        ."  ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($voronoiPolygon)."'), 4326),\n"
        ."  (select geom from eadming3 where eid='$id')\n"
      ."))\n";
      //echo "$sql\n";
      foreach (PgSql::query($sql) as $tuple)
        $features[] = [
          'type'=> 'Feature',
          'properties'=> $undefProperties[$no],
          'geometry'=> json_decode($tuple['st_asgeojson'], true)];
    }
    return ['type'=> 'FeatureCollection', 'features'=> $features];
  }
  
  function undefProperties(): array {
    $undefProperties = [];
    foreach ($this->undefs as $undef)
      $undefProperties = array_merge($undefProperties, $undef->properties());
    return $undefProperties;
  }

  function testGeo(): void {
    try {
      $undefPoints = $this->points();
    }
    catch (Exception $e) {
      echo $e->getMessage(),"\n";
    }
  }
};

// structuration hiérarchique des zones avec possibilité de doublons
class Zone {
  const VERBOSE = false; // affiche ou non ttes les requêtes SQL générées en plus de les exécuter
  protected $id; // string
  protected $sameAs; // [ id ]
  protected $ref; // string
  protected $parentId; // string - id du parent dans l'arbre courant
  protected $parents; // [string] - liste d'id des parents si plus d'un
  protected $children; // [ id => Zone ]

  function __construct(string $id, array $zone, string $parentId='') {
    $this->id = $id;
    $this->parentId = $parentId;
    $this->sameAs = $zone['sameAs'] ?? [];
    $this->ref = $zone['ref'] ?? '';
    $this->parents = $zone['parents'] ?? [];
    $this->children = [];
    if (isset($zone['contains']))
      foreach ($zone['contains'] as $childId => $child)
        $this->children[$childId] = new Zone($childId, $child, $id);
  }
  
  function asArray(): array {
    $array = [];
    if ($this->sameAs)
      $array['sameAs'] = $this->sameAs;
    if ($this->ref)
      $array['ref'] = $this->ref;
    if ($this->children) {
      foreach ($this->children as $id => $zone)
        $array['contains'][$id] = $zone->asArray();
    }
    return $array;
  }
  
  function decompdefUndefs(): array {
    /* décomposition d'une zone racine en sous-arbre dans lesquels
      - soit (ex s01033@1971-01-01)
        - la racine est définie dans COG2020 (s, r ou ecomp)
        - et aucun des enfants ne l'est
      - soit
        - la racine est un ecomp virtuel (cad un ecomp corr. à plusieurs zones)
        - aucun des enfants n'est défini dans COG2020
        ex:
          zones:
            s23093@1972-11-01:
              ref: COG2020s
              contains:
                s23085@1943-01-01: {  }
                s23093@1943-01-01: {  }
                s23094@1943-01-01:
                  sameAs:
                    - r23094@1972-11-01
                  ref: COG2020r
    ** La fonction renvoie une liste [ DefUndefs ]
    ** Algorithme:
      - je descend récursivement dans l'arbre sur des neouds définis dans COG2020
      - sur un noeud je distingue les cas suivants:
        - tous les enfants sont définis dans COG2020
        - aucun enfant n'est défini dans COG2020
        - certains enfants sont définis et d'autres ne le sont pas
    */
    switch ($this->cas()) {
      case 'pasDEnfant': return [];

      // appel récursif
      case 'tousDéfinis': {
        $result = [];
        foreach ($this->children as $child)
          $result = array_merge($result, $child->decompdefUndefs());
        return $result;
      }

      // cas basique
      case 'aucunDéfini': return [new DefUndefs($this->id, $this->sameAs, $this->ref, $this->children)];
      
      case 'certainsDéfinis': return [new DefUndefs($this->id, $this->sameAs, 'ecomp', $this->undefChildren())];
    }
  }
  
  private function cas(): string {
    if (count($this->children)==0)
      return 'pasDEnfant';
    $nbre = $this->nbreEnfantsDefinisDansCog2020();
    if ($nbre == count($this->children))
      return 'tousDéfinis';
    elseif ($nbre == 0)
      return 'aucunDéfini';
    else
      return 'certainsDéfinis';
  }
  
  private function nbreEnfantsDefinisDansCog2020() {
    $nbre = 0;
    foreach ($this->children as $child)
      if (in_array($child->ref, ['COG2020s','COG2020r','COG2020ecomp','COG2020union']))
        $nbre++;
    return $nbre;
  }
  
  private function undefChildren(): array {
    $undefs = [];
    foreach ($this->children as $id => $child)
      if (!in_array($child->ref, ['COG2020s','COG2020r','COG2020ecomp','COG2020union']))
        $undefs[$id] = $child;
    return $undefs;
  }
  
  // retourne les points associés, un point par zone feuille
  function points(): array {
    if (!$this->children) {
      $cinsee = substr($this->id, 1, 5);
      return [ Histo::get($cinsee)->chefLieu() ];
    }
    $result = [];
    foreach ($this->children as $child)
      $result = array_merge($result, $child->points());
    return $result;
  }
  
  function properties(): array {
    if (!$this->children) {
      $version = Histo::getVersion($this->id);
      $prop = [
        'id'=> $this->id,
        'fin'=> $version->fin(),
        'sameAs'=> $this->sameAs,
        'name'=> [$version->name(substr($this->id,0,1))],
      ];
      return [ $prop ];
    }
    $result = [];
    foreach ($this->children as $child)
      $result = array_merge($result, $child->properties());
    return $result;
  }
  
  /*/ décompose un noeud en feuilles
  function leaves(): array {
    if (!$this->children)
      return [ $this->id ];
    $result = [];
    foreach ($this->children as $child)
      $result = array_merge($result, $child->leaves());
    return $result;
  }*/
};


PgSql::query("drop table if exists voronoi");
PgSql::query("create table voronoi(
  type char(1) not null, -- 's' ou 'r'
  cinsee char(5) not null, -- code Insee
  debut char(10) not null, -- date de création de la version dans format YYYY-MM-DD
  fin char(10), -- date du lendemain de la fin de la version dans format YYYY-MM-DD, ou null ssi version valide à la date de référence
  dnom varchar(256), -- dernier nom
  geom geometry, -- géométrie
  primary key (type, cinsee, debut) -- la clé est composée du type, du code Insee et de la date de création
)");
$date_atom = date(DATE_ATOM);
PgSql::query("comment on table voronoi is 'couche des Voronoi du référentiel générée le $date_atom'");

function storeFeature(array $feature): void {
  $id = $feature['properties']['id'];
  $type = substr($id, 0, 1);
  $cinsee = substr($id, 1, 5);
  $debut = substr($id, 7);
  $fin = $feature['properties']['fin'];
  $fin = $fin ? "'$fin'" : 'null';
  $dnom = str_replace("'", "''", $feature['properties']['name'][0]);
  $geojson = json_encode($feature['geometry']);
  $sql = "insert into voronoi(type, cinsee, debut, fin, dnom, geom) "
    ."values('$type', '$cinsee', '$debut', $fin, '$dnom', ST_SetSRID(ST_GeomFromGeoJSON('$geojson'), 4326))";
  echo "sql=$sql\n";
  Pgsql::query($sql);
}

$yaml = Yaml::parseFile('../zones/zones.yaml');
//$yaml = Yaml::parseFile('zonestest.yaml');
//print_r($yaml);

foreach ($yaml as $id => $zone) {
  if (!is_array($zone)) continue; // je saute le titre
  //if (substr($id, 1, 2) <> '01') die("-- Fin 01\n");
  //if ($id <> 's33055@2019-01-01') continue;
  $zone = new Zone($id, $zone);
  //echo "$id: "; print_r($zone);
  if ($decompdefUndefs = $zone->decompdefUndefs()) {
    if ($_GET['action'] == 'testGeo') {
      foreach ($decompdefUndefs as $defUndefs)
        $defUndefs->testGeo();
      continue;
    }
    //echo Yaml::dump([$id => $zone->asArray()], 99, 2);
    //echo "Décomposition:\n";
    foreach ($decompdefUndefs as $defUndefs) {
      //echo Yaml::dump([$defUndefs->asArray()], 99, 2),"\n";
      $featureCollection = $defUndefs->unDefAsFeatureCollection();
      echo Yaml::dump(['$featureCollection'=> $featureCollection], 3, 2),"\n\n";
      foreach ($featureCollection['features'] as $feature) {
        storeFeature($feature);
      }
    }
  }
}
die("-- Fin ok à ".date(DATE_ATOM)."\n");
