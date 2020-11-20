# Mise à disposition de ComHisto sous la forme de cartes et d'une API (EN COURS)

L'URL d'accès est https://comhisto.georef.eu

Cet accès propose les 2 fonctionnalités suivantes:

- affichage d'une page Html par code Insee et par entité comprenant une carte Leaflet,
  incluant la définition du code Insee ou de l'entité en JSON-LD
  et permettant de naviguer entre différentes entités ;
- exposition sous la forme de données JSON/JSON-LD ou GeoJSON des entités.

Les pages Html sont proposées pour les URI suivantes:
- `https://comhisto.georef.eu/COM/{cinsee}/{ddebut}`

Les appels retournent soit un affichage Html, soit des données en JSON, en fonction du paramètre Accept de la requête HTTP.
Les documents Html contiennent d'une part une description JSON-LD de l'entité demandée conformément
au principe de publication des donnée liées et,
d'autre part, une carte Leaflet de l'entité.

L'API expose principalement les points d'accès suivants :

- `/` : racine fournissant une documentation
- `/COM/{cinsee}/{ddebut}` : version de la commune ayant pour code Insee {cinsee} et débutant à {ddebut}
  - exemple: https://comhisto.georef.eu/COM/01015/2016-01-01
- `/ERAT/{cinsee}/{ddebut}` : version de l'entité rattachée ayant pour code Insee {cinsee} et débutant à {ddebut}
  - exemple: https://comhisto.georef.eu/ERAT/01015/2016-01-01
- `/COM/{cinsee}/{ddebut}.json` : version de la commune ayant pour code Insee {cinsee} et débutant à {ddebut}, en imposant le format JSON au détriment du format Html
  - exemple: https://comhisto.georef.eu/COM/01015/2016-01-01.json
- `/COM/{cinsee}` : version valide de la commune ayant pour code Insee {cinsee}
  - exemple: https://comhisto.georef.eu/COM/01015
- `/ERAT/{cinsee}` : version valide de l'entité rattachée ayant pour code Insee {cinsee}
  - exemple: https://comhisto.georef.eu/ERAT/01015
- `/COM/{cinsee}.json` : version valide de la commune ayant pour code Insee {cinsee}, en imposant le format JSON au détriment du format Html
  - exemple: https://comhisto.georef.eu/COM/01015.json
- `/codeInsee/{cinsee}` : liste des versions d'entités ayant pour code Insee {cinsee}
  - exemple: https://comhisto.georef.eu/codeInsee/01015
- `/codeInsee/{cinsee}/{ddebut}` : liste des versions d'entités ayant pour code Insee {cinsee} et débutant à {ddebut}
  - exemple: https://comhisto.georef.eu/codeInsee/01015/2016-01-01
  
Les URL en https://comhisto.georef.eu correspondent aux URI des objets décrits.

L'API expose aussi avec l'URL de base https://comhisto.geoapi.fr
3 points d'entrée pour obtenir la description d'entités correspondant à une date donnée:

- `https://comhisto.geoapi.fr/COM/{cinsee}?date={date}` : version de la commune ayant pour code Insee {cinsee} et existant au {date}
  - exemple: https://comhisto.geoapi.fr/COM/01015?date=2015-01-01
- `https://comhisto.geoapi.fr/ERAT/{cinsee}?date={date}` : version de l'entité rattachée ayant pour code Insee {cinsee} et existant au {date}
  - exemple: https://comhisto.geoapi.fr/ERAT/01015?date=2015-01-01
- `https://comhisto.geoapi.fr/ERAT/{cinsee}?date={date}` : version des entités ayant pour code Insee {cinsee} et existant au {date}
  - exemple: https://comhisto.geoapi.fr/codeInsee/01015?date=2015-01-01

Ces URL ne correspondent pas à des URI d'objets.

