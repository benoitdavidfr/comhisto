<?php
/*PhpDoc:
name: verifcheflieu.php
title: verifcheflieu.php - verification que les chefs-lieux sont situés géographiquement dans l'eadming3 correspondant
doc: |
  Dans fcomhisto je découpe les eadming3 par l'algo de Voronoi en fonction des points des chefs-lieux
  Si ces points de chefs-lieux ne sont pas situés dans cet eadming3 que je découpe alors ce découpage ne fonctionne pas
  Ce script vérifie que ces chefs-lieux sont bien situés dedans.
  Pour cela,
    - je lit histolitp
    - pour chaque version valide
      - je construis les couples (eadmin, elits)
      - j'effectue le test d'inclusion
journal: |
  23/9/2020 4:26:
    - ttes les erreurs sont corrigées
  18/9/2020:
    - création
*/
ini_set('memory_limit', '1G');

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../../../../phplib/pgsql.inc.php';
require_once __DIR__.'/../cheflieu/cheflieu.inc.php'; // classe ChefLieu donnant accès aux chefs-lieux
require_once __DIR__.'/pgsqlsa.inc.php'; // Extension de PgSql pour simplifier l'appel des fonctions d'analyse spatiale
require_once __DIR__.'/histo.inc.php'; // classes Histo et Version stockant histelitp.yaml
require_once __DIR__.'/centelits.inc.php'; // classe CEntElits - couple (eadmin (coms, erat, ecomp) définie dans COG2020, élits corr.)

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (php_sapi_name() <> 'cli')
  set_time_limit(2*60);

ChefLieu::load(__DIR__.'/../cheflieu');
//print_r(ChefLieu::$all);

Histo::load(__DIR__.'/../elits2/histelitp.yaml');
//echo Yaml::dump(Histo::allAsArray(), 3, 2);

PgSql::open('host=pgsqlserver dbname=gis user=docker password=docker');

$verif = true; // false si au moins une erreur
foreach (Histo::$all as $cinsee => $histo) {
  //if (substr($cinsee, 0, 1) >= 4) break;
  //if (substr($cinsee, 0, 1) < 8) continue;
  if (!($vvalide = $histo->vvalide())) {
    //echo "$cinsee non valide\n";
    continue;
  }
  if (!($cEntElits = $vvalide->cEntElits())) {
    continue;
  }
  foreach ($cEntElits as $cEntElit) {
    $verif = $verif && $cEntElit->verifChefLieuDansEadmin();
  }
}
exit($verif ? 0 : 1);
