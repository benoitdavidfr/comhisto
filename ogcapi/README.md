# API conforme au standard OGC API Features

Cette API expose les versions de commune et d'entités rattachées conformément
au [standard OGC API Features](http://docs.opengeospatial.org/is/17-069r3/17-069r3.html).

La page d'accueil (landing page) est https://features.geoapi.fr/comhisto .

L'API est décrite selon les [spécification OpenAPI 3.0](http://spec.openapis.org/)
en JSON à https://features.geoapi.fr/comhisto/api?f=json

La liste des collections est disponible à https://features.geoapi.fr/comhisto/collections

Les versions de communes et d'entités rattachées sont disponibles à
https://features.geoapi.fr/comhisto/collections/comhistog3/items  
Les objets peuvent être balayés au travers du lien *next*.

## Utilisation dans QGis
Cette API peut être utilisée dans les versions récentes de QGis (>= 3.16).

Pour visualiser aisément les données, il est recommandé:

- de dédoubler la couche en 2 en utilisant pour les communes simples le filtre `type='s'`
  et pour les entitées rattachées le filtre `type='r'`
- d'utiliser le mode temporel de QGis en indiquant le champ `ddebut`comme champ Début et le champ `dfin` comme champ de fin.
