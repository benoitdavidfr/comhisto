<?php
/*PhpDoc:
name: verif.php
title: api/verif.php - Tests sur verif.yaml
doc: |
journal: |
  23/11/2020:
    - création
*/
require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

$accept = isset($_GET['ld']) ? 'application/ld+json' : 'application/json,application/geo+json';
//echo (!isset($_GET['ld']) ? "ld non défini" : "ld=$_GET[ld]"),"<br>\n";
$opts = [
  'http'=> [
    'method'=> 'GET',
    'header'=> "Accept: $accept\r\n"
              ."Accept-language: en\r\n"
              ."Cookie: foo=bar\r\n",
  ],
];
$context = stream_context_create($opts);

$jeutest = Yaml::parseFile('verif.yaml');


echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>verif</title>\n</head><body>\n";
//echo "<pre>"; //print_r($jeutest);
echo "<table border=1><th>",implode('</th><th>', array_keys($jeutest['contents']['baseUrls'])),"</th>";
foreach ($jeutest['contents']['path_infos'] as $path_info) {
  echo "<tr>";
  foreach ($jeutest['contents']['baseUrls'] as $baseUrl) {
    $url = $baseUrl.$path_info['url'];
    $contents = @file_get_contents($url, false, $context);
    $bgcolor = $contents === false ? '#FFB6C1' : '#00D000';
    echo "<td bgcolor='$bgcolor'><pre>";
    echo "<a href='$url'>$path_info[url] $path_info[title]</a>\n";
    if ($contents ===  FALSE) {
      echo "Erreur Http: $http_response_header[0]\n";
    }
    else {
      echo "Lecture Http ok,";
      if (($array = json_decode($contents, true)) === null) {
        echo " Erreur de décodage JSON\n";
      }
      else {
        echo " JSON ok\n";
      }
    }
    echo "</pre></td>\n";
  }
  echo "</tr>";
}
die("</table>\n");
