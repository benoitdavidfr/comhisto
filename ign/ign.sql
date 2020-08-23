/*PhpDoc:
name: ign.sql
title: ign.sql - fabrication des éléments administratifs à partir de AE2020COG
doc: |
  Ecriture de requêtes PostGis pour
    1) déduire les limites entre communes et entités rattachées fournies en polygone par l'IGN
    2) stocker ces limites en les partageant entre communes et entités rattachées
    3) définir comme vue matérialisée les communes à partir de ces limites
    4) définir une géométrie simplifiée des limites et en conséquence des communes
  Dans cette version je gère les bugs IGN.
  Ce script utilise sous-traite une partie du traitement à:
    - errorcorr.sql qui vérifie la cohérence topologique et corrige les erreurs topologiques dans les données IGN en entrée
    - exterior.sql qui construuit les limites extérieures des communes, cad les limites avec la mer ou l'étranger
    - ctrlqual.sql qui effectue une comparaison entre admin et eadming3 pour détecter d'éventuelles erreurs

  Dans un premier temps on constitue les données initiales à partir des couches commune_carto et entite_rattachee_carto de AE2020COG
  corrigées dans ~/html/data/aegeofla/makefra.php des bugs initiaux détectées
  L'étape préliminaire consiste à vérifier les données IGN et éventuellement à les corriger dans errorcorr.sql.

  L'algorithme est ensuite le suivant:
    1) fabriquer une couche d'éléments (elt) correspondant à un pavage du territoire, cad ne s'intersectant pas 2 à 2
      et dont l'union couvre l'ensemble du territoire, constituée de:
      a) les c. simples non rattachantes
      b) les entitées rattachées (erat)
      c) les parties des c. simples rattachantes non couvertes par des erat, appelées entités complémentaires (ou ecomp)
      Les éléments correspondent aux faces du graphe topologique administratif fusionnant les communes simples et les erat.
    2) générer les limites entre ces éléments en calculant leur intersection 2 à 2
    3) générer les limites extérieures des éléments - voir exterior.sql
    4) peupler lim à partir des 2 ens. de limites précédemment constitués

  Pour que le traitement ne génère pas d'erreurs, il faut définir les pré-conditions et les tester.
  En l'espèce, la pré-condition est que les éléments + l'extérieur constituent un pavage de l'univers.
  Le test est effectué dans errorcorr.sql qui génère plusieurs tables réutilisées par la suite

  Tables en entrée:
    - commune_carto
    - eratcorrigee - produit dans errorcorr.sql
    - ecomp - prduit dans errorcorr.sql

  Tables peuplées en sortie:
    - lim - limite entre éléments ou avec l'extérieur
    - eadminpolg3 - éléments administratifs généralisés sous forme de polygones
    - eadming3 - éléments administratifs généralisés agrégés par eid

  Tables temporaires:
    - eadmin - éléments administratifs
    - eltint - intersections entre éléments
    - eltinterror
    - eltdecrattachante
    - limcrattachante

journal: |
  23/8/2020:
    - dégénéralisation de qqs limites qui posent pbs
  9/8/2020:
    - adapattion à comhisto
    - la généralisation génère beaucoup d'erreurs
  8/8/2020:
    - adaptation à comhisto du fichier rpigeo3.sql de rpicom/rpigeo
    - abandon du schema général PostGis
  20/6/2020:
    - exploit d'une nlle version de errorcorr.sql
  15/6/2020
    8:50
      - reste au moins 3 erreurs dans la construction des limites des elts
        Erreurs:
         - 49013 COMD de 49228
         - 52054 COMD de 52008
         - 65116 COMS
      - reprendre le code rpigeo2.sql pour la suite
  14/6/2020
    - écriture de errorcorr.sql qui teste la pré-condition et effectue des corrections nécessaires
    - écriture de errorcorrsup.sql qui rédéfinit qqs éléments qui posent problèmes et que je ne sais pas corriger autrement
  13/6/2020
    - suite de rpigeo2.sql
    - utilise schema.sql
tables:
*/

----------------------------------------------------------------------------------
-- 0) tester les pré-conditions et corriger les données en entrée -> errorcor.sql
----------------------------------------------------------------------------------

