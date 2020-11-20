<?php
/*PhpDoc:
name: index.php
title: map/index.php - visualisation carto de ComHisto
doc: |
  Soit le script est exécuté directement
    en localhost:
      http://localhost/yamldoc/pub/comhisto/map/?id=01015
    sur georef:
      https://georef.eu/yamldoc/pub/comhisto/map/?id=33055
  Soit le fichier est inclus dans ../api/api.php
    en localhost:
      http://localhost/yamldoc/pub/comhisto/api/api.php/COM/01015
      http://localhost/yamldoc/pub/comhisto/api/api.php?id=01015
    sur georef:
      https://comhisto.georef.eu/COM/01015
      https://comhisto.georef.eu/COM/01015/2016-01-01
      https://comhisto.georef.eu/?id=01034

  Comme script prend normalement un paramètre GET id correspondant soit à un code Insee soit à une partie du nom
  de l'entité recherchée.
  Si ce paramètre est absent alors affiche le formulaire de recherche.
  Si le paramètre ne correspond pas à un code Insee ou à l'id d'une version alors recherche des entités dont le nom
  contient cette chaine.
  N'utilise pas de paramètre en PATH_INFO

  Le formulaire fait appel au même script (par $_SERVER[SCRIPT_NAME]) en utilisant le paramètre GET id
  Le script crée un iframe pour la carte qui utilise map.php dans le répertoire ../map/ de $_SERVER[SCRIPT_NAME]
journal: |
  18/11/2020:
    - fusion de ../api/map.inc.php avec map/index.php
    - ajout de la possibilité de clicker sur chaque date pour désigner une version particulière
      (sauf entités rattachées propres)
    - définition de la couche affichée initialement
  11-12/11/2020:
    - création
*/
require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/histelits.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

function supprimeAccents(string $str): string {
	$search  = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ò', 'Ó', 'Ô',
    'Õ', 'Ö', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'à', 'á', 'â', 'ã', 'ä', 'å', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î',
    'ï', 'ð', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ');
	$replace = array('A', 'A', 'A', 'A', 'A', 'A', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'O', 'O', 'O',
    'O', 'O', 'U', 'U', 'U', 'U', 'Y', 'a', 'a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i',
    'i', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y');
	return str_replace($search, $replace, $str);
}

function map(string $id='', array $json=null): string { // contient la plupart du code pour pouvoir être utilisée par ../api
  //echo "map($id)<br>\n";
  //echo "SCRIPT_NAME=$_SERVER[SCRIPT_NAME]<br>\n";
  //echo "<pre>"; print_r($_SERVER); echo "</pre>\n";
  $cinsee = !$id ? '' : ((strlen($id) == 5) ? $id : substr($id, 1, 5));
  //echo "map($id), cinsee=$cinsee<br>\n";
  echo "<!DOCTYPE HTML><html><head>",
    "<meta charset='UTF-8'>",
    '<meta name="google-site-verification" content="w7OzHQjbjmirp3lhcURtYl-4_vOErXR0pNIMJfiKn08" />',
    "<title>map $id</title>",
    ($json ?
        "<script type=\"application/ld+json\">\n"
        .json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
        ."</script>\n"
      : ''),
    "</head><body>\n";
  
  $form = "<table><tr>" // le formulaire
        . "<td><table border=1><form action='$_SERVER[SCRIPT_NAME]'><tr>"
        . "<td><input type='text' size=60 name='id' value='$id'/></td>\n"
        . "<td><input type='submit' value='Chercher'></td>\n"
        . "</tr></form></table></td>\n"
        . "<td>(<a href='doc.php' target='_blank'>doc</a>)</td>\n"
        . "</tr></table>\n";

  if (!$id) { // si paramètre vide alors affichage du formulaire de recherche
    echo $form;
    if ($json) {
      echo "<pre><h3>Publication comme données liées en JSON-LD</h3>\n",
        Yaml::dump($json, 3, 2),
        "</pre>\n";
    }
  }
  elseif ($cluster = Histelits::cluster(__DIR__.'/../elits2/histelitp', $cinsee)) { // si id est un code Insee
    // alors affichage des histelits correspondants
    // modif des clés date pour qu'elles soient dans un second temps clickables
    foreach ($cluster as $cinsee2 => &$histelit) {
      foreach ($histelit as $ddebut => $version) {
        $etat = $version['état'] ?? [];
        $type = in_array($etat['statut'] ?? '', ['COMA','COMD','ARM']) ? 'r' : 's';
        if (isset($etat['nomCommeDéléguée'])) {
          $version['état']["nomCommeDéléguée$cinsee2@$ddebut"] = $etat['nomCommeDéléguée'];
          unset($version['état']['nomCommeDéléguée']);
        }
        $histelit["$type$cinsee2@$ddebut"] = $version;
        unset($histelit[$ddebut]);
      }
    }
    $yaml = Yaml::dump($cluster, 3, 2);
    $script_name = $_SERVER['SCRIPT_NAME'];
    // remplacement des codes Insee par un href vers ce code Insee 
    $yaml = preg_replace("!(\d[\dAB]\d\d\d)('?:)!", "<a href='$script_name?id=\\1'>\\1</a>\\2", $yaml);
    // remplacement des dates par un href vers l'id de la version correspondante
    $yaml = preg_replace('!([sr]\d[\dAB]\d\d\d@([^:]+)):!', "<a href='$script_name?id=\\1'>\\2</a>:", $yaml);
    // remplacement des nomCommeDéléguée par un href vers l'id de la version correspondante
    $yaml = preg_replace('!(nomCommeDéléguée)([^:]+)!', "<a href='$script_name?id=r\\2'>\\1</a>", $yaml);
    $dirname = dirname($_SERVER['SCRIPT_NAME']); // répertoire du script dans le serveur Http
    echo "<table><tr>";
    echo "<td valign='top'>",
      "<iframe id='map' title='map' width='650' height='650' src='$dirname/../map/map.php?id=$id'></iframe>",
      "</td>\n";
    echo "<td valign='top'>$form<pre>$yaml";
    if ($json) {
      echo "<h3>Publication comme données liées en JSON-LD</h3>\n",
        Yaml::dump($json, 3, 2);
    }
    echo "</pre></td>\n";
    echo "</tr></table>\n";
  }
  else { // sinon recherche des entités à partir du nom
    echo $form;
    // Test en minuscules après suppression des accents
    $search = mb_strtolower(supprimeAccents($id));
    $cinsees = [];
    foreach (Histelits::$all as $cinsee => $histelit) {
      foreach ($histelit as $ddebut => $version) {
        //echo "<pre>",Yaml::dump($version),"</pre>\n";
        if ($name = $version['état']['name'] ?? null) {
          if (strpos(mb_strtolower(supprimeAccents($name)), $search) !== false)
            $cinsees[$cinsee] = 1;
        }
      }
    }
    //print_r($cinsees);
    foreach (array_keys($cinsees) as $cinsee) {
      $histelit = Histelits::$all[$cinsee];
      $names = [];
      foreach ($histelit as $ddebut => $version) {
        //echo "<pre>",Yaml::dump($version),"</pre>\n";
        if ($name = $version['état']['name'] ?? null) {
          $names[$name] = 1;
        }
      }
      echo "<a href='?id=$cinsee'>",implode(' / ',array_keys($names))," ($cinsee)</a><br>\n";
    }
  }
  echo "</body></html>\n";
  die();
}

// Exécution lorsque le script est appelé directement et pas inclus dans ../api/api.php
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
  map($_GET['id'] ?? '');
}