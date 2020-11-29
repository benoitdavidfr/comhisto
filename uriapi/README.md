# API définissant et exposant les URI

Cette URI offre les fonctionnalités suivantes :
- définition d'URI stables pour les entités,
- déréférencement des URI fournissant une description des entités en JSON-LD en utilisant le type City du standard Schema.org,
- exposition en Html d'une IHM basique de visualisation et de navigation intégrant des cartes Leaflet
  ainsi que, de manière cachée mais standardisée, l'enregistrement JSON-LD.

Les principales URI utilisées sont les suivantes :

- `https://comhisto.georef.eu/{statut}/{cinsee}/{ddebut}` est l'URI de la version d'une commune ({statut}='COM')
  ou d'une entité rattachée ({statut}='ERAT) portant le code Insee {cinsee} et débutant à la date {ddebut}  
  ex: https://comhisto.georef.eu/COM/01015/2016-01-01

- `https://comhisto.georef.eu/codeInsee/{cinsee}` est l'URI du code Insee {cinsee} correspondant à l'ensemble
  des versions portant ce code  
  ex: https://comhisto.georef.eu/codeInsee/01015

De plus les URL suivantes sont définies :

- `https://comhisto.georef.eu/{statut}/{cinsee}` retourne la version valide d'une commune ou d'une entité rattachée
  portant le code Insee {cinsee}  
  ex:
   - https://comhisto.georef.eu/COM/01015 retourne l'entité https://comhisto.georef.eu/COM/01015/2016-01-01
   - https://comhisto.georef.eu/COM/01340 retourne une erreur 404 car il n'existe pas de version valide de commune
     portant ce code.
- `https://comhisto.georef.eu/{statut}/{cinsee}?date={date}` retourne la version d'une commune ou d'une entité rattachée
  portant le code Insee {cinsee} et existant à la date {date}, ou l'erreur Http 404 si cette version n'existe pas  
  ex:
   - https://comhisto.georef.eu/COM/01015?date=2019-01-01 retourne l'entité https://comhisto.georef.eu/COM/01015/2016-01-01
   - https://comhisto.georef.eu/COM/01340?date=2019-01-01 retourne une erreur 404 car il n'existe pas de version de commune
     portant ce code à cette date.

Les fonctionnalités détaillées de l'API sont définies dans le document Open API 3.0
disponible en JSON à https://comhisto.georef.eu/api
et consultable en Html à https://comhisto.georef.eu/api
