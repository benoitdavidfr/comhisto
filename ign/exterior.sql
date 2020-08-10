/*PhpDoc:
name: exterior.sql
title:  construction des limites extérieures des communes, cad les limites avec la mer ou l'étranger
doc: |
  construit les limites extérieures affectées à chaque elt (c. simple non rattachante, e. ratt. + compl.)
  L'algorithme consiste à:
    1) agréger les communes en polygones
    2) fabriquer les polygones extérieurs par différence avec un rectangle englobant par zone
    3) déduire la limite extérieure
    4) sur FXX qui pose un problème de performances, décomposer cette limite en anneaux, puis en segments
    5) réaffecter chaque segment à un code insee par croisement géométrique avec elt
    6) restructurer les segments en limites par elt
    7) ajouter les DOM traités plus simplement car pas d'enjeu de perf.

  Tables en entrée:
    - commune_carto - communes AE2020COG
    - eadmin - Elément administif = c. simples non rattachantes + entités rattachées + entités complémentaires

  Tables en sortie:
    - eltextlim - limites extérieures décomposées par elt (c. simple non rattachante, e. ratt. + compl.)
      avec géométrie sous la forme de LineString et de MultiLineString

  Tables temporaires:
    - univers - Un rectangle englobant par grande zone géographique
    - comunionfxxdom - Union géométrique des communes, 1 pour FXX et 1 pour les DOM
    - extfxx - Extérieur de FXX en 2 polygones, 1 pour l'extérieur et l'autre pour l'enclave espagnole de Llivia
    - extfxxring - decomposition du polygone en anneaux intérieurs et extérieurs- 28
    - extfxxsegs - decomposition de chaque anneau en segments, le plus grand compte 152.434 points / 204626

journal: |
  8-9/8/2020:
    - adaptation à comhisto du fichier rpicom/rpigeo/exterior.sql
*/

-- 1) Vérifier que les extérieurs sont constitués pour les DOM d'un seul polygone (pas d'enclave)
-- et pour la métropole d'un extérieur et du polygone correspondant à l'enclave de Llivia

-- 1a) Un rectangle englobant par grande zone géographique
drop table if exists univers;
create table univers(
  num serial,
  iso3 char(3), -- code ISO 3166-1 alpha 3
  box geometry(POLYGON, 4326)
);
comment on table univers is 'Un rectangle englobant par grande zone géographique';
insert into univers(iso3, box) values
('FXX', ST_MakeEnvelope(-6, 41, 10, 52, 4326)),
('GLP', ST_MakeEnvelope(-62, 15.8, -61, 16.6, 4326)),
('MTQ', ST_MakeEnvelope(-61.3, 14.3, -60.8, 15, 4326)),
('GUF', ST_MakeEnvelope(-55, 2, -51, 6, 4326)),
('REU', ST_MakeEnvelope(55, -22, 56, -20, 4326)),
('MYT', ST_MakeEnvelope(44, -14, 46, -12, 4326));

-- 1b) Union des communes en 1 tuple pour la métropole et 1 pour les DOM
drop table if exists comunionfxxdom;
create table comunionfxxdom as
  select 'FXX' as id, ST_Union(wkb_geometry) as geom
  from commune_carto
  where substring(id, 1, 2)<>'97'
union
  select 'DOM' as id, ST_Union(wkb_geometry) as geom
  from commune_carto
  where substring(id, 1, 2)='97';
comment on table comunionfxxdom is 'Union géométrique des communes, 1 pour FXX et 1 pour les DOM';

-- 1c) L'extérieur des communes pour chaque gde zone géo.,
-- permettra de créer la limite extérieure cad la limite du territoire avec la mer ou l'étranger
-- je distingue FXX et DOM pour corriger les erreurs et optimiser le traitement
drop table if exists exterior;
create table exterior as
  select num, iso3, ST_Difference(box, geom) as geom
  from univers, comunionfxxdom
  where iso3='FXX' and id='FXX'
union
  select num, iso3, ST_Difference(box, geom) as geom
  from univers, comunionfxxdom
  where iso3<>'FXX' and id='DOM';
comment on table exterior is 'Extérieur pour chaque gde zone géo., cad Polygone/MultiPolygone correspondant à la mer et l''étranger.';

-- le fait que l'extérieur de chaque DOM corresponde à 1 polygone
-- et que l'extérieur de FXX corresponde à 2 polygones valide la cohérence la couverture de commune_carto
-- la table peut être visualisée avec QGis
select iso3, ST_AsText(geom) from exterior;

-- polygones de l'extérieur FXX - decomposition du MultiPolygon en Polygones
-- contient 2 polygones, le 1er l'extérieur et le 2nd l'enclave espagnole de Llivia
drop table if exists extfxx;
create table extfxx as
  select npol, ST_GeometryN(geom, npol) as geom
  from exterior, generate_series(1,100) npol
  where iso3='FXX' and npol <= ST_NumGeometries(geom);
comment on table extfxx is 'Extérieur de FXX en 2 polygones, 1 pour l''extérieur et l''autre pour l''enclave espagnole de Llivia';

-- décomposition des polygones en anneaux intéreurs et extérieurs - 28
drop table if exists extfxxring;
create table extfxxring as
  select nr, ST_InteriorRingN(geom, nr) as geom
  from extfxx, generate_series(1,100) nr
  where nr <= ST_NumInteriorRings(geom)
union
  select 0, ST_ExteriorRing(geom) as geom
  from extfxx;

select nr, ST_NPoints(geom), ST_AsText(geom) from extfxxring;

-- décomposition de chaque anneau en segments, le plus grand compte 152.434 points / 204626 / 10'
drop table if exists extfxxsegs;
select 'Debut:', now();
create table extfxxsegs as
select nr, npt as nseg, ST_MakeLine(ST_PointN(geom, npt),ST_PointN(geom, npt+1)) as geom
from extfxxring, generate_series(1,160000) npt
where npt < ST_NumPoints(geom);
create index extfxxsegs_gist on extfxxsegs using gist(geom);
select 'Fin:', now();

select nr, nseg, ST_AsText(geom) from extfxxsegs;

-- reaffectation de chaque segment à un elt (c. simple non rattachante, e. ratt. + compl.) / 1407
-- génère des LineString et des MultiLineString / 1519 / 6'
drop table if exists eltextlim;
select 'Debut:', now();
create table eltextlim as
  select 'FXX' iso3, id, ST_LineMerge(ST_Collect(s.geom)) geom -- FXX
  from eadmin ea, extfxxsegs s
  where s.geom && ea.geom and ST_Dimension(ST_Intersection(s.geom, ea.geom))=1
  group by id
union
  select iso3, id, ST_LineMerge(ST_Intersection(ea.geom, ex.geom)) geom -- DOM
  from eadmin ea, exterior ex
  where ex.iso3<>'FXX' and ea.id like '_97%' and ea.geom && ex.geom and ST_Dimension(ST_Intersection(ea.geom, ex.geom))=1;
select 'Fin:', now();
comment on table eltextlim is 'Limite ext., cad avec la mer ou l''étranger, de chaque elt (coms, er, ecomp).';

select id, ST_AsText(geom) from eltextlim;

-- Suppression des tables temporaires
drop table if exists extfxxsegs;
drop table if exists extfxxring;
drop table if exists extfxx;
drop table if exists exterior;
drop table if exists comunionfxxdom;
drop table if exists univers;
