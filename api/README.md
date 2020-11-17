# API de mise à disposition du référentiel ComHisto (EN COURS)

L'URL de base de l'API est https://comhisto.georef.eu

Les appels peuvent être effectués soit pour obtenir un affichage Html, soit pour obtenir des données en JSON,
en fonction du paramètre HTTP Accept.
Par défaut dans un navigateur, on obtient un document Html avec une description JSON-LD des données.

L'API expose les points d'accès suivants :

- `/` : racine fournissant une documentation
- `/COM/{cinsee}/{ddebut}` : version de la commune ayant pour code Insee {cinsee} et débutant à {ddebut}
  - exemple: http://comhisto.georef.eu/COM/01015/2016-01-01
- `/ERAT/{cinsee}/{ddebut}` : version de l'entité rattachée ayant pour code Insee {cinsee} et débutant à {ddebut}
  - exemple: http://comhisto.georef.eu/ERAT/01015/2016-01-01
- `/COM/{cinsee}` : version valide de la commune ayant pour code Insee {cinsee}
  - exemple: http://comhisto.georef.eu/COM/01015
- `/ERAT/{cinsee}` : version valide de l'entité rattachée ayant pour code Insee {cinsee}
  - exemple: http://comhisto.georef.eu/ERAT/01015
- `/codeInsee/{cinsee}` : liste des versions d'entités ayant pour code Insee {cinsee}
  - exemple: http://comhisto.georef.eu/codeInsee/01015
- `/codeInsee/{cinsee}/{ddebut}` : liste des versions d'entités ayant pour code Insee {cinsee} et débutant à {ddebut}
  - exemple: http://comhisto.georef.eu/codeInsee/01015/2016-01-01
  
Les URL en https://comhisto.georef.eu correspondent aux URI des objets décrits.

L'API expose aussi avec l'URL de base https://comhisto.geoapi.fr
2 points d'entrée pour obtenir la description d'entités correspondant à une date donnée:

- `http://comhisto.geoapi.fr/COM/{cinsee}?date={date}` : version de la commune ayant pour code Insee {cinsee} et existant au {date}
  - exemple: http://comhisto.geoapi.fr/COM/01015?date=2015-01-01
- `http://comhisto.geoapi.fr/ERAT/{cinsee}?date={date}` : version de l'entité rattachée ayant pour code Insee {cinsee} et existant au {date}
  - exemple: http://comhisto.geoapi.fr/ERAT/01015?date=2015-01-01
- `http://comhisto.geoapi.fr/ERAT/{cinsee}?date={date}` : version des entités ayant pour code Insee {cinsee} et existant au {date}
  - exemple: http://comhisto.geoapi.fr/codeInsee/01015?date=2015-01-01


