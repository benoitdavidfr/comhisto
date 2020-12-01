<?php
/*PhpDoc:
name: index.php
title: geocode/index.php - géocodeur
doc: |
  1) afficher le menu de téléchargment d'un fichier depuis le poste local vers le serveur
    éventuellement effacer le fichier précédent
  2) télécharger le fichier depuis le poste local vers le serveur,
    enregistrement avec un nom aléatoire,
    identification des champs,
    proposer le choix du champ de géocodage et celui entre cntrl/dwnld
  3) soit cntrl, soit dwnld
    si cntrl alors
      afficher le cntrl
      proposer dwnld ou charger un autre fichier en effacant le fichier courant
    sinon
      réaliser le dwnld
    finsi

  dispo sur: https://comhisto.georef.eu/geocodeur
journal: |
  1/12/2020:
    - création
*/
require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../lib/openpg.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

// retourne le Feature GeoJSON correspondant à id
function geocode(string $id): array {
  $patterns = [
    'cinsee'=> '\d[\dAB]\d\d\d',
    'date'=> '\d\d\d\d-\d\d-\d\d',
  ];
  if (preg_match("!^[sr]$patterns[cinsee]@$patterns[date]$!", $id)) { // [sr]{cinsee}@{ddebut}
    $sql = "select id, cinsee, ddebut, dfin, statut, dnom, ST_AsGeoJSON(geom) geom from comhistog3 where id='$id'";
    $tuples = PgSql::getTuples($sql);
    if (!$tuples)
      return ['error'=> "Erreur : id $id inexistant"];
    $tuple = $tuples[0];
  }
  elseif (preg_match("!^($patterns[cinsee])@($patterns[date])$!", $id, $matches)) { // {cinsee}@{ddebut}
    $cinsee = $matches[1];
    $ddebut = $matches[2];
    $sql = "select id, cinsee, ddebut, dfin, statut, dnom, ST_AsGeoJSON(geom) geom
      from comhistog3 where cinsee='$cinsee' and ddebut='$ddebut'";
    $tuples = PgSql::getTuples($sql);
    if (!$tuples)
      return ['error'=> "Erreur : id $id ne correspond à aucun enregistrement"];
    elseif (count($tuples)==1) {
      $tuple = $tuples[0];
    }
    elseif (count($tuples)==2) {
      //echo '<pre>',Yaml::dump(['$tuples'=> $tuples]),"</pre>\n";
      if (in_array($tuples[0]['statut'], ['BASE','ASSO','NOUV']))
        $tuple = $tuples[0];
      else
        $tuple = $tuples[1];
    }
    else {
      echo '<pre>',Yaml::dump(['$tuples'=> $tuples]),"</pre>\n";
      return ['error'=> "Erreur : cas imprévu sur $id"];
    }
  }
  elseif (preg_match("/^([sr]?)($patterns[cinsee])#($patterns[date])$/", $id, $matches)) { // [sr]?{cinsee}!{ddebut}
    $type = $matches[1];
    $cinsee = $matches[2];
    $date = $matches[3];
    $sql = "select id, type, cinsee, ddebut, dfin, statut, dnom, ST_AsGeoJSON(geom) geom
      from comhistog3 where cinsee='$cinsee' and ddebut<='$date' and (dfin>'$date' or dfin is null)"
      .($type ? " and type='$type'":'');
    $tuples = PgSql::getTuples($sql);
    if (!$tuples)
      return ['error'=> "Erreur : id $id ne correspond à aucun enregistrement"];
    elseif (count($tuples)==1) {
      $tuple = $tuples[0];
    }
    elseif (count($tuples)==2) {
      //echo '<pre>',Yaml::dump(['$tuples'=> $tuples]),"</pre>\n";
      if (in_array($tuples[0]['statut'], ['BASE','ASSO','NOUV']))
        $tuple = $tuples[0];
      else
        $tuple = $tuples[1];
    }
    else {
      echo '<pre>',Yaml::dump(['$tuples'=> $tuples]),"</pre>\n";
      return ['error'=> "Erreur : cas imprévu sur $id"];
    }
  }
  else
    return ['error'=> "Erreur : id $id non conforme"];
  //print_r($tuples);
  $geom = json_decode($tuple['geom'], true);
  return [
    'type'=> 'Feature',
    'id'=> $tuple['id'],
    'properties'=> [
      'cinsee'=> $tuple['cinsee'],
      'ddebut'=> $tuple['ddebut'],
      'dfin'=> $tuple['dfin'],
      'statut'=> $tuple['statut'],
      'nom'=> $tuple['dnom'],
    ],
    'geometry'=> $geom,
  ];
}

