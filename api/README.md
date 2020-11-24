# Mise à disposition de ComHisto sous diverses formes

Outre la mise à disposition de fichiers GéoJSON, ComHisto est mis à disposition sous 3 formes :

- au travers de l'API en JSON/GéoJSON qui impose au client de connaitre la structure des données,
- comme données liées par la définition d'URI identifiant chaque entité, l'utilisation d'ontologies standards
  et la fourniture de données JSON-LD,
- sous la forme de page Html par entité incluant une carte de l'entité, fournissant une IHM basique de navigation
  et intégrant de manière cachée mais standardisée l'enregistrement JSON-LD.

Les principales URI utilisées sont les suivantes :

- `https://comhisto.georef.eu/{statut}/{cinsee}/{ddebut}` est l'URI de la version d'une commune ({statut}='COM')
  ou d'une entité rattachée ({statut}='ERAT) portant le code Insee {cinsee} et débutant à la date {ddebut}  
  ex: https://comhisto.georef.eu/COM/01015/2016-01-01

- `https://comhisto.georef.eu/codeInsee/{cinsee}` est l'URI du code Insee {cinsee} correspondant à l'ensemble
  des versions portant ce code  
  ex: https://comhisto.georef.eu/codeInsee/01015

De plus:
- `https://comhisto.geoapi.fr/{statut}/{cinsee}` retourne la version valide d'une commune ou d'une entité rattachée
  portant le code Insee {cinsee}, ou l'erreur Http 404 s'il n'existe pas de version valide pour ce code  
  ex:
   - https://comhisto.geoapi.fr/COM/01015 retourne l'entité https://comhisto.geoapi.fr/COM/01015/2016-01-01
   - https://comhisto.geoapi.fr/COM/01340 retourne une erreur 404 car il n'existe pas de version valide de commune
     portant ce code.