-----------------------------------------------------
-- 1) fabriquer la table des éléments administratifs
-----------------------------------------------------
-- 1) fabrication de la table des éléments en substituant aux c. rattachantes leurs entités rattachées + complémentaires. / 37237
-- schema: id, crat, geom ; l'id est la concaténation du type (s, r ou c) avec le code Insee
-- attention: les complémentaires ne sont pas agrégées par id
drop table if exists eadmin;
create table eadmin as
  -- les c. s. non rattachantes
  select concat('s',id) as id, null as crat, wkb_geometry as geom
  from commune_carto cc
  where cc.id not in (select crat from eratcorrb)
union
  -- les e. rattachées / type vaut COMA, COMD ou ARM
  select concat('r',id) as id, crat, geom from eratcorrb
union
  -- les complémentaires
  select concat('c', substr(id, 1, 5)) as id, crat, geom from ecomp;
comment on table eadmin is 'Elément administif = c. simples non ratt. + entités rattachées + entités complémentaires';
create index eadmin_geom_gist on eadmin using gist(geom);

select count(*) from eadmin;
-- 37237

----------------------------------------------------------------------------------
-- 2) générer les limites entre ces éléments en calculant leur intersection 2 à 2
----------------------------------------------------------------------------------
-- 2a) calcul des intersections entre éléments en s'assurant que ces limites sont linéaires / 109 135
-- prend 4'10" sur Mac
drop table if exists eltint;
select 'Début:', now();
create table eltint as 
select e1.id id1, e2.id id2, ST_LineMerge(ST_Intersection(e1.geom, e2.geom)) geom
from eadmin e1, eadmin e2
where e1.geom && e2.geom and e1.id < e2.id and ST_Dimension(ST_Intersection(e1.geom, e2.geom)) > 0;
select 'Fin:', now();
create index eltint_geom_gist on eltint using gist(geom);

-- 2b) liste des intersections non linéaires, fait partie des tests de pré-condition
select id1, id2, ST_Dimension(geom), ST_AsText(geom) from eltint where ST_Dimension(geom)<>1;
-- -> vide

-- 0
drop table if exists eltinterror;
create table eltinterror as
select id1, id2, numgeom, ST_GeometryN(geom, numgeom) geom
from eltint, generate_series(1,1000) numgeom
where ST_Dimension(geom)<>1 and numgeom <= ST_NumGeometries(geom);

select id1, id2, numgeom, ST_AsText(geom) from eltinterror where ST_Dimension(geom)=2;
select id1, id2, numgeom, ST_AsGeoJSON(geom) from eltinterror where ST_Dimension(geom)=2;

-- -> 0 rows OK

----------------------------------------------
-- 3) Calcul et ajout des limites extérieures 
----------------------------------------------
-- 3a) -> exterior.sql -> produit la table eltextlim
-- 3b) Intégration des limites extérieures dans les limites des éléments // 1524
insert into eltint(id1, id2, geom)
  select id, concat('iso',iso3), geom
  from eltextlim;

-- 3c) définition de la table des limites

/*PhpDoc: tables
name: lim
title: lim - limite entre entités administratives ou avec l'extérieur
database: [ ae2020cog ]
*/
drop table if exists lim;
create table lim(
  num serial primary key, -- le num. de limite
  id1 char(6) not null, -- id d'un des 2 eadmin
  id2 char(6) not null, -- id de l'autre eadmin
  geom  geometry(LINESTRING, 4326), -- la géométrie de la limite telle que définie dans la source IGN, comme LINESTRING
  simp3 geometry(LINESTRING, 4326)  -- la géométrie simplifiée de la limite avec une résolution de 1e-3 degrés (cad env. 100 m)
);
comment on table lim is 'Limite entre communes ou avec l''extérieur';
create index lim_geom_gist on lim using gist(geom);
create index lim_id1 on lim using hash(id1);
create index lim_id2 on lim using hash(id2);

-- 3d) peuplement de la table lim à partir de eltint en décomposant les Multi* en LineString / 111125
truncate table lim;
insert into lim(id1, id2, geom)
  select id1, id2, geom
  from eltint
  where GeometryType(geom)='LINESTRING'
union
  select id1, id2, ST_GeometryN(geom, n) as geom
  from eltint, generate_series(1,100) n
  where GeometryType(geom)<>'LINESTRING'
    and n <= ST_NumGeometries(geom)
    and GeometryType(ST_GeometryN(geom, n))='LINESTRING';