class MonoPart { // décodage et téléchargement d'un fichier 
  protected $headers;
  protected $contents;
  
  function __construct(string $contents, array $headers) {
    $this->headers = $headers;
    $this->contents = $contents;
  }
  
  // renvoie le contenu encodé en fonction du header Content-Transfer-Encoding
  function decodedContents(): string {
    $ctEncoding = $this->headers['Content-Transfer-Encoding'] ?? null;
    if (!$ctEncoding || ($ctEncoding == '8bit') || ($ctEncoding == '7bit'))
      return $this->contents;
    elseif ($ctEncoding == 'base64')
      return base64_decode($this->contents);
    elseif ($ctEncoding == 'quoted-printable')
      return quoted_printable_decode($this->contents);
    else
      throw new Exception("Content-Transfer-Encoding == '$ctEncoding' inconnu");
  }
  
  // Génère un téléchargement
  function download() {
    $contents = $this->decodedContents(); // 
    header('Content-Type: '.$this->headers['Content-Type']);
    header('Content-length: '. strlen($contents));
    if (isset($this->headers['Content-Disposition']))
      header('Content-Disposition: '.$this->headers['Content-Disposition']);
    echo $contents;
    die();
  }
}

$htmlHeader = "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>géocodeur</title></head><body>\n";
  
// le formulaire de chargement du fichier sur le serveur
$form = <<<EOT
<form action='' enctype='multipart/form-data' method='POST'>
<table border=1><tr>
<input type='hidden' name='MAX_FILE_SIZE' value='30000' />
<td><label for='txt'>Selectionner un fichier CSV:</label>
<input type='file' id='inputfile' name='inputfile' accept='text/*'></td>
<td>séparateur: 
  ;<input type='radio' name='separator' value='semicolon' checked>
  ,<input type='radio' name='separator' value='comma'>
  tab<input type='radio' name='separator' value='tab'>
</td>
<td><input type='submit'></td>
</tr></table></form>
EOT;

$doc = <<<EOT
</p>Géocodage sur les versions historiques des communes
utilisant les formats d'identifiants suivants :<ul>
  <li>`{cinsee}@{ddebut}` pour définir la version d'une commune ou d'une entité rattachée portant le code {cinsee}
    et débutant à la date {ddebut}
    <ul>
      <li>exemple: `01015@2016-01-01` pour la commune nouvelle d'Arboys en Bugey</li>
    </ul>
  </li>
  <li>`[sr]{cinsee}@{ddebut}` si on veut préciser qu'il s'agit d'une commune ou d'une entité rattachée<br>
    exemples:
    <ul>
      <li>`s01015@2016-01-01` pour la commune nouvelle d'Arboys en Bugey</li>
      <li>`r01015@2016-01-01` pour la commune rattachée d'Arbignieu</li>
    </ul>
  </li>
  <li>`{cinsee}#{date}` pour définir la version d'une commune ou d'une entité rattachée portant le code {cinsee}
    et valide à la date {date}
    <ul>
      <li>exemple: `01015#2020-01-01` pour la commune nouvelle d'Arboys en Bugey</li>
    </ul>
  </li>
  <li>`[sr]{cinsee}#{date}` si on veut préciser qu'il s'agit d'une commune ou d'une entité rattachée<br>
    exemples:
    <ul>
      <li>`s01015#2020-01-01` pour la commune nouvelle d'Arboys en Bugey</li>
      <li>`r01015#2020-01-01` pour la commune rattachée d'Arbignieu</li>
    </ul>
  </li>
</ul>
EOT;

if (!$_POST && (!$_GET || ($_GET['action']=='delfile'))) {
  if ($_GET && ($_GET['action']=='delfile') && file_exists(__DIR__."/$_GET[filename]"))
    unlink(__DIR__."/$_GET[filename]");
  echo $htmlHeader,
    "<h3>Géocodeur</h3>\n",
    $form,
    $doc;
  die();
}
//echo "<pre>_POST="; print_r($_POST);
//echo "_FILES="; print_r($_FILES);
//echo "</pre>\n";