***
  Logique de mise à disposition:
    - chaque entité est identifiée par un URI dont le déréférencement donne accès à ses propriétés
      - les URI sont généralement de la forme http://comhisto.georef.eu/{concept}/{identifier}/{version}.{format}
    - le déréférencement d'un URI propose, en fonction du paramètre Accept, jusqu'à 3 résultats:
      - un objet JSON/GéoJSON qui fournit le détail des données mais nécessite que le client connaisse leur structure
      - un objet JSON-LD dans des ontologies standards, qui donc standardise cette structure mais en la simplifiant
      - un texte Html qui fournit une IHM basique tout en fournissant le JSON-LD dans l'en-tête Html

  Correspondance id <-> URI:
    En fonction des contextes, une entité est identifiée soit par id soit par un URI.
    L'URI a l'avantage d'être générique mais est long, l'id au contraire est court.
    Il existe une bijection entre les id et les URI, dont il existe 4 catégories
      cinsee (eg 01015) <-> http://comhisto.georef.eu/codeInsee/{cinsee}
      cinsee@ddebut (eg 01015@2016-01-01) <-> https://comhisto.georef.eu/codeInsee/{cinsee}/{ddebut}
      [sr]cinsee (eg r01015) <-> https://comhisto.georef.eu/(COM|ERAT)/{cinsee}
      [sr]cinsee@ddebut (eg r01015@2016-01-01) <-> https://comhisto.georef.eu/(COM|ERAT)/{cinsee}/{ddebut}

  Les motifs d'URI sont:
    / -> URI de référence
      LD: doc de la base comme enregistrement dcat:Dataset, Html: IHM de consultation de la base
    /api (A FAIRE)
      JSON: doc open API de l'API d'accès
    /(COM|ERAT) -> liste des objets COM|ERAT valides dans format JSON-LD
    /(COM|ERAT)/{cinsee}/{ddebut} -> URI de la version de commune|ERAT débutant à {ddebut}
      JSON: Feature GeoJSON, LD: https://schema.org/City, Html: carte Leaflet, ou erreur 404
    /codeInsee/{cinsee} -> URI du code Insee,
      retourne en JSON-LD la liste des versions (dcterms:hasVersion) de COM|ERAT correspondant à {cinsee},
      en Html la carte des versions de COM|ERAT correspondant à ce code,
      si {cinsee} invalide retourne une erreur 404
      correspond à l'id ne contenant que le code Insee
    /elits2020/{cinsee} -> URI de l'élit 2020,
      si l'élit est valide alors retourne en JSON un Feature GeoJSON, en Html une carte,
      si l'élit a été remplacé alors retourne la liste des élits le remplacant (dcterms:replacedBy) (A VOIR),
      si l'élit n'a jamais existé alors retourne une erreur 404
    /map/(s|r|){cinsee}/{ddebut} -> URI de la carte,  en Html/JSON-LD, sinon erreur 404
    /status -> URI du thésaurus des statuts, retourne en JSON-LD un skos:ConceptScheme
    /status/{status} -> URI d'un statut, retourne un skos:Concept en JSON-LD ou erreur 404
    /contexts/{context} -> URI d'un contexte, retourne le contexte en JSON-LD ou erreur 404

  Traitements - Points d'entrée:
    / -> doc ?
    /(COM|ERAT)/{cinsee} -> retourne la version valide de la commune|ERAT en GeoJSON/Html/JSON-LD, sinon erreur 404
    /(COM|ERAT)/{cinsee}?date={date}
      -> retourne la version existant à cette date en GeoJSON/Html/JSON-LD, sinon erreur 404
    /codeInsee/{cinsee}?date={date}
      -> retourne la version existant à cette date soit de la commune s'il y en a une, sinon de l'ERAT,
       en GeoJSON/Html/JSON-LD, sinon erreur 404
    /codeInsee/{cinsee}/{ddebut} -> retourne la version débutant à {ddebut} soit de commune s'il y en a une, sinon d'ERAT,
       en GeoJSON/Html/JSON-LD, sinon erreur 404
    /map/(s|r|){cinsee} -> carte de la version valide de commune|ERAT|codeInsee en Html/JSON-LD, sinon erreur 404

  Les 2 ensembles d'URL sont compatibles.
  Ainsi http://comhisto.georef.eu/ et http://comhisto.geoapi.fr/
  sont mappés vers /prod/georef/yamldoc/pub/comhisto/api/api.php/
  
  - http://comhisto.georef.eu/ correspond à des URI qui identifient des objets pérennes
  - http://comhisto.geoapi.fr/ correspond à des traitements qui ne correspondent pas à un objet pérenne
  
  Publication comme données liées (LD):
    - une version d'entité
      - est un objet de type http://schema.org/City
      - et porte en outre la propriété https://schema.org/temporalCoverage (prévue pour https://schema.org/CreativeWork)
        - qui utilise https://en.wikipedia.org/wiki/ISO_8601#Time_intervals
      - une autre possibilité serait d'utiliser la propriété hasTime de https://www.w3.org/TR/owl-time/
        - mais elle a moins de chance d'être comprise par Google
    - le principe de publication est d'exposer un objet JSON-LD sur la page HTML représentant une entité
    - puis de fournir un sitemap pour l'indexation par Google
  
  Logique d'enchainement replaces/replacedBy (défini dans dcterms(http://purl.org/dc/terms/)):
    - les formats JSON et JSON-LD exposent les champs replaces et replacedBy
    - lien entre une version de code Insee et la suivante/précédente
    - + lors d'un changement de code lien entre les codes
    - + lors de la création de CNOUV avec déléguée propre -> lien replacedBy vers les 2 entités et inverse
    - + lors d'une fusion lien replacedBy de l'entité fusionnée vers la fusionnante et inverse
  
  URI dans les évènements:
    Je décide dans les évènements de début d'utiliser l'URI valable à cette date et non l'URI de l'objet précédent
    Par exemple:
      type: Feature
      id: 'https://comhisto.georef.eu/ERAT/01015/2016-01-01'
      properties:
        ddebut: '2016-01-01'
        edebut:
          devientDéléguéeDe: 'https://comhisto.georef.eu/COM/01015/2016-01-01'
        dfin: null
        efin: null
        statut: COMD
        dnom: Arbignieu
    
      devientDéléguéeDe: https://comhisto.georef.eu/COM/01015/2016-01-01

    J'aurais pu utiliser l'URI de l'objet précédent, ici https://comhisto.georef.eu/COM/01015/1943-01-01
    mais dans certain cas cet objet n'existe pas et j'aurais été obligé de changer le code Insee
**

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

