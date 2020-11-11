<?php
/*PhpDoc:
name: index.php
title: map/index.php - visualisation carto de ComHisto
doc: |
  Ergonomie mauvaise
  Réfléchir à une ergonomie avec affichage de 1er niveau par COM valide, affichage des ER et des périmées
journal: |
  11/11/2020:
    - création
*/
require_once __DIR__.'/../../../vendor/autoload.php';

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

$form = "<table><tr>" // le formulaire
      . "<td><table border=1><form><tr>"
      . "<td><input type='text' size=60 name='id' value='".($_GET['id'] ?? '')."'/></td>\n"
      . "<td><input type='submit' value='Chercher'></td>\n"
      . "</tr></form></table></td>\n"
      . "<td>(<a href='doc.php' target='_blank'>doc</a>)</td>\n"
      . "</tr></table>\n";

$histelit = null;
if (isset($_GET['id']) && $_GET['id']) {
  $histelits = AutoDescribed::readfile(__DIR__.'/../elits2/histelitp');
  $histelit = $histelits[$_GET['id']] ?? null;
}
if ($histelit) { // affichage de l'histelit correspondant au code Insee
  $yaml = Yaml::dump($histelit);
  $yaml = preg_replace('!(\d[\dAB]\d\d\d)!', "<a href='?id=\\1'>\\1</a>", $yaml);
  echo "<table><tr>";
  echo "<td><iframe id='map' title='map' width='700' height='650' src='map.php?id=$_GET[id]'></iframe></td>\n";
  echo "<td valign='top'>$form<pre>$yaml</pre></td>\n";
  echo "</tr></table>\n";
}
elseif (isset($_GET['id']) && $_GET['id']) { // recherche des entités à partir du nom
  // Test en minuscules après suppression des accents
  $search = mb_strtolower(supprimeAccents($_GET['id']));
  $cinsees = [];
  foreach ($histelits as $cinsee => $histelit) {
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
else {
  echo $form;
}
echo "</body></html>\n";
die();
