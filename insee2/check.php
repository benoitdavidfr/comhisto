<?php
/*PhpDoc:
name: check.php
title: check.php - vérification de la conformité d'un fichier Yaml à son schéma
doc: |
journal: |
  5/11/2020:
    - création
*/
ini_set('memory_limit', '2G');

if (php_sapi_name() == 'cli') {
  if ($argc <> 2)
    die("usage: php $argv[0] {docid}\n");
  $params = ['docid'=> $argv[1],];
}
else {
  $params = ['docid'=> 'comhisto/insee2/com20200101'];
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>insee</title></head><body>\n",
       "<h3>vérification du fichier $params[uid]</h3><pre>\n";
}

require_once __DIR__.'/../../../inc.php';
$doc = new_doc($params['docid'], 'pub');
if ($doc->checkSchemaConformity('/')->ok())
  exit(0);
else
  exit(1);
