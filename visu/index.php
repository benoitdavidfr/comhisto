<?php
/* Ergonomie mauvaise notamment impossibilité d'accéder aux voisines d'une entité périmée
Réfléchir à une ergonomie
avec affichage de 1er niveau par COM valide, affichage des ER et des périmées
*/
require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class AutoDescribed { // Pour garder une compatibilité avec YamlDoc, le pser est enregistré comme objet AutoDescribed
  protected $_id;
  protected $_c;

  function __construct(array $c, string $docid) { $this->_c = $c; $this->_id = $docid; }
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; } // lit les champs
  function asArray() { return $this->_c; }

  static function readfile(string $path): array { // lit un fichier si possible en pser sinon en Yaml, renvoit son contenu ss ses MD
    if (is_file("$path.pser") && (filemtime("$path.pser") > filemtime("$path.yaml"))) {
      $file = unserialize(file_get_contents("$path.pser"));
      return $file->contents;
    }
    else {
      $yaml = Yaml::parseFile("$path.yaml");
      file_put_contents("$path.pser", serialize(new AutoDescribed($yaml, '')));
      return $yaml['contents'];
    }
  }
};

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>visu ",$_GET['id'] ?? '',"</title></head><body>\n";
echo "<table><tr>"; // formulaire
echo "<td><table border=1><form><tr>";
echo "<td><input type='text' size=120 name='id' value='",$_GET['id'] ?? '',"'/></td>\n";
echo "<td><input type='submit' value='Chercher'></td>\n";
echo "</tr></form></table></td>\n";
echo "<td>(<a href='doc.php' target='_blank'>doc</a>)</td>\n";
echo "</tr></table>\n";

$histelit = null;
if (isset($_GET['id'])) {
  $histelits = AutoDescribed::readfile(__DIR__.'/../elits2/histelitp');
  $histelit = $histelits[$_GET['id']] ?? null;
}
if ($histelit) { // affichage de l'histelit correspondant au code Insee
  echo "<table><tr>";
  echo "<td><pre>",Yaml::dump($histelit),"</td>\n";
  echo "<td><iframe id='map' title='map' width='600' height='600' src='map.php?id=$_GET[id]'></iframe></td>\n";
  echo "</tr></table>\n";
}
else { // recherche des entités à partir du nom
  $cinsees = [];
  foreach ($histelits as $cinsee => $histelit) {
    foreach ($histelit as $ddebut => $version) {
      //echo "<pre>",Yaml::dump($version),"</pre>\n";
      $name = $version['état']['name'] ?? null;
      if (preg_match("!$_GET[id]!i", $name))
        $cinsees[$cinsee] = 1;
    }
  }
  //print_r($cinsees);
  foreach (array_keys($cinsees) as $cinsee) {
    $histelit = $histelits[$cinsee];
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
