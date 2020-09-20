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
  18/9/2020:
    - création
*/
/* Sortie 18/9/2020 7:43 - 26 erreurs à corriger:
Erreur sur elit=04044 (Castillon) / s04039 - corrigé
Erreur sur elit=14262 (La Ferrière-au-Doyen) / r14061 -> r14629 - corrigé
Erreur sur elit=14490 (Parfouru-l'Éclin) / r14143 -> r14372 - corrigé
Erreur sur elit=14233 (Écajeul) / r14431 -> 14422 - corrigé
Erreur sur elit=14567 (Saint-Crespin) / r14431 -> 14422 - corrigé
Erreur sur elit=15193 (Saint-Julien-de-Jordanne) / s15113 - déplacé
Erreur sur elit=16319 (Saint-Genis / Saint-Genis-de-Blanzac) / r16115 - déplacé
Erreur sur elit=24049 (Born-de-Champs) / r24028 -> r24497 - corrigé
Erreur sur elit=25593 (Vaux / Vaux-les-Prés) / s25147 - corrigé
Erreur sur elit=28244 (Mervilliers) / r28199 -> 28002 - corrigé
Erreur sur elit=38506 (Thuellin) / r38022 -> r38541 - corrigé
Erreur sur elit=39416 (Petit-Villard) / c39331 - corrigé
Erreur sur elit=42258 (Saint-Martin-en-Coailleux) / s42207 - corrigé
Erreur sur elit=44177 (Sainte-Marie) / s44131 - corrigé
Erreur sur elit=49146 (Les Gardes) / r49092 -> r49281 - corrigé
Erreur sur elit=49357 (Trèves-Cunault) / r49149
Erreur sur elit=51385 (Moronvilliers) / s51440
Erreur sur elit=52382 (Percey-le-Petit / Percey-sous-Montormentier) / r52382
Erreur sur elit=54319 (Lixières) / s54059
Erreur sur elit=59460 (Petite-Synthe) / c59183
Erreur sur elit=59245 (Forenville) / s59567
Erreur sur elit=73172 (Montpascal) / r73135
Erreur sur elit=73251 (Saint-Laurent-de-la-Côte) / r73257
Erreur sur elit=79228 (Rigné) / c79329
Erreur sur elit=89185 (Fyé) / s89068
Erreur sur elit=78613 (Thionville) / s91613
*/
ini_set('memory_limit', '1G');
set_time_limit(2*60);

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../../../../phplib/pgsql.inc.php';
require_once __DIR__.'/../cheflieu/cheflieu.inc.php'; // classe ChefLieu donnant accès aux chefs-lieux
require_once __DIR__.'/pgsqlsa.inc.php'; // Extension de PgSql pour simplifier l'appel des fonctions d'analyse spatiale
require_once __DIR__.'/histo.inc.php'; // classes Histo et Version stockant histelitp.yaml
require_once __DIR__.'/centelits.inc.php'; // classe CEntElits - couple (eadmin (coms, erat, ecomp) définie dans COG2020, élits corr.)

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

ChefLieu::load(__DIR__.'/../cheflieu');
//print_r(ChefLieu::$all);

Histo::load('histelitp.yaml');
//echo Yaml::dump(Histo::allAsArray(), 3, 2);

PgSql::open('host=172.17.0.4 dbname=gis user=docker password=docker');

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
    $cEntElit->verifChefLieuDansEadmin();
  }
}
