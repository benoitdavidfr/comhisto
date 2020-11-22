<?php
/*PhpDoc:
name: accept.php
title: accept.php - affiche le résultat d'une requête Http en positionnant le paramètre Accept
doc: |
  effectue une requête Http en positionnant le paramètre Accept
  soit à 'application/ld+json' soit à 'application/json,application/geo+json'
  Affiche le résultat en Yaml en reamplacant les URL par des liens
journal: |
  21/11/2020:
    - améliorations
*/
require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

function replaceUrl(string $text): string {
  $pattern = '!(https?:)(//[^ \n\'"]*)!';
  while (preg_match($pattern, $text, $matches)) {
    $text = preg_replace($pattern, "<a href='?url=".urlencode($matches[1].$matches[2])."'>Http:$matches[2]</a>", $text, 1);
    //break;
  }
  return $text;
}

function response_header(array $input): array {
  $output = ['returnCode'=> array_shift($input)];
  foreach ($input as $val) {
    $pos = strpos($val, ': ');
    $output[substr($val, 0, $pos)] = substr($val, $pos+2);
  }
  return $output;
}

echo "<table><tr>" // le formulaire
      . "<td><table border=1><form><tr>"
      . "<td><input type='text' size=100 name='url' value='".($_GET['url'] ?? '')."'/></td>\n"
      . "<td><label for='ld'>ld</label><input type='checkbox' id='ld' name='ld' value='on'"
        .(isset($_GET['ld']) ? ' checked' : '')."></td>"
      . "<td><input type='submit' value='Envoyer'></td>\n"
      . "</tr></form></table></td>\n"
      . "<td>(<a href='doc.php' target='_blank'>doc</a>)</td>\n"
      . "</tr></table>\n";
if ($url = ($_GET['url'] ?? '')) {
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
  if (($contents = @file_get_contents($url, false, $context)) ===  FALSE) {
    echo "<pre>Erreur de lecture de $url\nhttp_response_header = ";
    echo Yaml::dump(['header'=> response_header($http_response_header)], 3, 2),"</pre>\n";
    die();
  }
  echo "<pre>",Yaml::dump(['header'=> response_header($http_response_header)], 3, 2),"</pre>\n";
  if (($array = json_decode($contents, true)) === null) {
    echo "<pre><b>Erreur de décodage JSON</b>\n$contents";
    die();
  }
  echo "<pre>";
  echo //json_encode($array, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
    replaceUrl(Yaml::dump(['body'=> $array], 4, 2));
}
