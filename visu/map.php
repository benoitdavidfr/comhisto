<?php
// script appelé avec en paramètre id le code Insee d'une entité

require_once __DIR__.'/../../../../phplib/pgsql.inc.php';

class GBox { // BBox en coordonnées géographiques
  protected $min=[]; // [number, number] ou []
  protected $max=[]; // [number, number] ou [], [] ssi $min == []
  
  function __construct(array $tuple) {
    if ($tuple['xmin'] === null) return;
    $this->min = [$tuple['xmin'], $tuple['ymin']];
    $this->max = [$tuple['xmax'], $tuple['ymax']];
  }

  // retourne le centre de la BBox
  function center(string $param='LngLat'): array {
    if (!$this->min)
      return [];
    elseif ($param == 'LatLng')
      return [($this->min[1]+$this->max[1])/2, ($this->min[0]+$this->max[0])/2];
    else
      return [($this->min[0]+$this->max[0])/2, ($this->min[1]+$this->max[1])/2];
  }

  function dLon(): ?float  { return !$this->min ? null : $this->max[0] - $this->min[0]; }
  function dLat(): ?float  { return !$this->min ? null : $this->max[1] - $this->min[1]; }

  // taille max en degrés de longueur constante (Zoom::SIZE0 / 360)
  function size(): ?float {
    if (!$this->min)
      return null;
    $cos = cos(($this->max[1] + $this->min[1])/2 / 180 * pi()); // cosinus de la latitude moyenne
    return max($this->dlon() * $cos, $this->dlat());
  }
  
  // niveau de zoom à utiliser
  function zoom(): int { return $this->min ? Zoom::zoomForGBoxSize($this->size()) : -1; }
};

{/*PhpDoc: classes
name: Zoom
title: class Zoom - classe regroupant l'intelligence autour des niveaux de zoom
*/}
class Zoom {
  const MAXZOOM = 18; // zoom max utilisé notamment pour les points
  // SIZE0 est la circumférence de la Terre en mètres
  // correspond à 2 * PI * a où a = 6 378 137.0 est le demi-axe majeur de l'ellipsoide WGS 84
  const SIZE0 = 20037508.3427892476320267 * 2;
  
  // niveau de zoom adapté à la visualisation d'une géométrie définie par la taille de son GBox
  static function zoomForGBoxSize(float $size): int {
    if ($size == 0) {
      return self::MAXZOOM;
    }
    else {
      $z = log(360.0 / $size, 2);
      //echo "z=$z<br>\n";
      return min(round($z), self::MAXZOOM);
    }
  }
};

PgSql::open('host=172.17.0.4 dbname=gis user=docker password=docker');

$sql = "select min(ST_XMin(geom)) xmin, min(ST_YMin(geom)) ymin, max(ST_XMax(geom)) xmax, max(ST_YMax(geom)) ymax
        from comhistog3 where cinsee='$_GET[id]'";
$bbox = new GBox(PgSql::getTuples($sql)[0]);
if ($bbox->size() === null) {
  die("Erreur bbox non défini pour $_GET[id]");
}

echo "<pre>";
//echo "size=",$bbox->size(),"\n";
//echo "zoom=",$bbox->zoom(),"\n";

// rouge - versions périmées
// vert - COM valides
// bleu - COMA et COMD valides
$layers = [];
$sql = "select id, dfin, statut from comhistog3 where cinsee='$_GET[id]' order by ddebut";
foreach (PgSql::query($sql) as $tuple) {
  //print_r($tuple);
  $overlays[$tuple['id']] = [
    'path'=> "http://$_SERVER[HTTP_HOST]".dirname($_SERVER['PHP_SELF'])."/geojson.php?id=$tuple[id]",
    'color'=> $tuple['dfin'] ? 'red' : (in_array($tuple['statut'],['COMA','COMD']) ? 'blue' : 'green'),
  ];
}
$neigborPath = "http://$_SERVER[HTTP_HOST]".dirname($_SERVER['PHP_SELF'])."/neighbor.php?id=$_GET[id]";
echo "</pre>\n";
?>
<!DOCTYPE HTML><html><head>
  <title>carte</title>
  <meta charset="UTF-8">
  <!-- meta nécessaire pour le mobile -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <!-- styles nécessaires pour le mobile -->
  <link rel='stylesheet' href='leaflet/llmap.css'>
  <!-- styles et src de Leaflet -->
  <link rel="stylesheet" href='leaflet/leaflet.css'/>
  <script src='leaflet/leaflet.js'></script>
  <!-- Include the edgebuffer plugin -->
  <script src="leaflet/leaflet.edgebuffer.js"></script>
  <!-- Include the Control.Coordinates plugin -->
  <link rel='stylesheet' href='leaflet/Control.Coordinates.css'>
  <script src='leaflet/Control.Coordinates.js'></script>
  <!-- Include the uGeoJSON plugin -->
  <script src="leaflet/leaflet.uGeoJSON.js"></script>
  <!-- plug-in d'appel des GeoJSON en AJAX -->
  <script src='leaflet/leaflet-ajax.js'></script>
