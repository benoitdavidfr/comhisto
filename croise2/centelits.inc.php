<?php
/*PhpDoc:
name: centelits.inc.php
title: centelits.inc.php - couple (eadmin (coms, erat, ecomp) définie dans COG2020, élits correspondants)
doc: |
journal: |
  18/9/2020:
    - création
*/

class CEntElits {
  const ONLY_SHOW_SQL = false; // true <=> les reqêtes SQL de création d'elts sont affichées mais pas exécutées
  protected $ent; // eadmin (coms, erat, ecomp) définie dans COG2020 identifiée par le type et le code Insee
  protected $eltSet; // ensemble d'élits, sous la forme d'un EltSet
  
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
    PgSql::query("drop table if exists elit");
    PgSql::query("create table elit(
      cinsee char(5) not null primary key, -- code Insee
      geom geometry -- géométrie Polygon|MultiPolygon 4326
    )");
    $date_atom = date(DATE_ATOM);
    PgSql::query("comment on table elit is 'couche des éléments intemporels générée le $date_atom'");
    //file_put_contents(__DIR__.'/fcomhisto.sql', '');
  }
  
  function storeElits(): void { // enregistre les élits dans la table des élits
    $eid = $this->ent;
    if ($this->eltSet->count() == 1) { // l'eadmin correspond à un seul élit => la géométrie de l'élit est celle de l'eadmin
      $elt = $this->eltSet->elts()[0];
      $sql = "insert into elit(cinsee, geom) select '$elt', geom from eadming3 where eid='$eid'";
      //file_put_contents(__DIR__.'/fcomhisto.sql', "$sql\n", FILE_APPEND);
      try {
        if (self::ONLY_SHOW_SQL)
          echo "sql=$sql\n";
        elseif (($affrows = PgSql::query($sql)->affected_rows()) <> 1)
          echo "Erreur sur affected_rows=$affrows ligne ",__LINE__,", sql=$sql\n";
      }
      catch (Exception $e) {
        echo $e->getMessage(),"\n";
        echo "sql=$sql\n";
        die("Erreur Sql ligne ".__LINE__."\n");
      }
    }
    else { // l'eadmin correspond à un plusieurs élits => la géométrie de chaque élit est calculée par Voronoi
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
      //$elts = $this->eltSet->elts();
      foreach ($voronoiPolygons['geometries'] as $no => $voronoiPolygon) {
        $elt = $this->eltCorrPolygon($voronoiPolygon);
        $sql = "insert into elit(cinsee, geom) "
          ."select '$elt', ST_Intersection("
          ."  ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($voronoiPolygon)."'), 4326),\n"
          ."  (select geom from eadming3 where eid='$eid')\n"
          .")";
        file_put_contents(__DIR__.'/fcomhisto.sql', "$sql\n", FILE_APPEND);
        try {
          if (self::ONLY_SHOW_SQL)
            echo "sql=$sql\n";
          elseif (($affrows = PgSql::query($sql)->affected_rows()) <> 1)
            echo "Erreur sur affected_rows=$affrows ligne ",__LINE__,", sql=$sql\n";
        }
        catch (Exception $e) {
          echo $e->getMessage(),"\n";
          echo "sql=$sql\n";
          die("Erreur Sql ligne ".__LINE__."\n");
        }
      }
    }
  }
  
  // recherche de l'élément dont le chef-lieu est dans le polygone
  function eltCorrPolygon(array $polygon): string {
    foreach ($this->eltSet->elts() as $elt) {
      $point = Histo::get($elt)->chefLieu();
      if (PgSqlSA::pointInPolygon(['type'=> 'Point', 'coordinates'=> $point], $polygon))
        return $elt;
    }
    die("Aucun $elt pour le polygone ".json_encode($polygon));
  }
  
  function eltMPoints(): array { // Retourne un MultiPoint GeoJSON avec un point par élément
    $points = [];
    foreach ($this->eltSet->elts() as $elt) {
      $points[] = Histo::get($elt)->chefLieu();
    }
    return ['type'=> 'MultiPoint', 'coordinates'=> $points];
  }

  function verifChefLieuDansEadmin(): bool { // renvoie vrai ssi le chef-lieu est dans l'eadmin
    $verif = true;
    if ($this->eltSet->count() == 1)
      return true;
    foreach ($this->eltSet->elts() as $elit) {
      $histo = Histo::get($elit);
      //echo "chefLieu->names=",implode(' / ', $histo->names()),"\n";
      $point = ['type'=> 'Point', 'coordinates'=> $histo->chefLieu()];
      $sql = "select ST_Within(ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($point)."'), 4326), geom) from eadming3 "
        ."where eid='$this->ent'";
      //echo "verifChefLieu: $sql\n";
      $tuples = PgSql::getTuples($sql);
      if (count($tuples) <> 1) {
        echo "Erreur $this->ent n'existe pas\n";
        $verif = false;
      }
      elseif ($tuples[0]['st_within'] <> 't') {
        echo "Erreur sur elit=$elit (",implode(' / ', $histo->names()),") / $this->ent\n";
        $verif = false;
      }
    }
    return $verif;
  }
};
