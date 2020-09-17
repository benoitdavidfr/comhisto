<?php
// génération GeoJSON des chefs-lieux

require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

// stockage des chefs-lieux provenant de Wikipedia ou saisis dans le Géoportail
class ChefLieu {
  static $all = []; // [iddept => [id => ['names'=> [name], 'geo'=>[lon, lat]]]]
  
  // chargement initial à parir des 2 fichiers Yaml
  static function load(string $dir) {
    self::$all = Yaml::parsefile("$dir/cheflieuwp.yaml");
    unset(self::$all['title']);
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

  static function allAsGeoJSON(): array {
    $features = [];
    foreach (self::$all as $iddept => $chefslieuxdept) {
      foreach ($chefslieuxdept as $cheflieu) {
        if (!isset($cheflieu['geo']))
          continue;
        $features[] = [
          'type'=> 'Feature',
          'properties'=> [
            'iddept'=> $iddept,
            'names'=> $cheflieu['names'],
          ],
          'geometry'=> [
            'type'=> 'Point',
            'coordinates'=> $cheflieu['geo'],
          ]
        ];
      }
    }
    return [
      'type'=> 'FeatureCollection',
      'name'=> 'chef-lieu-wp-gp',
      "description"=> "Chefs-lieux extraits de Wikipédia ou numérisés sur le GP",
      'features'=> $features,
    ];
  }
};
ChefLieu::load(__DIR__.'/../cheflieu');
//print_r(ChefLieu::$all); die();
echo json_encode(ChefLieu::allAsGeoJSON(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),"\n";
