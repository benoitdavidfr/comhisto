<?php
/*PhpDoc:
name: join.php
title: join.php - croise la liste des zones avec la table eadming3 pour générer la couche du référentiel
screens:
doc: |
  Construit la couche comhistog3 du référentiel à partir de eadming3 en construisant des ordres SQL fondés sur la liste des zones
  Peut aussi en fonction du paramétrage:
    - construire la table comhisto à partir de eadmin
    - afficher les ordres au lieu de les exécuter
journal: |
  21/8/2020:
    - correction cas ex. 23093 qui doit être défini par r23094 + c23093 et non uniquement r23094
  19/8/2020:
    - modif sur s33055@2019-01-01 -> 22 erreurs d'insertion sur 44841
    - modif 49080 dans ../insee/histo.php -> 15 erreurs d'insertion sur 44839
    - ajout cas particuliers 53003, 71014 et 71263 dans zones.inc.php -> 0 erreurs d'insertion sur 44833
  16/8/2020:
    - ajout de la déf des elts -> 21 erreurs d'insertion sur 44911
  13/8/2020:
    - 3e correction des zones - 54 erreurs d'insertion sur 44799
    - 4e correction des zones - 29 erreurs d'insertion sur 44742
  12/8/2020:
    - correction des zones et adaptation de join.php - 232 erreurs d'insertion sur 45019
    - 2nd correction des zones - 82 erreurs d'insertion sur 44805
  10-11/8/2020:
    - création - Test sur le dept 01 - semble ok
    - test sur FR, erreurs provenant des cXXXXX - 293 erreurs d'insertion sur 44679 soit 0.7%
*/
ini_set('memory_limit', '1G');

echo "Début à ",date(DATE_ATOM),"\n";

require_once __DIR__.'/../../../vendor/autoload.php';
if (0) { // 1 alors exécute les ordres Sql
  require_once __DIR__.'/../../../../phplib/pgsql.inc.php';
}
else { // sinon les affiche seulement
  class PgSql {
    static function open(string $connection_string): void {}
    static function query(string $sql) { echo "$sql\n"; return new PgSql; }
    function affected_rows(): int { return 1; }
  };
}

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (php_sapi_name() <> 'cli')
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>join</title></head><body><pre>\n";

PgSql::open('host=172.17.0.4 dbname=gis user=docker password=docker');

$table = 'comhisto'.Zone::EADMIN;
PgSql::query("drop table if exists $table");
PgSql::query("create table $table(
  type char(1), -- 's' ou 'r'
  cinsee char(5), -- code Insee
  debut char(10), -- date de début
  fin char(10), -- date de fin
  dnom varchar(256), -- dernier nom
  geom geometry -- géométrie
)");

class Histo {
  static $all;
  protected $id;
  protected $versions;
  
  static function load(string $fpath) {
    $yaml = Yaml::parseFile($fpath);
    //print_r($yaml);
    foreach ($yaml['contents'] as $id => $histo) {
      self::$all[$id] = new Histo($id, $histo);
    }
    //print_r(self::$all);
  }
  
  static function getVersion(string $id): Version {
    $cinsee = substr($id, 1, 5);
    $dv = substr($id, 7);
    return self::$all[$cinsee]->versions[$dv];
  }
    
  function __construct(string $id, array $histo) {
    $this->id = $id;
    $vprec = null;
    foreach ($histo as $dv => $version) {
      if ($vprec)
        $vprec->setFin($dv);
      $this->versions[$dv] = new Version($id, $dv, $version);
      $vprec = $this->versions[$dv];
    }
  }
};

class Version {
  protected $hid;
  protected $debut;
  protected $fin;
  protected $evts;
  protected $etat;
  
  function __construct(string $hid, string $debut, array $version) {
    $this->hid = $hid;
    $this->debut = $debut;
    $this->fin = null;
    $this->evts = $version['evts'] ?? [];
    $this->etat = $version['etat'] ?? [];
    //print_r($version);
  }
  
  function setFin(string $fin) { $this->fin = $fin; }
  
  function type(): string { return in_array($this->etat['statut'], ['COMA', 'COMD', 'ARDM']) ? 'r' : 's'; }
  function cinsee(): string { return $this->hid; }
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

class Zone {
  const VERBOSE = false; // affiche ou non ttes les requêtes SQL générées en plus de les exécuter
  const EADMIN = ''; // '' pour générer comhisto à partir de eadmin ou 'g3' pour générer comhistog3 à partir de eadming3
  static $errors=0; // nbre d'erreurs
  static $inserts=0; // nbre d'insertions effectuées
  protected $id; // string
  protected $sameAs; // [ id ]
  protected $ref; // string
  protected $parentId; // string
  protected $children; // [ id => Zone ]
  