----------------------------------------------
-- 4) Test de reconstruction des polygones des eadmin
----------------------------------------------

drop table if exists lim2;
create table lim2 as
  select id1 as eid, geom from lim
union
  select id2 as eid, geom from lim where id2 not like 'iso%';
create index lim2_eid on lim2 using hash(eid);

select 'Début:', now();
drop table if exists eadminpol;
create table eadminpol as
select eid, (ST_Dump(ST_Polygonize(geom))).geom as geom
from lim2
group by eid;
select 'Fin:', now();
    
----------------------------------------------
-- 5) Simplification des limites et construction des polygones correspondants des eadmin
----------------------------------------------

-- 5a) création de la géométrie généralisée
update lim set simp3=ST_SimplifyPreserveTopology(geom, 0.001);

drop table if exists lim2;
create table lim2 as
  select id1 as eid, simp3 from lim
union
  select id2 as eid, simp3 from lim where id2 not like 'iso%';
create index lim2_eid on lim2 using hash(eid);

-- génération de la table des polygones à partir des limites généralisées - 37237
drop table if exists eadminpolg3;
create table eadminpolg3 as
select eid, (ST_Dump(ST_Polygonize(simp3))).geom
from lim2
group by eid;


drop table if exists polg3error;
create table polg3error as
select id from eadmin where id not in (select eid from eadminpolg3) order by id;
-- 52 polygones manquants

update lim set simp3=geom
where id1 in (select id from polg3error) or id2 in (select id from polg3error);

-- dégénéralisation de s51537 qui sinon pose pbs
update lim set simp3=geom where id1='s51537';
-- dégénéralisation de la limite entre s70041 et s70100
update lim set simp3=geom where id1='s70041' and id2='s70100';
-- dégénéralisation de la limite entre s02095 et s02703
update lim set simp3=geom where id1='s02095' and id2='s02703';

drop table if exists lim2;
create table lim2 as
  select id1 as eid, simp3 from lim
union
  select id2 as eid, simp3 from lim where id2 not like 'iso%';
create index lim2_eid on lim2 using hash(eid);

-- génération de la table des polygones à partir des limites généralisées - 37237
drop table if exists eadminpolg3;
create table eadminpolg3 as
select eid, (ST_Dump(ST_Polygonize(simp3))).geom
from lim2
group by eid;
create index eadminpolg3_geom_gist on eadminpolg3 using gist(geom);

drop table if exists polg3error;
create table polg3error as
select id from eadmin where id not in (select eid from eadminpolg3) order by id;
-- 2 erreurs restantes

   id   
--------
 r52054
 s52107

insert into eadminpolg3(eid, geom) select id, geom from eadmin where id in ('r52054','s52107');

drop table if exists polg3error;
create table polg3error as
select id from eadmin where id not in (select eid from eadminpolg3) order by id;
-- 0 erreurs

select count(*) from eadminpolg3;
-- 37397

select eid from eadminpolg3 where eid not in (select id from eadmin) order by eid;
-- 0 erreurs

-- les entités en plusieurs parties génèrent plusieurs enregistrements
select eid, count(*) nbre
from eadminpolg3
group by eid
having count(*) > 1;

-- Lorsqu'il y a un trou dans un polygone, il est affecté à la fois à la c. du trou et à celle qui le contient'

alter table eadminpolg3 add num serial;
update eadminpolg3 set geom=ST_MakeValid(geom);

delete from eadminpolg3 where num in (
  -- les pol à supprimer sont ceux qui à la fois touchent un autre polygone ayant même eid
  -- et égalent un autre polygone avec un eid différent
  select del.num
  from eadminpolg3 t, eadminpolg3 del, eadminpolg3 eq
  where t.geom && del.geom and ST_Touches(t.geom, del.geom) and t.eid=del.eid
    and del.geom && eq.geom and ST_Equals(del.geom, eq.geom) and del.eid <> eq.eid  
);
-- DELETE 29
  
drop table if exists eadming3;
create table eadming3 as
  select eid, ST_Collect(geom) as geom
  from eadminpolg3
  group by eid;
-- 37231 ligne(s) affectée(s).
