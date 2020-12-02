<?php
/*PhpDoc:
name: isodate.php - définit checkIsoDate() qui Teste si une date vérifie un des formats dddd, dddd-dd, dddd-dd-dd et ..
title: lib/isodate.php
doc: |
journal: |
  2/12/2020:
    - création
*/
// Teste si la date vérifie un des formats dddd, dddd-dd, dddd-dd-dd et ..
// Si ok alors retourne soit .. soit la date au format dddd-dd-dd
// Sinon retourne ''
function checkIsoDate(string $isoDate, bool $test=false): string {
  if ($isoDate == '..')
    return '..';
  if (!preg_match('!^(\d\d\d\d)(\-(\d\d)(\-(\d\d))?)?$!', $isoDate, $matches))
    return '';
  if ($test)
    print_r($matches);
  $year = $matches[1];
  $month = ($matches[3] ?? 1);
  $day = ($matches[5] ?? 1);
  if (($month < 1) || ($month > 12)) {
    if ($test)
      echo "mois incorrect\n";
    return '';
  }
  if (($day < 1) || ($day > 31)) {
    if ($test)
      echo "jour incorrect\n";
    return false;
  }
  return sprintf('%s-%02d-%02d', $year, $month, $day);
}


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>checkIsoDate</title></head><body><pre>\n";
$date = checkIsoDate($_GET['d'] ?? '', true);
echo  $date ? $date : "KO";
