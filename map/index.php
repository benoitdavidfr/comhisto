<?php
/*PhpDoc:
name: index.php
title: map/index.php - visualisation carto de ComHisto
doc: |
  Ergonomie mauvaise
  Réfléchir à une ergonomie avec affichage de 1er niveau par COM valide, affichage des ER et des périmées

  Prend normalement un paramètre GET id correspondant soit à un code Insee soit à une partie du nom de l'entité recherchée.
  Si ce paramètre est absent alors affiche le formulaire de recherche.
  N'utilise pas de paramètre en PATH_INFO

  index.php n'est utilisé qu'en direct cad:
    sur localhost
      http://localhost/yamldoc/pub/comhisto/map/?id=01015
    sur georef en http ou en https:
      https://georef.eu/yamldoc/pub/comhisto/map/?id=33055

  Le formulaire fait appel au même script en utilisant le paramètre GET id
  Le script crée un iframe pour la carte qui utilise map.php dans le même répertoire que index.php
journal: |
  11-12/11/2020:
    - création
*/
require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/histelits.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

//echo "<pre>"; print_r(mb_get_info()); echo "</pre>\n";
//echo "<pre>"; print_r(mb_strtolower($_GET['id'])); echo "</pre>\n";
//echo "<pre>"; print_r(mb_strtoupper($_GET['id'])); echo "</pre>\n";

function supprimeAccents(string $str): string {
	$search  = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ò', 'Ó', 'Ô',
    'Õ', 'Ö', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'à', 'á', 'â', 'ã', 'ä', 'å', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î',
    'ï', 'ð', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ');
	$replace = array('A', 'A', 'A', 'A', 'A', 'A', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'O', 'O', 'O',
    'O', 'O', 'U', 'U', 'U', 'U', 'Y', 'a', 'a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i',
    'i', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y');
	return str_replace($search, $replace, $str);
}

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>map ",$_GET['id'] ?? '',"</title></head><body>\n";
//echo "REQUEST_SCHEME=$_SERVER[REQUEST_SCHEME],\n";
//echo "<pre>_SERVER="; print_r($_SERVER); echo "</pre>\n";

$form = "<table><tr>" // le formulaire
      . "<td><table border=1><form><tr>"
      . "<td><input type='text' size=60 name='id' value='".($_GET['id'] ?? '')."'/></td>\n"
      . "<td><input type='submit' value='Chercher'></td>\n"
      . "</tr></form></table></td>\n"
      . "<td>(<a href='doc.php' target='_blank'>doc</a>)</td>\n"
      . "</tr></table>\n";

if (!($_GET['id'] ?? null)) { // si paramètre vide alors affichage du formulaire de recherche
  echo $form;
}
elseif ($cluster = Histelits::cluster(__DIR__.'/../elits2/histelitp', $cinsee)) { // si id est un code Insee
  $yaml = Yaml::dump($cluster, 3, 2);
  $yaml = preg_replace('!(\d[\dAB]\d\d\d)!', "<a href='?id=\\1'>\\1</a>", $yaml);
  echo "<table><tr>";
  echo "<td valign='top'>",
    "<iframe id='map' title='map' width='700' height='650' src='map.php?id=$_GET[id]'></iframe>",
    "</td>\n";
  echo "<td valign='top'>$form<pre>$yaml</pre></td>\n";
  echo "</tr></table>\n";
}
else { // recherche des entités à partir du nom
  // Test en minuscules après suppression des accents
  $search = mb_strtolower(supprimeAccents($_GET['id']));
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