  function __construct(string $id, array $zone, string $parentId='') {
    $this->id = $id;
    $this->sameAs = $zone['sameAs'] ?? [];
    $this->ref = $zone['ref'] ?? '';
    $this->parentId = $parentId;
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
  
  // Génère et exécute le code SQL pour la zone et ses sous-zones
  function gensql(): void {
    if ($this->ref && (substr($this->ref,0,7)=='COG2020')) {
      //echo '/*<b>',Yaml::dump([$this->id => $this->asArray()], 99, 2),'</b>*/';
      $eadmin = 'eadmin'.Zone::EADMIN;
      $eadminid = (Zone::EADMIN == 'g3') ? 'eid' : 'id';
      $eltIds = $this->eltCog2020Ids();
      if (count($eltIds) > 1) {
        //echo "-- <b>$this->id composed of [",implode(',',$idEAdmins),"]</b>\n";
        $geomsql = "ST_Union(geom) from $eadmin where $eadminid in ('".implode("','",$eltIds)."')";
      }
      else {
        $geomsql = "geom from $eadmin where $eadminid='".$eltIds[0]."'";
      }
      $ids = array_merge([$this->id], $this->sameAs);
      foreach ($ids as $id) {
        $version = Histo::getVersion($id);
        $fin = $version->fin();
        $dnom = $version->name(substr($id,0,1));
        $sql  = "insert into comhisto(type, cinsee, debut, fin, dnom, geom)\n";
        $sql .= "select '".substr($id,0,1)."','".substr($id,1,5)."','".$version->debut()."',"
                .($fin ? "'".$fin."'," : 'null,')
                ."'".str_replace("'","''", $dnom)."',$geomsql";
        if (self::VERBOSE) {
          echo "$sql\n";
        }
        $result = PgSql::query($sql);
        //echo "result=",$result->affected_rows(),"\n";
        if ($result->affected_rows() <> 1) {
          echo "Erreur sur id=$id, sql=$sql\n";
          self::$errors++;
        }
        self::$inserts++;
      }
    }
    foreach ($this->children as $zone)
      $zone->gensql();
  }
  
  // Renvoie la liste des id d'élts admin. du COG2020 correspondant à la zone définie dans le COG2020
  // Un élt admin est une zone COG2020s, COG2020r, COG2020ecomp qui ne contient pas de zone COG2020s, COG2020r, COG2020ecomp
  function eltCog2020Ids(): array {
    if (!$this->children) { // Si la zone ne contient pas de sous-zones
      //echo "la zone ne contient pas de sous-zones\n";
      return [$this->idCog2020()];
    }
    else {
      $eltIds = [];
      $nbzoneNonDefinies = 0;
      foreach ($this->children as $id => $child) {
        switch ($child->ref) {
          case '': {
            //echo "$this->id zone non définie dans COG2020\n";
            $nbzoneNonDefinies++;
            break;
          }
          case 'COG2020s':
          case 'COG2020r':
          case 'COG2020ecomp': {
            $eltIds[] = $child->idCog2020();
            break;
          }
          case 'COG2020union': {
            $eltIds = array_merge($eltIds, $child->eltCog2020Ids());
            break;
          }
        }
      }
      if ($nbzoneNonDefinies && count($eltIds)) // mixte zones définies et non définies, ex s23093@1972-11-01
        return array_merge($eltIds, ['c'.substr($this->id, 1, 5)]);
      elseif (count($eltIds)) // au moins une sous-zone est définie dans le COG
        return $eltIds;
      else // aucune des sous-zones n'est définie dans le COG
        return [$this->idCog2020()];
    }
  }
  
  // Renvoie l'id du Cog2020 de la zone
  function idCog2020(): string {
    if (!$this->ref || (substr($this->ref,0,7)<>'COG2020')) 
      throw new Exception("Ref incorrect dans idCog() sur $this->id");
    if ($this->ref == 'COG2020ecomp')
      return 'c'.substr($this->parentId, 1, 5);
    // Si non je cherche la version valide
    $ids = array_merge([$this->id], $this->sameAs);
    foreach ($ids as $id) {
      $version = Histo::getVersion($id);
      $fin = $version->fin();
      if (!$fin)
        return substr($id, 0, 6);
    }
    throw new Exception("Aucune version valide dans idCog() sur $this->id");
  }
};

$yaml = Yaml::parseFile('../zones/zones.yaml');
//print_r($yaml);

foreach ($yaml as $id => $zone) {
  if (!is_array($zone)) continue;
  //if (substr($id, 1, 2) <> '01') die("-- Fin 01\n");
  //if ($id <> 's33055@2019-01-01') continue;
  //if ($id <> 's23093@1972-11-01') continue;
  $zone = new Zone($id, $zone);
  //echo "$id: "; print_r($zone);
  $zone->gensql();
}
echo Zone::$errors," erreurs d'insertion sur ",Zone::$inserts,"\n";
die("-- Fin ok à ".date(DATE_ATOM)."\n");
