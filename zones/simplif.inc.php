<?php
/*PhpDoc:
name: simplif.inc.php
title: simplif.inc.php - def. des simplifications
doc: |
  L'historique contient 6 dissolutions et 6 créations.
  Les 6 dissolutions (seDissoutDans/reçoitUnePartieDe) sont assimilées ici à des fusions (fusionneDans/absorbe) en définissant dans
  Simplif::DISSOLUTIONS pour chaque commune dissoute la commune principale d'absorption.

  De même les 6 créations de commune à partir d'autres communes (crééeAPartirDe/contribueA) sont assimiliées à des scissions 
  (crééeCommeSimpleParScissionDe/seScindePourCréer) en définissant dans Simplif::CREATIONS pour chaque commune créée la commune
  principale dont est issue la commune créée.
journal: |
  16/8/2020:
    - fork de defelt.php
*/

// Définition des simplifications
class Simplif {
  // [cinsee se dissolvant => cinsee on considère que la dissolution est effectuée]
  const DISSOLUTIONS = [
    '08227' => '08454', // le hameau de Hocmont (08227) est maintenant sur la commune de Touligny (08454)
    '45117' => '45093', // le hameau Creuzy (45117) est maintenant sur la commune de Chevilly (45093)
    '51606' => '51369', // Verdey (51606) -> Mœurs-Verdey (51369)
    '51385' => '51440', // Moronvilliers (51385) -> Pontfaverger-Moronvilliers (51440)
    '60606' => '60509',
    '77362' => '77444',
  ];
  // cinsee se créant => cinsee de la principale commune contributive
  const CREATIONS = [
    '27701' => '27528', // Val-de-Reuil (27701) est principalement issue de Le Vaudreuil (27528)
    '29302' => '29231',
    '38567' => '38422',
    '46339' => '46251',
    '57766' => '57206',
    '91692' => '91122',
  ];
};