if ($_POST) { // transfert du fichier
  if ($_FILES['inputfile']['error'] <> 0) {
    die("<b>Erreur: aucun fichier détecté, erreur ".$_FILES['inputfile']['error']."</b><br>\n$form");
  }
  $rand = rand(0, 999999);
  $filename = "localfile$rand.txt";
  if (file_put_contents(__DIR__."/$filename", file_get_contents($_FILES['inputfile']['tmp_name'])) === false)
    die("Erreur de copie de ".$_FILES['inputfile']['tmp_name']);
  if (($handle = fopen(__DIR__."/$filename", 'r')) === FALSE)
    die("Erreur de lecture de $filename");
  $separator = ['semicolon'=>';', 'comma'=> ',', 'tab'=> "\t"][$_POST['separator']];
  $headers = fgetcsv($handle, 0, $separator);
  fclose($handle);
  echo $htmlHeader,"<form action=''>",
    "<input type='hidden' name='filename' value='$filename'>",
    "<input type='hidden' name='separator' value='$_POST[separator]'>",
    "<table border=1><tr>",
    "<td>Fichier chargé, champ: ";
  foreach ($headers as $header) {
    echo "$header<input type='radio' name='field' value='$header'>\n";
  }
  echo "<td>sortie: ",
    "cntrl<input type='radio' name='action' value='cntrl' checked>\n",
    "dwnld<input type='radio' name='action' value='dwnld'>\n",
    "</td>\n",
    "<td><input type='submit'></td>\n",
    "</tr></table></form>\n",
    "ou <a href='?action=delfile&amp;filename=$filename'>Effacer le fichier courant et charger un autre fichier CSV</a><br>\n";
  die();
}

if ($_GET) {
  if (($handle = fopen(__DIR__."/$_GET[filename]", 'r')) === FALSE)
    die("Erreur de lecture de ".$_FILES['inputfile']['tmp_name']);
  $features = [];
  $nolcsv=0; // num. de ligne dans le fichier CSV, 0 est la ligne des en-têtes
  $separator = ['semicolon'=>';', 'comma'=> ',', 'tab'=> "\t"][$_GET['separator']];
  $headers = fgetcsv($handle, 0, $separator);
  if ($_GET['action']=='cntrl')
    echo $htmlHeader,
      "<a href='?action=dwnld&amp;filename=$_GET[filename]&amp;separator=$_GET[separator]&amp;field=$_GET[field]'>",
      "Téléchargement du résultat du géocodage en GéoJSON</a>",
      " ou <a href='?action=delfile&amp;filename=$_GET[filename]'>Effacer le fichier courant et charger un autre fichier CSV</a>",
      "<table border=1><th>#</th><th>",implode('</th><th>',$headers),"</th><th>résultat du géocodage</th>\n";
  while ($record = fgetcsv($handle, 0, $separator)) { // lecture du fichier
    $nolcsv++;
    $prop = [];
    foreach ($headers as $i => $key)
      $prop[$key] = $record[$i];
    //print_r($prop);
    $feature = geocode($prop[$_GET['field']]);
    if ($_GET['action']=='cntrl')
      echo "<tr><td>$nolcsv</td><td>",implode('</td><td>', $record),"</td>",
        "<td>",$feature['error'] ?? 'ok',"</td>",
        //"<td><pre>",Yaml::dump($feature),"</pre></td>",
        "</tr>\n";
    elseif (!isset($feature['error'])) {
      $feature['properties'] = array_merge($feature['properties'], $prop);
      $features[] = $feature;
    }
  }
  fclose($handle);
  if ($_GET['action']=='cntrl')
    die("</table>\n");
  $doc = new MonoPart(
    json_encode([
        'type'=> 'FeatureCollection',
        'features'=> $features,
      ],
      JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
    ),
    [
      'Content-Type'=> "application/geo+json",
      'Content-Disposition'=> "attachment; filename=\"geocodage.geojson\"",
      'Content-Transfer-Encoding'=> 'quoted-printable',
    ]
  );
  $doc->download();
}
