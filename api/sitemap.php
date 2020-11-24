<?php
/*PhpDoc:
name: sitemap.php
title: api/sitemap.php - Génération d'un sitemap
doc: |
journal: |
  23/11/2020:
    - création
*/
require_once __DIR__.'/../../../../phplib/pgsql.inc.php';
require_once __DIR__.'/../map/openpg.inc.php';

$type = 'COM';

header('Content-Type: application/xml');
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

$t = ($type=='COM') ? 's': 'r';

$sql = "select cinsee, ddebut, dnom
        from comhistog3
        where type='$t' and dfin is null";
try {
  foreach (PgSql::getTuples($sql) as &$tuple) {
    $url = "https://comhisto.georef.eu/$type/$tuple[cinsee]/$tuple[ddebut]";
    echo "<url>
    <loc>$url</loc>
    <lastmod>2020-11-23</lastmod>
    <changefreq>monthly</changefreq>
    <priority>1</priority>
  </url>\n";
  }
}
catch (Exception $e) {
  echo "<pre>sql=$sql</pre>\n";
  echo $e->getMessage();
  throw new Exception($e->getMessage());
}
echo "</urlset>\n";

