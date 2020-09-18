<?php
/*PhpDoc:
name: fcomhisto.php
title: fcomhisto.php - fabriquer les elits puis les comhistog3 à partir de ces elits
doc: |
  La première phase consiste à construire à partir de l'historique Insee les entités valides et pour chacune les éléments associés
  et à en déduire la géométrie associée à chaque élément.
    a) on part d'histeltd.yaml produit par properat.php que l'on charge dans la structure Histo/Version
    b) on sélectionne pour chaque code Insee sa version valide, s'il y en a une
    c) différents cas de figure
      - la version valide correspond à une COMS sans ERAT alors c'est une entité
      - la version valide correspond à une ERAT alors c'est une entité
      - la version valide correspond à une COMS avec ERAT alors il y a potentiellement 2 entités
        - celle correspondant à une éventuelle commune déléguée propre (ex. r01015)
        - celle correspondant à une éventuelle ECOMP
      J'ai 3 cas d'ECOMP:
        - dans le cas d'une association, le territoire de la commune chef-lieu est une ECOMP (ex c38139)
        - dans le cas d'une C. nouv. sans déléguée propre, le territoire de la C chef-lieu est une ECOMP (ex 11171 / 11080 -> 11080c)
        - dans le cas de la commune nouvelle 33055, la commune d'origine 33338 est absorbées dans la c. nouv. (ex 33338/33055)

  La seconde phase consiste à définir toutes les versions à partir des éléments définis dans la 1ère phase.
  Le résultat est stocké dans la table comhistog3

journal: |
  18/9/2020:
    - renommage de voronoi.php en fcomhisto.php
    - restructuration du code en éclatant la déf. des classes dans différents fichiers
  15/9/2020:
    - exécution après ajout manuel de Lyon
  13/9/2020:
    - modif association voronoi à l'élément
  12/9/2020:
    - ajout propriétés à comhistog3
    - génération déléguée propre
  9/9/2020:
    - amélioration d'eadming3, nlle génération @ 7:45, semble ok
  6/9/2020:
    - modification en amont des elts pour en faire des elts propres, cad hors ERAT et ajout du champ eltsNonDélégués pour 33055
    - adaptation du code
    - exécution de 11:00, qqs erreurs
    - exécution -> erreurs provenant d'eadming3
  2/9/2020:
    - ajout chefs-lieux manquants
    - exécution sur la totalité
    - erreur
      Query failed: ERROR:  duplicate key value violates unique constraint "elt_pkey"
      DETAIL:  Key (cinsee)=(52018) already exists.
      Erreur Sql ligne 422
  31/8/2020:
    - génération comhistog3 partiel
  30/8/2020:
    - 10:41 testEntites ok
      cela signfie que les entités des CEntElts créés correspondent aux entités décrites dans COG2020
    - 13:46 - réciproquement chaque entité décrite dans COG2020 correspond à un CEntElts
    - 15:16 - semble fonctionner - bloqué sur chefs-lieux identiques
    - 18:00 - semble fonctionner - bloqué sur chefs-lieux identiques
  29/8/2020:
    - création
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

if (php_sapi_name() <> 'cli') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>voronoi</title></head><body><pre>\n";
  if (!isset($_GET['action'])) {
    echo "</pre><a href='?action=testEntites'>test les entités</a><br>\n";
    echo "<a href='?action=voronoi'>génère les Voronoi</a><br>\n";
    die();
  }
}
else {
  $_GET['action'] = 'prod';
}
echo "-- Début à ",date(DATE_ATOM),"\n";

class Params {
  const GEN_ELTS = true; // si true on génère les élts dans la table elt, sinon on n'y touche pas
};
if (!Params::GEN_ELTS)
  echo "Attention: Les élts ne sont pas générés\n";

ChefLieu::load(__DIR__.'/../cheflieu');
//print_r(ChefLieu::$all);

Histo::load('histelitp.yaml');
//echo Yaml::dump(Histo::allAsArray(), 3, 2);

PgSql::open('host=172.17.0.4 dbname=gis user=docker password=docker');

if ($_GET['action']=='testEntites') {
  $sql = "select eid from eadming3";
  foreach (PgSql::query($sql) as $tuple) {
    $entites[$tuple['eid']] = 1;
  }
}
elseif (Params::GEN_ELTS) {
  CEntElits::createTable();
}
//print_r($entites);

// Phase 1 - création des éléments dans la table elt
if (Params::GEN_ELTS) {
  foreach (Histo::$all as $cinsee => $histo) {
    //if (substr($cinsee, 0, 1) >= 4) break;
    //if (substr($cinsee, 0, 1) < 8) continue;
    if (!($vvalide = $histo->vvalide())) {
      //echo "$cinsee non valide\n";
      continue;
    }
    //echo Yaml::dump([$cinsee => ['histo'=> $histo->asArray(), 'v2020'=> $v2020->asArray()]], 4, 2);
    $cEntElts = $vvalide->cEntElits();
    if (!$cEntElts) {
      if ($_GET['action']=='testEntites')
        echo "Aucun cEntElt pour $cinsee\n";
    }
    else {
      foreach ($cEntElts as $cEntElt) {
        //echo '<b>',Yaml::dump(['$cEntElt'=> $cEntElt->asArray()]),"</b>\n";
        if ($_GET['action']=='testEntites') {
          // teste si chaque entité identifiée par ce process existe bien dans COG2020 et vice-versa
          $cEntElt->testEntite($entites);
        }
        else {
          $cEntElt->storeElts();
        }
      }
    }
  }
}
if ($_GET['action']=='testEntites') {
  echo Yaml::dump(['$entites'=> $entites]);
}

//die("-- Fin ok phase elts à ".date(DATE_ATOM)."\n");


// Phase 2 - 
PgSql::query("drop table if exists comhistog3");
PgSql::query("drop type if exists StatutEntite");
PgSql::query("create type StatutEntite AS enum (
  'BASE', -- commune de base
  'ASSO', -- commune de rattachement d'une association
  'NOUV', -- commune de rattachement d'une commune nouvelle
  'COMA', -- commune associée
  'COMD', -- commune déléguée
  'ARDM'  -- arrondissement municipal
)");
PgSql::query("create table comhistog3(
  id char(17) not null primary key, -- concaténation de type, cinsee, '@' et ddebut
  type char(1) not null, -- 's' ou 'r'
  cinsee char(5) not null, -- code Insee
  ddebut char(10) not null, -- date de création de la version dans format YYYY-MM-DD
  edebut jsonb not null, -- évts de création de la version
  dfin char(10), -- date du lendemain de la fin de la version dans format YYYY-MM-DD, ou null ssi version valide à la date de référence
  efin jsonb, -- évts de fin de la version, ou null ssi version valide à la date de référence
  statut StatutEntite not null,
  crat char(5), -- pour une entité rattachée (COMA, COMD, ARDM) code Insee de la commune de rattachement, sinon null
  erats jsonb not null, -- pour une commune de rattachement (ASSO, NOUV) liste JSON des codes Insee des entités rattachées
  elits jsonb, -- liste JSON des éléments intemporels propres ou null ssi il n'y en a pas
  dnom varchar(256), -- dernier nom
  geom geometry -- géométrie
)");
$date_atom = date(DATE_ATOM);
PgSql::query("comment on table comhistog3 is 'couche des versions de communes générée le $date_atom'");

foreach (Histo::$all as $cinsee => $histo) {
  //if (substr($cinsee, 0, 1) >= 4) break;
  $histo->insertComhisto();
}

die("-- Fin ok à ".date(DATE_ATOM)."\n");
