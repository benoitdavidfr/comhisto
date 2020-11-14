<?php
/*PhpDoc:
name: openpg.inc.php
title: map/openpg.inc.php - instruction d'ouverture de PgSql en fonction du serveur
doc: |
  Les mots de passe secrets ne sont pas stockés ici
  Sur Alwaysdata l'utilisateur bdavid_comhisto permet d'accéder en lecture à la base bdavid_comhisto ;
  il n'est pas secret et peut être diffusé. En cas d'abus il sera supprimé.
journal: |
  12/11/2020:
    - création
*/
//echo "<pre>_SERVER="; print_r($_SERVER); //die();

if (($_SERVER['HTTP_HOST'] ?? 'localhost')=='localhost')
  PgSql::open('host=172.17.0.4 dbname=gis user=docker');
else
  PgSql::open('host=postgresql-bdavid.alwaysdata.net dbname=bdavid_comhisto user=bdavid_comhisto password=motdepasse123$');
