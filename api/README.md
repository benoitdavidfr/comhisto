# API de mise à disposition du référentiel ComHisto (EN COURS)

L'URL de base de l'API est http://comhisto.georef.eu

Les appels peuvent être effectués soit pour obtenir un affichage Html, soit pour obtenir des données en JSON,
en fonction du paramètre HTTP Accept.
Par défaut dans un navigateur, on obtient un document Html avec une description JSON-LD des données.

L'API expose les points d'accès suivants :

- `/` : racine fournissant une documentation
- `/COM/{cinsee}/{ddebut}` : version de commune ayant pour code Insee {cinsee} et débutant à {ddebut}
  - exemple: http://comhisto.georef.eu/COM/01015/2016-01-01
- `/ERAT/{cinsee}/{ddebut}` : version d'entité rattachée ayant pour code Insee {cinsee} et débutant à {ddebut}
  - exemple: http://comhisto.georef.eu/ERAT/01015/2016-01-01
