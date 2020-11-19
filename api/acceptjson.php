<?php
// interroge un serveur Http en positionnant le paramètre Accept
require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

function replaceUrl(string $text): string {
  $pattern = '!(http:)(//[^ \n\'"]*)!';
  while (preg_match($pattern, $text, $matches)) {
    $text = preg_replace($pattern, "<a href='?url=".urlencode($matches[1].$matches[2])."'>Http:$matches[2]</a>", $text, 1);
    //break;
  }
  return $text;
}

echo "<table><tr>" // le formulaire
      . "<td><table border=1><form><tr>"
      . "<td><input type='text' size=60 name='url' value='".($_GET['url'] ?? '')."'/></td>\n"
      . "<td><input type='submit' value='Envoyer'></td>\n"
      . "</tr></form></table></td>\n"
      . "<td>(<a href='doc.php' target='_blank'>doc</a>)</td>\n"
      . "</tr></table>\n";
if ($url = ($_GET['url'] ?? '')) {
  $opts = [
    'http'=> [
      'method'=> 'GET',
      'header'=> "Accept: application/json\r\n"
                ."Accept-language: en\r\n"
                ."Cookie: foo=bar\r\n",
    ],
  ];

  $context = stream_context_create($opts);
  echo "url=$url\n";
  if (($contents = @file_get_contents($url, false, $context)) ===  FALSE) {
    echo "<pre>Erreur de lecture de $url\nhttp_response_header = ";
    print_r($http_response_header);
    die();
  }
  if (($array = json_decode($contents, true)) === null) {
    echo "<pre><b>Erreur de décodage JSON</b>\n$contents";
    die();
  }
  echo "<pre>";
  echo //json_encode($array, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
    replaceUrl(Yaml::dump($array, 3, 2));
}
