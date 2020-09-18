<?php
/*PhpDoc:
name: verifcheflieu.php
title: verifcheflieu.php - verification que les chefs-lieux sont situés géographiquement dans l'eadming3 correspondant
doc: |
  Dans fcomhisto je découpe les eadming3 par l'algo de Voronoi en fonction des points des chefs-lieux
  Si ces points de chefs-lieux ne sont pas situés dans cet eadming3 que je découpe alors ce découpage ne fonctionne pas
  Ce script vérifie si ces chefs-lieux sont bien situés dedans.
  Pour cela,
    - je lit histolitp
    - j'en déduis les versions valides de code Insee
journal: |
  18/9/2020:
    - création
*/
