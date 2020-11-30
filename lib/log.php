<?php
/*PhpDoc:
name: log.php
title: log.php - affichage des logs
includes: [ accesscntrl.inc.php, secretconfig.inc.php, ../lib/mysql.inc.php ]
doc: |
  Dimensions d'analyse:
  - le temps, particulièrement par jour
  - mode d'accès : IP, login
  - appli utilisée
  - accès autorisé/refusé
  - localisation géographique des requêtes
journal: |
  30/11/2020:
    adaptation à comhisto
  1/11/2019:
    adaptation nlle version
  24/6/2017
    création
*/
require_once __DIR__.'/config.inc.php';
require_once __DIR__.'/mysql.inc.php';

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>log</title></head><body>\n";

$mysqlParams = config('mysqlParams')[$_SERVER['HTTP_HOST']];
MySQL::open($mysqlParams);

if (!isset($_GET['action'])) {
  echo "Nbre de requêtes par jour et par application :\n";
  $sql = "
    ( select date(logdt) d, 'tile' app, count(*) nbre from log
      where request_uri like '%/shomgt/tile.php%'
      group by date(logdt)
    )
    union
    ( select date(logdt) d, 'wms' app, count(*) nbre from log
      where request_uri like '%/shomgt/wms.php%'
      group by date(logdt), app
    )
    union
    ( select date(logdt) d, 'xxx' app, count(*) nbre from log
      where request_uri not like '%/shomgt/tile.php%'
        and request_uri not like '%/shomgt/wms.php%'
      group by date(logdt), app
    )
   ";
  foreach (MySQL::query($sql) as $tuple) {
    $logs[$tuple['d']][$tuple['app']] = $tuple['nbre'];
  }
  ksort($logs);
  echo "<table border=1><th></th><th>tile</th><th>wms</th><th>autres</th>";
  foreach ($logs as $d => $log)
    echo "<tr><td>$d</td>",
         "<td align='right'>",(isset($log['tile'])? $log['tile'] : ''),"</td>",
         "<td align='right'>",(isset($log['wms'])? $log['wms'] : ''),"</td>",
         "<td align='right'>",(isset($log['xxx'])? $log['xxx'] : ''),"</td>",
         "</tr>\n";
  echo "</table>\n";
  echo "<h3>Menu</h3><ul>\n",
       "<li><a href='?action=last'>Les 100 dernières requêtes hors consultation du log</a><br>\n",
       "<li><a href='?action=refused'>Les 100 dernières requêtes refusées</a><br>\n",
       "<li><a href='?action=statip'>Statistiques sur les IP autorisées par protocole</a><br>\n",
       "</ul>\n";
  die();
}

if ($_GET['action']=='last') {
  echo "Les 100 dernières requêtes hors consultation du log :\n";
//  $cols = ['logdt','ip','referer','login','user','request_uri','phpserver','access'];
  $cols = ['logdt','ip','referer','login','user','host','request_uri','access'];
  $sql = "SELECT ".implode(',',$cols)." FROM `log`
      where request_uri not like '%/log.php%'
      ORDER BY `log`.`logdt` DESC
      LIMIT 0 , 99";
//  echo "<pre>",$sql,"</pre>\n";
  echo "<table border=1>",
       "<th>",implode('</th><th>',$cols),"</th>\n";
  foreach (MySQL::query($sql) as $tuple) {
    echo "<tr>";
    foreach ($cols as $c)
      echo "<td>$tuple[$c]</td>";
    echo "</tr>\n";
  }
  echo "</table>\n";
  die();
}

if ($_GET['action']=='refused') {
  echo "Les 100 dernières requêtes refusées :\n";
//  $cols = ['logdt','ip','referer','login','user','request_uri','phpserver','access'];
  $cols = ['logdt','ip','referer','login','user','request_uri','access'];
  $sql = "SELECT ".implode(',',$cols)." FROM `log`
      where access='F'
      ORDER BY `log`.`logdt` DESC
      LIMIT 0 , 99";
//  echo "<pre>",$sql,"</pre>\n";
  echo "<table border=1>",
       "<th>",implode('</th><th>',$cols),"</th>\n";
  foreach (MySQL::query($sql) as $tuple) {
    echo "<tr>";
    foreach ($cols as $c)
      echo "<td>$tuple[$c]</td>";
    echo "</tr>\n";
  }
  echo "</table>\n";
  die();
}

if ($_GET['action']=='statip') {
  echo "<h2>Liste des IP (referer pour tile) ayant accédé à un service avec nbre d'accès</h2>\n";
  echo "Analyse :<ul>
    <li> c'est surtout le WMS qui est utilisé principalement depuis MTES et AFB
    <li>l'utilisation de tile par carte.snosan.fr est significatif
    <li>peu de téléchargements
    </ul>\n";
  
  $sql = "select logdt, ip, request_uri, referer from log where access='T' and login is null";
  echo "<table border=1>\n";
  $pattern = '!^/shomgt/(wms|tile|mapwcat|maplib|gtdl|log|index|index2|gazetteer|login|accesscntrl.inc)\.php!';
  foreach (MySQL::query($sql) as $tuple) {
    if (!isset($dtmin))
      $dtmin = $tuple['logdt'];
    else
      $dtmin = min($tuple['logdt'], $dtmin);
    if (!isset($dtmax))
      $dtmax = $tuple['logdt'];
    else
      $dtmax = max($tuple['logdt'], $dtmax);
    $protocol = '';
    if ($tuple['request_uri'] == '/shomgt/')
      $protocol = 'shomgt';
    elseif (preg_match($pattern, $tuple['request_uri'], $matches))
      $protocol = $matches[1];
    else
      echo "<tr><td>$tuple[ip]</td><td>$protocol</td><td>$tuple[request_uri]</td></tr>\n";
    if ($protocol == 'tile') {
      if (!isset($protocols[$protocol][$tuple['referer']]))
        $protocols[$protocol][$tuple['referer']] = 1;
      else
        $protocols[$protocol][$tuple['referer']]++;
    }
    else {
      if (!isset($protocols[$protocol][$tuple['ip']]))
        $protocols[$protocol][$tuple['ip']] = 1;
      else
        $protocols[$protocol][$tuple['ip']]++;
    }
  }
  echo "</table>\n";
  echo "période : $dtmin - $dtmax<br>\n";
  echo "<pre>protocols="; print_r($protocols);
}


/*
  drop table if exists log;
  create table log(
    logdt datetime not null comment "date et heure",
    ip varchar(25) not null comment "adresse IP de l'appelant",
    referer varchar(255) comment "referer appelant",
    login varchar(255) comment "login appelant éventuel issu du cookie",
    user varchar(255) comment "login appelant éventuel issu de l'authentification HTTP",
    request_uri longtext comment "requete appelée sans le host",
    phpserver longtext comment "variable Php $_SERVER encodé en JSON",
    access char(1) comment "acces accordé T ou refusé F"
  );
*/
