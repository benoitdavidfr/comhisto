<?php
/*PhpDoc:
name: index.php
title: map/index.php - visualisation carto de ComHisto
doc: |
  Génère un fichier Html permettant de visualiser des entités de comhisto sous la forme:
    1) d'une carte dans un cadre
    2) d'un extrait de histelit
    3) d'un représentation JSON-LD de l'entité ciblée

  Fichier soit éxécuté comme script directement
    en localhost:
      http://localhost/yamldoc/pub/comhisto/map/?id=01015
    sur georef:
      https://georef.eu/yamldoc/pub/comhisto/map/?id=33055
  Soit inclus dans ../api/api.php
    en localhost:
      http://localhost/yamldoc/pub/comhisto/api/api.php
      http://localhost/yamldoc/pub/comhisto/api/api.php/COM/01015
      http://localhost/yamldoc/pub/comhisto/api/api.php?id=01015
    sur comhisto.georef.eu:
      https://comhisto.georef.eu/
      https://comhisto.georef.eu/COM/01015
      https://comhisto.georef.eu/COM/01015/2016-01-01
      https://comhisto.georef.eu/?id=01034

  Le script prend normalement en paramètre GET id soit:
    - un code Insee, ex 01015,
    - un code Insee préfixé par s ou r, ex r01015,
    - un code Insee préfixé et suffixé par @ et une date, ex r01015@2016-01-01
    - un code Insee suffixé par @ et une date, ex 01015@2016-01-01
    - une partie du nom de l'entité recherchée.
  Si ce paramètre est absent alors affiche le formulaire de recherche.
  Si le paramètre ne correspond pas à un id alors recherche des entités dont le nom contient la chaine.
  N'utilise pas de paramètre en PATH_INFO

  Le formulaire fait appel au même script (avec action='') en utilisant le paramètre GET id
  Le script crée un iframe pour la carte qui utilise map.php du même répertoire
  Le script propose des href

  Cas d'utilisation et valeur de $_SERVER[SCRIPT_NAME]:
    - localhost
      - http://localhost/yamldoc/pub/comhisto/map/?id=01015 -> /yamldoc/pub/comhisto/map/index.php
      - http://localhost/yamldoc/pub/comhisto/api/api.php   -> /yamldoc/pub/comhisto/api/api.php
    - georef.eu
      - https://georef.eu/yamldoc/pub/comhisto/map/?id=97101 -> /yamldoc/pub/comhisto/map/index.php
      - https://georef.eu/yamldoc/pub/comhisto/api/api.php/COM/01015/2016-01-01 -> /yamldoc/pub/comhisto/api/api.php
    - comhisto.georef.eu
      - https://comhisto.georef.eu/ ->
      - https://comhisto.georef.eu/ERAT/01340/2016-01-01 -> 
      - https://comhisto.georef.eu/ERAT/01340/2016-01-01?id=s01015@1943-01-01 -> 

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

// contient la quasi-totalité du code pour pouvoir être utilisée par ../api/api.php
// $id est l'identifiant de l'objet à afficher: soit un code Insee, soit un code préfixé par 's' ou 'r',
//   soit préfixé et suffixé par '@' et la date de début
// $record est un array contenant
//   soit le champ 'body' à afficher en JSON-LD dans le <head> et Yaml dans la page
//   soit le champ 'error'
function showComHisto(?string $id='', array $record=null): string {
  //echo "drawMap($id)<br>\n";
  //echo "<pre>record"; print_r($record); echo "</pre>\n";
  //echo "<pre>"; print_r($_SERVER); echo "</pre>\n";
  //echo "index.php> SCRIPT_NAME=$_SERVER[SCRIPT_NAME]<br>\n";
  
  // détermination de $mapScriptName qui est le chemin à utiliser pour appeler le script map/map.php
  if (!$_SERVER['SCRIPT_NAME']) // cas sur https://comhisto.georef.eu/
    // Par convention, les fichiers de map et d'api sont virtuellement accessibles par https://comhisto.georef.eu/
    $mapScriptName = '/map.php';
  elseif (basename($_SERVER['SCRIPT_NAME'])=='index.php') // cas sur http://localhost/yamldoc/pub/comhisto/map/
    // je suis dans le bon répertoire, il suffit donc d'utiliser le chemin relatif
    $mapScriptName = 'map.php';
  elseif (basename($_SERVER['SCRIPT_NAME'])=='uriapi.php') { // cas sur http://localhost/yamldoc/pub/comhisto/uriapi/uriapi.php
    // je suis dans le répertoire api, j'utilise le répertoire de comhisto défini indirectement par $_SERVER['SCRIPT_NAME']
    $comHistoDirname = dirname(dirname($_SERVER['SCRIPT_NAME'])); // répertoire comhisto
    //echo "index.php> comHistoDirname=$comHistoDirname<br>\n";
    $mapScriptName = "$comHistoDirname/map/map.php";
  }
  else
    throw new Exception("Cas non prévu pour SCRIPT_NAME=$_SERVER[SCRIPT_NAME]");
  //echo "index.php> mapScriptName=$mapScriptName<br>\n";
  
  // si id correspond à une des 4 formes alors extraction du code Insee sinon null
  $cinsee = preg_match('!^([sr])?(\d[\dAB]\d\d\d)(@(\d\d\d\d-\d\d-\d\d))?$!', $id, $matches) ? $matches[2] : null;
  
  //echo "<pre>drawMap($id), cinsee=$cinsee, record="; print_r($record); echo "</pre>\n";
  echo "<!DOCTYPE HTML><html>\n<head>",
    "<meta charset='UTF-8'>",
    '<meta name="google-site-verification" content="w7OzHQjbjmirp3lhcURtYl-4_vOErXR0pNIMJfiKn08" />',"\n",
    "<title>map $id</title>\n",
    (isset($record['body']) ?
        "<script type=\"application/ld+json\">\n"
        .json_encode($record['body'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
        ."</script>\n"
      : ''),
    "</head><body>\n";
  
  $form = "<table><tr>" // le formulaire
        //. "<td><table border=1><form action='$_SERVER[SCRIPT_NAME]'><tr>"
        . "<td><table border=1><form action=''><tr>"
        . "<td><input type='text' size=60 name='id' value='$id'/></td>\n"
        . "<td><input type='submit' value='Chercher'></td>\n"
        . "</tr></form></table></td>\n"
        //. "<td>(<a href='doc.php' target='_blank'>doc</a>)</td>\n"
        . "</tr></table>\n";

  if (!$id || in_array($id,['s','r'])) { // si paramètre vide ou r|s alors affichage du formulaire de recherche
    echo $form;
    if ($record['body'] ?? null) {
      echo "<pre><h3>Publication comme données liées en Yaml-LD</h3>\n",
        Yaml::dump($record['body'], 3, 2),
        "</pre>\n";
    }
  }
  elseif ($error = $record['error'] ?? null) {
    echo $form;
    echo "erreur: $error[message]";
  }
  // si id correspond à un code Insee
  elseif ($cinsee && ($cluster = Histelits::cluster(__DIR__.'/../elits2/histelitp', $cinsee))) {
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
    $yaml = preg_replace("!(\d[\dAB]\d\d\d)('?:)!", "<a href='?id=\\1'>\\1</a>\\2", $yaml);
    // remplacement des dates par un href vers l'id de la version correspondante
    $yaml = preg_replace('!([sr]\d[\dAB]\d\d\d@([^:]+)):!', "<a href='?id=\\1'>\\2</a>:", $yaml);
    // remplacement des nomCommeDéléguée par un href vers l'id de la version correspondante
    $yaml = preg_replace('!(nomCommeDéléguée)([^:]+)!', "<a href='?id=r\\2'>\\1</a>", $yaml);
    $dirname = dirname($script_name); // répertoire du script dans le serveur Http
    echo "<table><tr>";
    echo "<td valign='top'>",
      "<iframe id='map' title='map' width='650' height='650' src='$mapScriptName?id=$id'></iframe>",
      "</td>\n";
    echo "<td valign='top'>$form<pre>$yaml";
    if ($record) {
      echo "<h3>Publication comme données liées en Yaml-LD</h3>\n",
        Yaml::dump($record['body'], 3, 2);
    }
    echo "</pre></td>\n";
    echo "</tr></table>\n";
  }
  else { // sinon recherche des entités à partir du nom
    echo "recherche noms contenant $id\n";
    echo $form;
    // Test en minuscules après suppression des accents
    $search = mb_strtolower(supprimeAccents($id));
    $cinsees = [];
    if (!Histelits::$all)
      Histelits::readfile(__DIR__.'/../elits2/histelitp');
    foreach (Histelits::$all as $cinsee => $histelit) {
      foreach ($histelit as $ddebut => $version) {
        //echo "<pre>",Yaml::dump($version),"</pre>\n";
        if ($name = $version['état']['name'] ?? null) {
          if (strpos(mb_strtolower(supprimeAccents($name)), $search) !== false)
            $cinsees[$cinsee] = 1;
        }
      }
    }
    if ($cinsees) {
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
    else { // gestion d'erreur de code Insee dans le cas du script map/index.php
      echo "<b>Erreur: $id ne correspond pas à un code Insee et aucun nom ne contient cette chaine</b><br>";
    }
  }
  echo "</body></html>\n";
  die();
}

// Exécution lorsque le script est appelé directement et pas inclus dans ../api/api.php
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
  showComHisto($_GET['id'] ?? '');
}