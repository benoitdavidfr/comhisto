<?php
/*PhpDoc:
name: isodate.php - définit checkIsoDate()
title: lib/isodate.php
doc: |
journal: |
  2/12/2020:
    - création
*/
function checkIsoDate(string $isoDate): bool { // Test si la date vérifie un des formats dddd, dddd-dd, dddd-dd-dd et ..
  if ($isoDate == '..')
    return true;
  if (!preg_match('!^(\d\d\d\d(\-(\d\d)(\-(\d\d))?)?)$!', $isoDate, $matches))
    return false;
  //print_r($matches);
  $month = ($matches[3] ?? 1);
  $day = ($matches[5] ?? 1);
  if (($month < 1) || ($month > 12)) {
    //echo "mois incorrect\n";
    return false;
  }
  if (($day < 1) || ($day > 31)) {
    //echo "jour incorrect\n";
    return false;
  }
  return true;
}
