-- investigations.sql

commune_carto(34968)
  id, nom_com

chef_lieu_carto(35466)
  insee_com, nom_chf

select id, nom_com from commune_carto where id not in (select insee_com from chef_lieu_carto)
-> 6 communes sans chef-lieu
  contenu:
    "id","nom_com"
    "55189","Fleury-devant-Douaumont"
    "55307","Louvemont-Côte-du-Poivre"
    "55050","Bezonvaux"
    "55139","Cumières-le-Mort-Homme"
    "55039","Beaumont-en-Verdunois"
    "55239","Haumont-près-Samogneux"

select * from chef_lieu_carto where insee_com not in (select id from commune_carto)
-> Les 45 ARM (20 à Paris, 9 à Lyon et 16 à Marseille) sont dans chef_lieu_carto sans être dans commune_carto

select distinct insee_com from chef_lieu_carto
where insee_com in (select insee_com from chef_lieu_carto group by insee_com having count(*)>1)
order by insee_com
  -> 459 communes ont plusieurs chefs-lieux

select insee_com, nom_chf from chef_lieu_carto
where insee_com in (select insee_com from chef_lieu_carto group by insee_com having count(*)>1)
order by insee_com
  -> 459 communes ont plusieurs chefs-lieux
  -> il semble s'agir des communes déléguées propres
  extrait:
    01015	Mairie d'Arboys en Bugey
    01015	Mairie d'Arbignieu
    01025	Mairie de Bâgé-la-Ville
    01025	Mairie de Bâgé-Dommartin

