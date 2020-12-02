# Construction des chefs-lieux
L'idée est de construire pour chaque entité un chef-lieu localisé par un point.  
En pratique, on devrait pouvoir associer à chaque code Insee ayant existé, un chef-lieu et lui associer un point.
Il n'est pas utile de versionner ces chefs-lieux qui ne changent pas dans le temps.

Le [fichier GéoJSON zippé](../export/cheflieu.7z) contient ces chefs-lieux avec les caractéristiques suivantes :

- 111 codes Insee ne correspondent pas à un point, il s'agit :
  - des COMD/COMA non découpées par Voronoi, qui pourront être ajoutés ultérieurement,
  - des 6 communes n'ayant pas de chef-lieu dans Admin-Express:
    - Fleury-devant-Douaumont (55189)
    - Louvemont-Côte-du-Poivre (55307)
    - Bezonvaux (55050)
    - Cumières-le-Mort-Homme (55139)
    - Beaumont-en-Verdunois (55039)
    - Haumont-près-Samogneux (55239)
  - des 2 communes de StBarth et StMartin
- définition des champs
  - `cinsee0` est le code Insee initial, soit au 1/1/1943, soit à la première création de la commune,
  - `cinseea` est le code Insee actuel, c'est dire après changement de code s'il y en a eu un, sinon il est identique à `cinsee0`,
  - `dlnom` est le dernier nom local, c'est à dire en priviligiant le nom de communé déléguée à celui de la commune nouvelle,
  - `source` définit la source de la géométrie, elle peut prendre les valeurs suivantes :
    - `absence` pour absence de géométrie dans les cas indiqués ci-dessus,
    - `chef_lieu_carto` quand la géométrie provient de la table `chef_lieu_carto` d'Admin-Express
    - `wpgp` quand le point a été saisi à partir de Wikipédia ou du Géoportail.
