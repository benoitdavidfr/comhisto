# Mise à disposition de ComHisto sous diverses formes

Outre la mise à disposition de fichiers GéoJSON, ComHisto est mis à disposition sous 3 formes :

- au travers de l'API en JSON/GéoJSON qui impose au client de connaitre la structure des données,
- comme données liées par la définition d'URI identifiant chaque entité, l'utilisation d'ontologies standards
  et la fourniture de données en JSON-LD,
- sous la forme d'une page Html par entité incluant une carte de l'entité, fournissant une IHM basique de navigation
  et intégrant de manière cachée mais standardisée l'enregistrement JSON-LD.

Les principales URI utilisées sont les suivantes :

- `https://comhisto.georef.eu/{statut}/{cinsee}/{ddebut}` est l'URI de la version d'une commune ({statut}='COM')
  ou d'une entité rattachée ({statut}='ERAT) portant le code Insee {cinsee} et débutant à la date {ddebut}  
  ex: https://comhisto.georef.eu/COM/01015/2016-01-01

- `https://comhisto.georef.eu/codeInsee/{cinsee}` est l'URI du code Insee {cinsee} correspondant à l'ensemble
  des versions portant ce code  
  ex: https://comhisto.georef.eu/codeInsee/01015

De plus les URL suivantes sont définies :

- `https://comhisto.geoapi.fr/{statut}/{cinsee}` retourne la version valide d'une commune ou d'une entité rattachée
  portant le code Insee {cinsee}, ou l'erreur Http 404 s'il n'existe pas de version valide pour ce code  
  ex:
   - https://comhisto.geoapi.fr/COM/01015 retourne l'entité https://comhisto.geoapi.fr/COM/01015/2016-01-01
   - https://comhisto.geoapi.fr/COM/01340 retourne une erreur 404 car il n'existe pas de version valide de commune
     portant ce code.
- `https://comhisto.geoapi.fr/{statut}/{cinsee}?date={date}` retourne la version d'une commune ou d'une entité rattachée
  portant le code Insee {cinsee} et existant à la date {date}, ou l'erreur Http 404 si cette version n'existe pas  
  ex:
   - https://comhisto.geoapi.fr/COM/01015?date=2019-01-01 retourne l'entité https://comhisto.geoapi.fr/COM/01015/2016-01-01
   - https://comhisto.geoapi.fr/COM/01340?date=2019-01-01 retourne une erreur 404 car il n'existe pas de version de commune
     portant ce code à cette date.


