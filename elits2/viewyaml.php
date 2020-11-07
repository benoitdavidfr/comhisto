<?php
/*PhpDoc:
name: viewyaml.php
title: viewyaml.php - affichage d'un fichier Yaml
doc: |
journal:
  17/8/2020:
    - création
*/
ini_set('memory_limit', '1G');
if (php_sapi_name() <> 'cli')
  set_time_limit (30);

require_once __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if (!isset($_GET['file']) || !is_file($_GET['file'])) {
  die("Erreur - le nom du fichier doit être fourni dans le paramètre file");
}
echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>$_GET[file]</title></head><body><pre>\n";
echo Yaml::dump(Yaml::parseFile($_GET['file']), 4, 2);
