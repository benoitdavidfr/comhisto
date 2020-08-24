/*PhpDoc:
name: ctrlqual.sql
title: ctrlqual.sql - contrôle des eadming3
doc: |
  Dégénéralisation de qqs limites
journal: |
  23/8/2020:
    - création
*/

-- eadmin n'est pas agrégé sur id - eadmin2 l'est
drop table if exists eadmin2;
create table eadmin2 as
  select id, crat, geom from eadmin where substr(id, 1, 1)<>'c'
union
  select id, crat, ST_Collect(geom) from eadmin where substr(id, 1, 1)='c' group by id, crat;
-- SELECT 37231

-- vérif identité des identifiants
select id from eadmin2 where id not in (select eid from eadming3);
select eid from eadming3 where eid not in (select id from eadmin2);

select e.id, ST_HausdorffDistance(e.geom, g.geom) dist
from eadmin2 e, eadming3 g
where e.id=g.eid and ST_HausdorffDistance(e.geom, g.geom) > 0.003
order by ST_HausdorffDistance(e.geom, g.geom) desc;

   id   |        dist         
--------+---------------------
 s67447 | 0.00400020343748866
 s66223 | 0.00335800650386424
(2 rows)

-- s67447 est dû à un polygone supprimé
-- s66223 est dû à un polygone supprimé
