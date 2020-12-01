<?php
/*PhpDoc:
name: json.php
title: json.php - affiche le résultat d'une requête Http en positionnant le paramètre Accept à JSON ou JSON-LD
doc: |
  effectue une requête Http en positionnant le paramètre Accept
  soit à 'application/ld+json' soit à 'application/json,application/geo+json'
  Affiche le résultat en Yaml en remplacant les URL par des liens
journal: |
  28/11/2020:
    - améliorations
  26/11/2020:
    - changement de nom
  21/11/2020:
    - améliorations
*/
//ini_set('max_execution_time', 30*60);

require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

function replaceUrl(string $text): string {
  if (strlen($text) > 1e6) return $text; // Si le text est trop long (1 Mo) on ne fait rien car trop long
  $ydepth = $_GET['ydepth'] ?? 9;
  $ld = $_GET['ld'] ?? null;
  $args = "&ydepth=$ydepth".($ld ? "&ld=$ld" : '');
  $pattern = '!(http(s)?:)(//[^ \n\'"]*)!';
  while (preg_match($pattern, $text, $m)) {
    $text = preg_replace($pattern, "<a href='?url=".urlencode($m[1].$m[3])."$args'>Http$m[2]:$m[3]</a>", $text, 1);
    //break;
  }
  return $text;
}

// transforme le contenu de $http_response_header en un array
function response_header(array $input): array {
  if (!$input) return ['error'=> '$http_response_header non défini'];
  $output = ['returnCode'=> array_shift($input)];
  foreach ($input as $val) {
    $pos = strpos($val, ': ');
    $output[substr($val, 0, $pos)] = substr($val, $pos+2);
  }
  return $output;
}

$url = $_GET['url'] ?? '';
$ydepth = $_GET['ydepth'] ?? 9;

echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>json</title>\n</head><body>\n";
echo "<table><tr>" // le formulaire
      . "<td><table border=1><form><tr>"
      . "<td><input type='text' size=130 name='url' value='$url'/></td>\n"
      . "<td><label for='ld'>ld</label><input type='checkbox' id='ld' name='ld' value='on'"
        .(isset($_GET['ld']) ? ' checked' : '')."></td>"
      . "<td><input type='text' size=3 name='ydepth' value='$ydepth'/></td>\n"
      . "<td><input type='submit' value='Envoyer'></td>\n"
      . "</tr></form></table></td>\n"
      . "<td>(<a href='doc.php' target='_blank'>doc</a>)</td>\n"
      . "</tr></table>\n";

if (!$url)
  die("Url vide");

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
//echo "url=$url\n";
if (FALSE === $contents = @file_get_contents($url, false, $context)) {
  echo "<pre>Erreur de lecture de $url\nhttp_response_header = ";
  die(Yaml::dump(['header'=> response_header($http_response_header ?? null)], 3, 2));
}
$response_header = response_header($http_response_header ?? null);
echo "<pre>",Yaml::dump(['header'=> $response_header], 3, 2),"</pre>\n";

if (($response_header['Content-Encoding'] ?? null) == 'gzip') {
  $contents = gzdecode($contents);
}

$contentType = $response_header['Content-Type'] ?? null;
if ($contentType == 'text/html')
  die($contents);
$jsonContentTypes = [
  'application/ld+json','application/json','application/geo+json',
  'application/vnd.oai.openapi+json;version=3.0',
];
if (!in_array($contentType, $jsonContentTypes))
  die("<pre>$contents");
if (($array = json_decode($contents, true)) === null)
  die("<pre><b>Erreur de décodage JSON</b>\n$contents");
echo "<pre>",replaceUrl(Yaml::dump(['json'=> 'ok', 'body'=> $array], $ydepth, 2));
