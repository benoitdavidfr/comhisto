<?php
/*PhpDoc:
name: join.php
title: join.php - croise la liste des zones avec la table eadming3 pour générer la couche du référentiel
screens:
doc: |
  Produit le code SQL pour générer la couche du référentiel à partir de la table eadming3
  Test sur le dept 01 - semble ok
journal:
  10-11/8/2020:
    - création
*/
ini_set('memory_limit', '1G');

require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>join</title></head><body><pre>\n";

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

Histo::load('../insee/histov.yaml');

$create = "drop table if exists comhisto;
create table comhisto(
  type char(1), -- 's' ou 'r'
  cinsee char(5), -- code Insee
  debut char(10), -- date de début
  fin char(10), -- date de fin
  dnom varchar(256), -- dernier nom
  geom geometry -- géométrie
);\n";
echo $create;

class Zone {
  protected $id; // string
  protected $sameAs; // [ id ]
  protected $ref; // string
  protected $contains; // [ id => Zone ]
  
  function __construct(string $id, array $zone) {
    $this->id = $id;
    $this->sameAs = $zone['sameAs'] ?? [];
    $this->ref = $zone['ref'] ?? '';
    $this->contains = [];
    if (isset($zone['contains']))
      foreach ($zone['contains'] as $id => $zone)
        $this->contains[$id] = new Zone($id, $zone);
  }
  
  function asArray(): array {
    $array = [];
    if ($this->sameAs)
      $array['sameAs'] = $this->sameAs;
    if ($this->ref)
      $array['ref'] = $this->ref;
    if ($this->contains) {
      foreach ($this->contains as $id => $zone)
        $array['contains'][$id] = $zone->asArray();
    }
    return $array;
  }
  
  // Génère le code SQL pour la zone et ses sous-zones
  function gensql(): void {
    if ($this->ref) {
      echo '/*<b>',Yaml::dump([$this->id => $this->asArray()], 99, 2),'</b>*/';
      $ids = array_merge([$this->id], $this->sameAs);
      $idEAdmins = $this->idEAdmins();
      if (count($idEAdmins) > 1) {
        echo "-- <b>$this->id composed of [",implode(',',$idEAdmins),"]</b>\n";
      }
      foreach ($ids as $id) {
        $version = Histo::getVersion($id);
        $fin = $version->fin();
        $dnom = $version->name(substr($id,0,1));
        echo "insert into comhisto(type, cinsee, debut, fin, dnom, geom)\n";
        echo "select '",substr($id,0,1),"','",substr($id,1,5),"','",$version->debut(),"',",
              $fin ? "'".$fin."'," : 'null,',
              "'",str_replace("'","''", $dnom),"',",
              (count($idEAdmins) == 1) ?
                "geom from eadming3 where eid='".$idEAdmins[0]."';\n"
              : "ST_Union(geom) from eadming3 where eid in ('".implode("','",$idEAdmins)."');\n";
      }
    }
    foreach ($this->contains as $zone)
      $zone->gensql();
  }
  
  // Renvoie la liste des id d'élts admin. correspondant à la zone
  function idEAdmins(): array {
    if (!$this->contains) { // Si la zone ne contient pas de sous-zones
      return [$this->idCog()];
    }
    else {
      $subZones = [];
      foreach ($this->contains as $id => $subZone) {
        if ($subZone->ref)
          $subZones[] = $subZone->idCog();
      }
      if (count($subZones) == 0) // aucune des sous-zones n'est définie dans le COG
        return [$this->idCog()];
      elseif (count($subZones) == count($this->contains))
        return $subZones;
      else
        return array_merge(['c'.substr($this->id, 1, 5)], $subZones);
    }
  }
  
  // fournit l'id du Cog de la zone
  function idCog(): string {
    if ($this->ref == 'COG2020ecomp')
      return 'c'.substr($this->id, 1, 5);
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
  if (!is_array($zone)) {
    //echo "$id: $zone\n";
    continue;
  }
  if (substr($id, 1, 2) <> '01') {
    die("-- Fin 01\n");
  }
  //echo "$id: "; print_r($zone);
  $zone = new Zone($id, $zone);
  //echo "$id: "; print_r($zone);
  $zone->gensql();
}