</head>
<body>
  <div id="map" style="height: 100%; width: 100%"></div>
  <script>
  // affichage des caractéristiques de chaque commune
  var onEachFeatureCH = function (feature, layer) {
    layer.bindPopup(
      '<b>comhistog3</b><br>'
      +'<pre>'+JSON.stringify(feature.properties,null,' ')+'</pre>'
    );
    layer.bindTooltip(feature.properties.dnom + ' (' + feature.properties.id + ')');
  }
  // affichage des caractéristiques des voisines
  var onEachFeatureNB = function (feature, layer) {
    layer.bindPopup(
      '<b>voisine</b><br>'
      + "<a href='http://localhost/yamldoc/pub/comhisto/visu/?id="
      + feature.properties.cinsee + "' target='_parent'>" 
      + feature.properties.dnom 
      + '</a>'
    );
    layer.bindTooltip(feature.properties.dnom + ' (' + feature.properties.type + feature.properties.cinsee + ')');
  }
  
  var map = L.map('map').setView(<?php echo json_encode($bbox->center('LatLng')),',',$bbox->zoom(); ?>);  // view pour la zone
  L.control.scale({position:'bottomleft', metric:true, imperial:false}).addTo(map);

  // activation du plug-in Control.Coordinates
  var c = new L.Control.Coordinates();
  c.addTo(map);
  map.on('click', function(e) { c.setCoordinates(e); });

  var baseLayers = {
    "Plan IGN" : new L.TileLayer(
      'https://igngp.geoapi.fr/tile.php/plan-ign/{z}/{x}/{y}.jpg',
      {format:"image/jpeg", minZoom:0, maxZoom:18, detectRetina:false, attribution:"&copy; <a href='http://www.ign.fr' target='_blank'>IGN</a>"}
    ),
    "OSM" : new L.TileLayer(
      'http://{s}.tile.osm.org/{z}/{x}/{y}.png',
      {attribution:"&copy; <a href='https://www.openstreetmap.org/copyright' target='_blank'>les contributeurs d’OpenStreetMap</a>"}
    ),
    "Fond blanc" : new L.TileLayer(
      'https://visu.gexplor.fr/utilityserver.php/whiteimg/{z}/{x}/{y}.jpg',
      {format:'image/jpeg', minZoom:0, maxZoom:21, detectRetina:false}
    )
  };
  map.addLayer(baseLayers["Plan IGN"]);

  var overlays = {
<?php
// affichage des couches préparées
foreach ($overlays as $overlayId => $overlay) {
  echo "    '$overlayId' : new L.GeoJSON.AJAX('$overlay[path]', {\n";
  echo "      style: { color: '$overlay[color]'}, minZoom: 0, maxZoom: 18, onEachFeature: onEachFeatureCH\n";
  echo "    }),\n";
}
?>

  // affichage d'une couche debug
    "voisines" : new L.GeoJSON.AJAX('<?php echo $neigborPath; ?>', {
      style: { color: 'lightGreen'}, minZoom: 0, maxZoom: 18, onEachFeature: onEachFeatureNB
    }),
  // affichage d'une couche debug
    "debug" : new L.TileLayer(
      'http://visu.gexplor.fr/utilityserver.php/debug/{z}/{x}/{y}.png',
      {format:"image/png","minZoom":0,"maxZoom":21,"detectRetina":false}
    )
  };
  map.addLayer(overlays['<?php echo $overlayId;?>']);

  L.control.layers(baseLayers, overlays).addTo(map);
  </script>
</body>
</html>
