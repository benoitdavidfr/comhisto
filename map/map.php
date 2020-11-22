<?php
/*PhpDoc:
name: map.php
title: map/map.php - génère la carte Leaflet appelée avec un code Insee en paramètre
doc: |
  Script normalement appelé dans un iframe depuis ./index.php ou ../api/api.php pour générer une carte Leaflet
  Il est toujours appelé avec un paramètre GET id qui est:
    - soit un code Insee, eg: 01015
    - soit l'id d'une version, eg: r01015@2016-01-01,
    - soit un code Insee précédé de 's' ou 'r', eg: r01015
    - soit un code Insee suivi d'une date de version, ex: 01015@2016-01-01,
  Les entités appartenant au cluster sont créées comme couche (overlay) Leaflet.
  Si le paramètre est un code Insee alors est affichée une des entités les plus récentes de ce code Insee.
  Si le paramètre est l'id d'une version alors cette version ou une autre ayant même géographie est affichée.
  Si l'id est un code Insee précédé de 's' ou 'r' alors est affichée l'entité la plus récente de ce code Insee
  correspondant à ce type.
  
  Script utilisé soit depuis ./index.php:
    en localhost:
      http://localhost/yamldoc/pub/comhisto/map/?id=01015
    sur georef:
      https://georef.eu/yamldoc/pub/comhisto/map/?id=33055
  Soit depuis ../api/api.php:
    en localhost:
      http://localhost/yamldoc/pub/comhisto/api/api.php?id=01015
    sur georef:
      https://comhisto.georef.eu/COM/01015
      https://comhisto.georef.eu/COM/01015/2016-01-01
      https://comhisto.georef.eu/?id=01034
  
  $_SERVER[REQUEST_SCHEME] vaut 'hhtp' sur localhost et 'https' sur Alwaysdata, que la connexion soit en http ou en https
  Son utilisation conduit donc à utiliser des liens en http sur localhost et en https sur Alwaysdata

  Le code JS fait des appels à plusieurs URL:
    - des fichiers CSS ou JS dans le sous-répertoire leaflet
    - les couches de base en PNG ou JPEG
    - les périmètres des entités visualisées par le script geojson.php appelé en AJAX GeoJSON
    - les entités voisines par le script neighbor.php appelé en AJAX GeoJSON
    - l'affichage d'une entité voisine par renvoi vers index.php
  Ces 3 derniers scripts sont appelés dans le même répertoire que map.php
  ou dans le cas d'une utilisation en API sur georef à la racine, dans ce cas l'appel doit être géré par api.php

journal: |
  22/11/2020:
    - ajout du 4ème type de paramètres
  18/11/2020:
    - utilisation des 3 différents type de paramètres
  17/11/2020:
    - adaptation pour utilisation avec l'API
  11-12/11/2020:
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

if (!isset($_GET['id']) || !$_GET['id']) { // erreur si le paramètre id n'est pas défini ou vide 
  header('HTTP/1.1 400 Bad Request');
  die("Erreur dans map.php, paramètre id non défini ou vide");
}

$id = $_GET['id'];

if (!preg_match('!^([sr])?(\d[\dAB]\d\d\d)(@(\d\d\d\d-\d\d-\d\d))?$!', $id, $matches)) {
  header('HTTP/1.1 400 Bad Request');
  die("Erreur dans map.php, paramètre id=$id incorrect");
}
//print_r($matches);
$type = $matches[1];
$cinsee = $matches[2];
$ddebut = $matches[4] ?? null;

$cluster = Histelits::cluster(__DIR__.'/../elits2/histelitp', $cinsee); // génération du cluster
// Calcul du rectangle englobant
$sql = "select min(ST_XMin(geom)) xmin, min(ST_YMin(geom)) ymin, max(ST_XMax(geom)) xmax, max(ST_YMax(geom)) ymax
        from comhistog3 where cinsee in ('".implode("','",array_keys($cluster))."')";
//echo "$sql<br>\n";
$bbox = new GBox(PgSql::getTuples($sql)[0]);
if ($bbox->size() === null) { // Erreur si bbox non défini 
  header('HTTP/1.1 400 Bad Request');
  die("Erreur dans map.php, bbox non défini pour $_GET[id]");
}

echo "<pre>";
//echo "size=",$bbox->size(),"\n";
//echo "zoom=",$bbox->zoom(),"\n";

// $dirPath est le chemin correspondant au répertoire dans lequel est map.php
// Lors d'un appel sur https://comhisto.georef.eu/, $dirPath vaut https://comhisto.georef.eu
$dirPath = "$_SERVER[REQUEST_SCHEME]://$_SERVER[HTTP_HOST]".dirname($_SERVER['SCRIPT_NAME']);
//print_r($_SERVER);
//echo "SCRIPT_NAME=$_SERVER[SCRIPT_NAME],\ndirPath=$dirPath\n";
//echo "REQUEST_SCHEME=$_SERVER[REQUEST_SCHEME],\ndirPath=$dirPath\n";

// construction des couches visualisables (overlays) en se limitant à une seule entité par géographie
// (définie par ses élitEtendus) et en privilégiant la version la plus récente.
// Utilisation de la légende:
//   vert - communes valides
//   bleu - entités rattachées valides
//   rouge - versions périmées
// La couche affichée par défaut est
// si l'id est un code Insee alors une des versions les plus récentes correspondant à ce code Insee
// sinon la couche d'une entité dont la géographie est la même que celle demandée
$statut = ($type == 'r') ? 'ERAT' : 'COM'; // statut COM ou ERAT
$elitEtendusDeLEntiteeDemandée = (strlen($id)==17) ? Histelits::elitEtendus($id, $statut) : null;
$defaultOverlayIds = [];
$layers = []; // [layerId => ['path'=> path, 'color'=> color]] - liste des couches à afficher
$elitss = []; // [elitEtendus => $layerId] - élitsEtendu des couches à afficher
$sql = "select id, cinsee, type, ddebut, dfin, statut from comhistog3
        where cinsee in ('".implode("','",array_keys($cluster))."')
        order by ddebut asc, type asc";
foreach (PgSql::query($sql) as $tuple) {
  $elitEtendus = (strlen($id)==17) ? Histelits::elitEtendus($tuple['id'], $tuple['statut']) : null;
  $tuple['$elitEtendus'] = $elitEtendus;
  //echo '$tuple='; print_r($tuple);
  if ($elitEtendus && isset($elitss[$elitEtendus]))
    unset($overlays[$elitss[$elitEtendus]]);
  $overlays[$tuple['id']] = [
    'path'=> "$dirPath/geojson.php?id=$tuple[id]",
    'color'=> $tuple['dfin'] ? 'red' : (in_array($tuple['statut'],['COMA','COMD','ARM']) ? 'blue' : 'green'),
  ];
  $elitss[$elitEtendus] = $tuple['id'];
  if ($id == $cinsee) { // le param. est code Insee alors une des versions les plus récentes correspondant à ce code Insee
    if ($tuple['cinsee'] == $cinsee)
      $defaultOverlayIds = [ $tuple['id'] ];
  }
  elseif ($id == "$type$cinsee") {
    if (($tuple['cinsee'] == $cinsee) && ($tuple['type'] == $type))
      $defaultOverlayIds = [ $tuple['id'] ];
  }
  elseif (strlen($id)==16) { // {cinsee}@{ddebut}
    if (($tuple['cinsee'] == $cinsee) && (!$defaultOverlayIds || ($tuple['type'] == 's')))
      $defaultOverlayIds = [ $tuple['id'] ];
  }
  else { // sinon si version alors une entité dont la géographie est la même que celle demandée
    if ($elitEtendus == $elitEtendusDeLEntiteeDemandée)
      $defaultOverlayIds = [ $tuple['id'] ];
  }
}
//echo 'overlays = '; print_r($overlays);
$neigborPath = "$dirPath/neighbor.php?id=$cinsee";
// Plan IGN V2 n'existe pas dans les DOM, il est remplacé par ScanExpress qui existe
//$defaultBaseLayer = (substr($cinsee, 0, 2) == '97') ? "Scan Express" : "Plan IGN v2";
// Finalement si, Plan IGN V2 existe dans les DOM
$defaultBaseLayer = "Plan IGN v2";
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
  // affichage pour chaque voisines du lien vers index.php
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
    /*"Plan IGN" : new L.TileLayer(
      'https://igngp.geoapi.fr/tile.php/plan-ign/{z}/{x}/{y}.jpg',
      { format:"image/jpeg", minZoom:0, maxZoom:18, detectRetina:false,
        attribution:"&copy; <a href='http://www.ign.fr' target='_blank'>IGN</a>"
      }
    ),*/
    "Scan Express IGN" : new L.TileLayer(
      'https://igngp.geoapi.fr/tile.php/scan-express/{z}/{x}/{y}.jpg',
      { format:"image/jpeg", minZoom:6, maxZoom:18, detectRetina:true,
        attribution:"&copy; <a href='http://www.ign.fr' target='_blank'>IGN</a>"
      }
    ),
    "Scan Express N&amp;B IGN" : new L.TileLayer(
      'https://igngp.geoapi.fr/tile.php/scan-express-ng/{z}/{x}/{y}.png',
      { format:"image/png", minZoom:6, maxZoom:18, detectRetina:true,
        attribution:"&copy; <a href='http://www.ign.fr' target='_blank'>IGN</a>"
      }
    ),
  // PYR Shom
    "Cartes Shom" : new L.TileLayer(
      'https://geoapi.fr/shomgt/tile.php/gtpyr/{z}/{x}/{y}.png',
      { format:"image/png", minZoom:0, maxZoom:18, detectRetina:false,
        attribution:"&copy; <a href='http://data.shom.fr' target='_blank'>Shom</a>"
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
<?php
foreach ($defaultOverlayIds as $defaultOverlayId)
  echo "map.addLayer(overlays['$defaultOverlayId']);\n";
?>
  
  L.control.layers(baseLayers, overlays).addTo(map);
  </script>
</body>
</html>
