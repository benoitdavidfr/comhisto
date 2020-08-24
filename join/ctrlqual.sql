/*PhpDoc:
name: ctrlqual.sql
title: ctrlqual.sql - contrôle qualité de comhisto(g3) en sortie de join.php
doc: |
  Les tables comhisto et comhistog3 sont générées par le script join.php en fonctiin du paramétrage du script.
  Les requêtes ci-dessous vérifient la cohérence de comhisto(g3) par rapport aux tables commune_carto et entite_rattachee_carto
journal: |
  21/8/2020:
    - la comparaison des géométries montre des erreurs dues notamment à la manière dont sont gérées les ecomp qui est à revoir
      voir 23094.yaml
  20/8/2020:
    - modifs sur histo.inc.php + zones.inc.php + delelt.php
  19/8/2020:
    - création
*/

-- 1) ttes les COMS et les ERAT de COG2020 doivent être définies dans comhisto

-- 1a) COMS de COG2020 absentes de comhisto comme valide
select id, nom_com from commune_carto
where id not in (
  SELECT cinsee FROM comhistog3 where type='s' and fin is null
)
order by id;

  id   |    nom_com     
-------+----------------
 27528 | Le Vaudreuil
 27701 | Val-de-Reuil
 97306 | Mana
 97361 | Awala-Yalimapo

-- 1b)
SELECT cinsee, dnom FROM comhistog3
where type='s' and fin is null
and cinsee not in (select id from commune_carto);

 cinsee | dnom 
--------+------
(0 rows)

-- 1c) ERAT de COG2020 absentes dans comhistog3 comme valide
select id, nom_com, insee_ratt from entite_rattachee_carto
where id not in (
  SELECT cinsee FROM comhistog3 where type='r' and fin is null
)
order by id;

 id | nom_com | insee_ratt 
----+---------+------------
(0 rows)

-- 1d)
SELECT cinsee, dnom FROM comhistog3
where type='r' and fin is null
and cinsee not in (select id from entite_rattachee_carto);

 cinsee | dnom 
--------+------
(0 rows)

-- 2) les géométries différentes
-- 2a) COMS de COG2020 et comhistog3
select c.id, c.nom_com, ST_HausdorffDistance(c.wkb_geometry, h.geom) dist
from commune_carto c, comhistog3 h
where c.id=h.cinsee and h.type='s' and h.fin is null and ST_HausdorffDistance(c.wkb_geometry, h.geom) > 0.001
order by ST_HausdorffDistance(c.wkb_geometry, h.geom) desc;
-- 24/8/2020 9:36 g3 après gestion de 49220 + 70285

-- 24/8/2020 9:16 g3 après gestion de 02738
  id   |           nom_com            |        dist         
-------+------------------------------+---------------------
 49220 | Morannes sur Sarthe-Daumeray |  0.0268586935626057
 70285 | Héricourt                    |  0.0252397108741424
 52094 | Vals-des-Tilles              |  0.0246617491577559
 59350 | Lille                        |  0.0245413494730811
 55117 | Clermont-en-Argonne          |  0.0242745544891015
 08366 | Rocquigny                    |  0.0213093554895492
 69123 | Lyon                         |  0.0199183933303438
 19275 | Ussel                        |  0.0184248209361709
 67447 | Schiltigheim                 | 0.00400020343748866
 66223 | Villefranche-de-Conflent     | 0.00335800650386424
 46177 | Loubressac                   | 0.00287753089442511
 22203 | Plœuc-L'Hermitage            | 0.00185781439649249
 85238 | Saint-Laurent-sur-Sèvre      | 0.00183029025020266
 22171 | Plaintel                     | 0.00135641609029338
(14 rows)

-- 24/8/2020 4:50 g3 après gestion de Champlitte
  id   |             nom_com              |        dist         
-------+----------------------------------+---------------------
 71270 | Mâcon                            |   0.049487655140749
 52242 | Haute-Amance                     |  0.0439561218085711
 86161 | Moncontour                       |  0.0325042457782051
 39130 | Nanchez                          |  0.0285029621692209
 52182 | Éclaron-Braucourt-Sainte-Livière |  0.0280869760246617
 02738 | Tergnier                         |  0.0270578135888693
 49220 | Morannes sur Sarthe-Daumeray     |  0.0268586935626057
 70285 | Héricourt                        |  0.0252397108741424
 52094 | Vals-des-Tilles                  |  0.0246617491577559
 59350 | Lille                            |  0.0245413494730811
 55117 | Clermont-en-Argonne              |  0.0242745544891015
 08366 | Rocquigny                        |  0.0213093554895492
 69123 | Lyon                             |  0.0199183933303438
 19275 | Ussel                            |  0.0184248209361709
 67447 | Schiltigheim                     | 0.00400020343748866
 66223 | Villefranche-de-Conflent         | 0.00335800650386424
 46177 | Loubressac                       | 0.00287753089442511
 22203 | Plœuc-L'Hermitage                | 0.00185781439649249
 85238 | Saint-Laurent-sur-Sèvre          | 0.00183029025020266
 22171 | Plaintel                         | 0.00135641609029338
(20 rows)

