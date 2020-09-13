# Documentation du fichier comhistog3.geojson

Les objets (features) du fichier correspondent aux versions successives des entités du référentiel.  
Les propriétés suivantes sont fournies:

- `type` - soit le caractère `'r'` s'il s'agit d'entité rattachée (commune associée, commune déléguée ou arrondissement municipal),
  soit le caractère `'s'` sinon ;
- `cinsee` - code Insee de l'entité sur 5 caractères ;
- `ddebut` - date de création de la version sous la forme d'une chaine de 10 caractères dans le format YYYY-MM-DD ;
- `edebut` - évènements de création de la version sous la forme d'une structure JSON/Yaml, 
- `dfin` - date du lendemain de la fin de la version dans format YYYY-MM-DD,
  ou null ssi la version est valide à la date de référence du référentiel ;
- `efin` - évènements de fin de la version sous la forme d'une structure JSON/Yaml,
  ou null ssi la version est valide à la date de référence du référentiel ;
- `statut` - statut de l'entité, prend une des valeurs suivantes:
  - `BASE` - commune de base (ni commune de rattachement, ni entité rattachée)
  - `ASSO` - commune de rattachement d'une association
  - `NOUV` - commune de rattachement d'une commune nouvelle
  - `COMA` - commune associée
  - `COMD` - commune déléguée
  - `ARDM` - arrondissement municipal
- `crat` - pour une entité rattachée (COMA, COMD, ARDM) code Insee de la commune de rattachement, sinon null ;
- `erats` - pour une commune de rattachement (ASSO, NOUV) liste JSON/Yaml des codes Insee des entités rattachées, sinon liste vide ;
- `elits` - liste des éléments intemporels propres ou null ssi il n'y en a pas ;
- `dnom` - nom

De plus, chaque objet est identifié dans la propriété `id`
qui est la concaténation de la propriété `type`, de `cinsee`, du caratère `@` et de la propriété `ddebut`.
