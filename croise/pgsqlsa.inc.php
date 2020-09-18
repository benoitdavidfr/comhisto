<?php
/*PhpDoc:
name: pgsqlsa.inc.php
title: pgsqlsa.inc.php - Extension de PgSql pour simplifier l'appel des fonctions d'analyse spatiale
doc: |
journal: |
  18/9/2020:
    - création
*/
class PgSqlSA extends PgSql {
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
  
  static function pointInPolygon(array $point, array $polygon): bool {
    //boolean ST_Within(geometry A, geometry B);
    $sql = 'select ST_Within('
      ."ST_GeomFromGeoJSON('".json_encode($point)."'),"
      ."ST_GeomFromGeoJSON('".json_encode($polygon)."')"
    .")";
    //echo "pointInPolygon: $sql\n";
    $tuple = self::getTuples($sql)[0]; // je sais que le résultat contient un n-uplet
    //print_r($tuple);
    return ($tuple['st_within'] == 't');
  }
};
