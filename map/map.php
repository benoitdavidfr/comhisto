<?php
/*PhpDoc:
name: map.php
title: map/map.php - carte Leaflet appelée avec un code Insee en paramètre
doc: |
  Bug - la géométrie d'un objet n'est pas définie pas ses elits mais ses élits + ses erats !!!
  Problème de disparition des zones valides
journal: |
  11/11/2020:
    - création
classes:
*/
require_once __DIR__.'/../../../../phplib/pgsql.inc.php';
require_once __DIR__.'/histelits.inc.php';
require_once __DIR__.'/openpg.inc.php';

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

Histelits::readfile(__DIR__.'/../elits2/histelitp');

$cluster = Histelits::cluster($_GET['id']);
$sql = "select min(ST_XMin(geom)) xmin, min(ST_YMin(geom)) ymin, max(ST_XMax(geom)) xmax, max(ST_YMax(geom)) ymax
        from comhistog3 where cinsee in ('".implode("','",array_keys($cluster))."')";
//echo "$sql<br>\n";
$bbox = new GBox(PgSql::getTuples($sql)[0]);
if ($bbox->size() === null) {
  die("Erreur bbox non défini pour $_GET[id]");
}

echo "<pre>";
//echo "size=",$bbox->size(),"\n";
//echo "zoom=",$bbox->zoom(),"\n";

// affichage des entités en se limitant à une seule entité pour chaque géographie (élitEtendus)
// et en privilégiant la version la plus récente
// rouge - versions périmées
// vert - COM valides
// bleu - COMA et COMD valides
$layers = []; // [layerId => ['path'=> path, 'color'=> color]]
$elitss = []; // [elitsEtendu => $layerId]
$sql = "select id, ddebut, dfin, statut from comhistog3
        where cinsee in ('".implode("','",array_keys($cluster))."')
        order by ddebut asc, type asc";
foreach (PgSql::query($sql) as $tuple) {
  $elitEtendus = Histelits::elitEtendus($tuple['id'], $tuple['statut']);
  //$tuple['$elitEtendus'] = $elitEtendus;
  //echo '$tuple='; print_r($tuple);
  if (isset($elitss[$elitEtendus]))
    unset($overlays[$elitss[$elitEtendus]]);
  $overlays[$tuple['id']] = [
    'path'=> "http://$_SERVER[HTTP_HOST]".dirname($_SERVER['PHP_SELF'])."/geojson.php?id=$tuple[id]",
    'color'=> $tuple['dfin'] ? 'red' : (in_array($tuple['statut'],['COMA','COMD','ARM']) ? 'blue' : 'green'),
  ];
  $elitss[$elitEtendus] = $tuple['id'];
}
$dirPath = "http://$_SERVER[HTTP_HOST]".dirname($_SERVER['PHP_SELF']);
$neigborPath = "$dirPath/neighbor.php?id=$_GET[id]";
// Plan IGN V2 n'existe pas dans les DOM
$defaultBaseLayer = (substr($_GET['id'], 0, 2) == '97') ? "Scan Express" : "Plan IGN v2";
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
  // affichage détaillé des caractéristiques de chaque entité
  var onEachFeatureCH = function (feature, layer) {
    layer.bindPopup(
      '<b>comhistog3</b><br>'
      +'<pre>'+JSON.stringify(feature.properties,null,' ')+'</pre>'
    );
    layer.bindTooltip(feature.properties.dnom + ' (' + feature.properties.id + ')');
  }
  // affichage de liens pour les voisines
  var onEachFeatureNB = function (feature, layer) {
    layer.bindPopup(
      '<b>voisine</b><br>'
      + "<a href='<?php echo "$dirPath/?id="; ?>"
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
    "Plan IGN v2" : new L.TileLayer(
      'https://igngp.geoapi.fr/tile.php/plan-ignv2/{z}/{x}/{y}.png',
      { format:"image/png", minZoom:0, maxZoom:18, detectRetina:false,
        attribution:"&copy; <a href='http://www.ign.fr' target='_blank'>IGN</a>"
      }
    ),
    "Plan IGN" : new L.TileLayer(
      'https://igngp.geoapi.fr/tile.php/plan-ign/{z}/{x}/{y}.jpg',
      { format:"image/jpeg", minZoom:0, maxZoom:18, detectRetina:false,
        attribution:"&copy; <a href='http://www.ign.fr' target='_blank'>IGN</a>"
      }
    ),
    "Scan Express" : new L.TileLayer(
      'https://igngp.geoapi.fr/tile.php/scan-express/{z}/{x}/{y}.jpg',
      { format:"image/jpeg", minZoom:6, maxZoom:18, detectRetina:true,
        attribution:"&copy; <a href='http://www.ign.fr' target='_blank'>IGN</a>"
      }
    ),
    "Scan Express N&amp;B" : new L.TileLayer(
      'https://igngp.geoapi.fr/tile.php/scan-express-ng/{z}/{x}/{y}.png',
      { format:"image/png", minZoom:6, maxZoom:18, detectRetina:true,
        attribution:"&copy; <a href='http://www.ign.fr' target='_blank'>IGN</a>"
      }
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
  map.addLayer(baseLayers['<?php echo $defaultBaseLayer;?>']);

  var overlays = {
<?php
// affichage des couches préparées
foreach ($overlays as $overlayId => $overlay) {
  echo "    '$overlayId' : new L.GeoJSON.AJAX('$overlay[path]', {\n";
  echo "      style: { color: '$overlay[color]'}, minZoom: 0, maxZoom: 18, onEachFeature: onEachFeatureCH\n";
  echo "    }),\n";
}
?>

  // affichage d'une couche des voisines
    "voisines" : new L.GeoJSON.AJAX('<?php echo $neigborPath; ?>', {
      style: {color: 'lightGreen', weight: 5, opacity: 0.65}, minZoom: 0, maxZoom: 18, onEachFeature: onEachFeatureNB
    })
  };
  map.addLayer(overlays['<?php echo $overlayId;?>']);

  L.control.layers(baseLayers, overlays).addTo(map);
  </script>
</body>
</html>
