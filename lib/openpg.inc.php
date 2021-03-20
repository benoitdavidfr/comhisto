<?php
/*PhpDoc:
name: openpg.inc.php
title: map/openpg.inc.php - instruction d'ouverture de PgSql en fonction du serveur
doc: |
  Les mots de passe secrets ne sont pas stockés ici.
  Le compte comhisto sur Ovh est en accès seulement.
journal: |
  18/12/2020:
    création utilisateur comhisto/Comhisto123456 sur Ovh et transfert connexion base vers Ovh
    suppression de l'utilisateur comhisto sur Alwaysdata, ne plus utiliser Alwaysdata
  12/11/2020:
    - création
*/
//echo "<pre>_SERVER="; print_r($_SERVER); //die();

require_once __DIR__.'/../../../../phplib/pgsql.inc.php';

if (($_SERVER['HTTP_HOST'] ?? 'localhost')=='localhost') {
  PgSql::open('host=pgsqlserver dbname=gis user=docker');
  //PgSql::open('host=db207552-001.dbaas.ovh.net port=35250 dbname=comhisto user=comhisto password=Comhisto123456');
}
else {
  //PgSql::open('host=postgresql-bdavid.alwaysdata.net dbname=bdavid_comhisto user=bdavid_comhisto password=motdepasse123$');
  PgSql::open('host=db207552-001.dbaas.ovh.net port=35250 dbname=comhisto user=comhisto password=Comhisto123456');
}
